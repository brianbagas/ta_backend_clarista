<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use Illuminate\Http\Request;
use App\Models\Pemesanan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\PesananDikonfirmasi;
use App\Mail\PembayaranDitolak;
use App\Mail\PembayaranMasuk;
use App\Traits\ApiResponseTrait;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Promo;
class PembayaranController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function indexForOwner()
    {
        $pemesanans = Pemesanan::where('status_pemesanan', 'menunggu_konfirmasi')
            ->with(['user', 'pembayaran', 'detailPemesanans.kamar'])
            ->latest()
            ->get();

        return $this->successResponse($pemesanans, 'Daftar pembayaran menunggu konfirmasi berhasil ditampilkan.');
    }

    public function verifikasi(Request $request, Pemesanan $pemesanan)
    {
        $validated = $request->validate([
            'status' => 'required|in:dikonfirmasi,batal',
            'catatan_admin' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            if ($validated['status'] === 'dikonfirmasi') {
                $pemesanan->update([
                    'status_pemesanan' => 'dikonfirmasi',
                    'catatan' => $validated['catatan_admin'] ?? null,
                ]);

                if ($pemesanan->pembayaran) {
                    $pemesanan->pembayaran()->update(['status_konfirmasi' => 'Dikonfirmasi']);
                }
            } else {
                $catatan = $validated['catatan_admin'] ?? 'Bukti pembayaran tidak valid.';

                $pemesanan->update([
                    'status_pemesanan' => 'batal',
                    'catatan' => $catatan,
                    'alasan_batal' => $catatan,
                    'dibatalkan_oleh' => 'owner',
                    'dibatalkan_at' => now(),
                ]);

                if ($pemesanan->pembayaran) {
                    $pemesanan->pembayaran()->update(['status_konfirmasi' => 'ditolak']);
                }
                foreach ($pemesanan->detailPemesanans as $detail) {
                    $detail->penempatanKamars()->update([
                        'status_penempatan' => 'cancelled',
                        'dibatalkan_oleh' => 'owner',
                        'dibatalkan_at' => now(),
                        'catatan' => 'Payment rejected by Owner. Reason: ' . $catatan
                    ]);
                }
                // Release kuota promo
                if ($pemesanan->promo_id) {
                    $promo = Promo::find($pemesanan->promo_id);
                    if ($promo) {
                        $promo->decrement('kuota_terpakai');
                    }
                }
            }

            DB::commit();

            try {
                if ($pemesanan->user && $pemesanan->user->email) {
                    if ($validated['status'] === 'dikonfirmasi') {
                        Mail::to($pemesanan->user->email)->send(new PesananDikonfirmasi($pemesanan));
                    } else {
                        $catatanAdmin = $validated['catatan_admin'] ?? 'Bukti pembayaran tidak valid. Silakan upload ulang dengan bukti yang jelas.';
                        Mail::to($pemesanan->user->email)->send(new PembayaranDitolak($pemesanan, $catatanAdmin));
                    }
                }
            } catch (\Exception $e) {
                Log::error('Gagal kirim email verifikasi: ' . $e->getMessage());
            }

            return $this->successResponse($pemesanan, 'Status pemesanan berhasil diubah menjadi ' . $validated['status']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal verifikasi pembayaran: ' . $e->getMessage());
            return $this->errorResponse('Terjadi kesalahan saat memproses verifikasi.', 500);
        }
    }

    public function showVerificationDetail(Pemesanan $pemesanan)
    {

        $pemesanan->load(['user', 'detailPemesanans.kamar', 'pembayaran', 'detailPemesanans.penempatanKamars.kamarUnit']);

        return $this->successResponse($pemesanan, 'Detail verifikasi berhasil diambil.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Pemesanan $pemesanan)
    {

        if (Auth::id() !== $pemesanan->user_id) {
            return $this->errorResponse('Akses ditolak.', 403);
        }
        if ($pemesanan->status_pemesanan !== 'menunggu_pembayaran') {
            return $this->errorResponse('Pembayaran tidak dapat diproses untuk status pesanan ini.', 400);
        }
        $validated = $request->validate([
            'bukti_bayar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'jumlah_bayar' => 'required|numeric|min:0',
            'bank_tujuan' => 'nullable|string|max:50',
            'nama_pengirim' => 'nullable|string|max:100',

        ]);
        // Validasi jumlah bayar
        if ($validated['jumlah_bayar'] != $pemesanan->total_bayar) {
            return $this->errorResponse(
                'Jumlah pembayaran tidak sesuai. Harap transfer sejumlah Rp ' .
                number_format($pemesanan->total_bayar, 0, ',', '.'),
                422
            );
        }
        $path = $request->file('bukti_bayar')->store('bukti_pembayaran', 'public');
        DB::beginTransaction();
        try {
            Pembayaran::create([
                'pemesanan_id' => $pemesanan->id,
                'bukti_bayar_path' => $path,
                'jumlah_bayar' => $validated['jumlah_bayar'],
                'bank_tujuan' => $validated['bank_tujuan'],
                'nama_pengirim' => $validated['nama_pengirim'],
                'tanggal_konfirmasi' => now(),
            ]);
            $pemesanan->update(['status_pemesanan' => 'menunggu_konfirmasi']);
            DB::commit();
            // Notifikasi email ke owner
            try {
                $pemesanan->load('pembayaran');
                $ownerEmail = User::whereHas('role', function ($q) {
                    $q->where('role', 'owner');
                })->value('email');
                $recipient = $ownerEmail ?: 'owner@clarista.com';
                Mail::to($recipient)->send(new PembayaranMasuk($pemesanan));
            } catch (\Exception $e) {
                Log::error('Gagal kirim email notifikasi pembayaran owner: ' . $e->getMessage());
            }
            return $this->successResponse(null, 'Bukti pembayaran berhasil diunggah. Menunggu konfirmasi dari admin.', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            // Clean up uploaded file if DB fails
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            }
            Log::error('Gagal unggah pembayaran: ' . $e->getMessage());
            return $this->errorResponse('Terjadi kesalahan saat memproses data.', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Pembayaran $pembayaran)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pembayaran $pembayaran)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Pembayaran $pembayaran)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pembayaran $pembayaran)
    {
        //
    }

    public function getPembayaranNotifikasi()
    {
        $pembayaran = Pemesanan::where('status_pemesanan', 'menunggu_konfirmasi')->count();
        return $this->successResponse($pembayaran, 'Jumlah pesanan menunggu konfirmasi berhasil ditampilkan.');
    }

    /**
     * Get soft-deleted pembayarans.
     */
    public function trashed()
    {
        $pembayarans = Pembayaran::onlyTrashed()
            ->with([
                'pemesanan' => function ($query) {
                    $query->withTrashed()->with('user');
                }
            ])
            ->latest('deleted_at')
            ->get();

        return $this->successResponse($pembayarans, 'Data pembayaran terhapus berhasil diambil');
    }

    /**
     * Restore soft-deleted pembayaran.
     */
    public function restore($id)
    {
        $pembayaran = Pembayaran::onlyTrashed()->findOrFail($id);

        $pembayaran->restore();

        return $this->successResponse($pembayaran, 'Pembayaran berhasil dikembalikan (restore).');
    }

    /**
     * Force delete pembayaran.
     */
    public function forceDelete($id)
    {
        $pembayaran = Pembayaran::onlyTrashed()->findOrFail($id);


        if ($pembayaran->bukti_bayar_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($pembayaran->bukti_bayar_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($pembayaran->bukti_bayar_path);
        }

        $pembayaran->forceDelete();

        return $this->successResponse(null, 'Pembayaran berhasil dihapus permanen.');
    }
}
