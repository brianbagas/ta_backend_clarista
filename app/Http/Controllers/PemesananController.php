<?php

namespace App\Http\Controllers;

use Illuminate\Validation\ValidationException;
use App\Models\Pemesanan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Kamar;
use Illuminate\Support\Facades\Auth;
use App\Models\DetailPemesanan;
use App\Models\KamarUnit;
use App\Models\PenempatanKamar;
use App\Models\Promo;
use App\Models\User;
use App\Models\Pembayaran;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Traits\ApiResponseTrait;
use App\Mail\PesananDibatalkan;


class PemesananController extends Controller
{
    use ApiResponseTrait;



    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $pemesanans = $user->pemesanans()
            ->with(['detailPemesanans.kamar.images', 'review'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($pemesanans, 'Data pemesanan berhasil diambil');
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
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'tanggal_check_in' => 'required|date|after_or_equal:today',
                'tanggal_check_out' => 'required|date|after:tanggal_check_in',
                'kamars' => 'required|array',
                'kamars.*.kamar_id' => 'required|exists:kamars,id_kamar',
                'kamars.*.jumlah_kamar' => 'required|integer|min:1',
                'kode_promo' => 'nullable|string|exists:promos,kode_promo',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        }

        DB::beginTransaction();
        try {
            $checkIn = Carbon::parse($validated['tanggal_check_in']);
            $checkOut = Carbon::parse($validated['tanggal_check_out']);
            $durasiMenginap = $checkIn->diffInDays($checkOut);
            $subtotal = 0;
            $bookingPlan = [];

            foreach ($validated['kamars'] as $item) {
                $kamar = Kamar::findOrFail($item['kamar_id']);

                $availableUnits = $kamar->getAvailableUnits($checkIn, $checkOut, $item['jumlah_kamar'], true);


                if ($availableUnits->count() < $item['jumlah_kamar']) {
                    throw ValidationException::withMessages([
                        'kamars' => 'Stok untuk tipe "' . $kamar->tipe_kamar . '" tidak mencukupi di tanggal tersebut.'
                    ]);
                }


                $subtotal += $kamar->harga * $item['jumlah_kamar'] * $durasiMenginap;

                $bookingPlan[] = [
                    'kamar_obj' => $kamar,
                    'qty' => $item['jumlah_kamar'],
                    'units' => $availableUnits
                ];
            }

            $totalBayar = $subtotal;
            $promoId = null;

            if (!empty($validated['kode_promo'])) {
                $promo = Promo::where('kode_promo', $validated['kode_promo'])
                    ->where('is_active', true)
                    ->where('berlaku_mulai', '<=', now())
                    ->where('berlaku_selesai', '>=', now())
                    ->first();

                if ($promo) {
                    $nilaiDiskon = ($promo->tipe_diskon === 'persen')
                        ? ($subtotal * $promo->nilai_diskon) / 100
                        : $promo->nilai_diskon;

                    $diskonFinal = min($subtotal, $nilaiDiskon);
                    $totalBayar = $subtotal - $diskonFinal;
                    $promoId = $promo->id;
                }
                if ($promoId) {
                    // Lock promo row
                    $promoLocked = Promo::where('id', $promoId)->lockForUpdate()->first();

                    if (!$promoLocked) {
                        DB::rollBack();
                        return $this->errorResponse('Promo tidak ditemukan atau tidak aktif.', 404);
                    }

                    // Re-check kuota
                    if (!is_null($promoLocked->kuota) && $promoLocked->kuota_terpakai >= $promoLocked->kuota) {
                        DB::rollBack();
                        return $this->errorResponse('Maaf, kuota promo baru saja habis digunakan pengguna lain.', 409);
                    }


                    $promoLocked->increment('kuota_terpakai');
                }
            }


            $pemesanan = Pemesanan::create([
                'user_id' => Auth::id(),
                'tanggal_check_in' => $checkIn,
                'tanggal_check_out' => $checkOut,
                'total_bayar' => $totalBayar,
                'promo_id' => $promoId,
                'status_pemesanan' => 'menunggu_pembayaran',
                'expired_at' => now()->addHours(1)
            ]);


            foreach ($bookingPlan as $plan) {

                $detail = DetailPemesanan::create([
                    'pemesanan_id' => $pemesanan->id,
                    'kamar_id' => $plan['kamar_obj']->id_kamar,
                    'jumlah_kamar' => $plan['qty'],
                    'harga_per_malam' => $plan['kamar_obj']->harga,
                ]);


                foreach ($plan['units'] as $unit) {
                    PenempatanKamar::create([
                        'detail_pemesanan_id' => $detail->id,
                        'kamar_unit_id' => $unit->id,
                        'status_penempatan' => 'pending',
                    ]);
                }
            }

            DB::commit();
            return $this->successResponse(
                $pemesanan->load('detailPemesanans.penempatanKamars'),
                'Booking berhasil dibuat',
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();

            if ($e instanceof ValidationException) {
                return $this->errorResponse('Validasi gagal', 422, $e->errors());
            }
            return $this->errorResponse('Gagal membuat pesanan.', 500, $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Pemesanan $pemesanan)
    {
        if (Auth::id() !== $pemesanan->user_id) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        return $this->successResponse(
            $pemesanan->load('user', 'detailPemesanans.kamar', 'promo'),
            'Detail pemesanan berhasil diambil'
        );
    }

    public function indexOwner(Request $request)
    {
        $query = Pemesanan::with(['user', 'detailPemesanans.penempatanKamars', 'pembayaran']);

        // Default sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        // Proteksi kolom sorting yang valid agar tidak SQL injection
        $allowedSortColumns = ['kode_booking', 'tanggal_check_in', 'tanggal_check_out', 'total_bayar', 'status_pemesanan', 'created_at'];

        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest();
        }

        // Filter by status tab
        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'perlu_tindakan') {
                $query->where('status_pemesanan', 'menunggu_konfirmasi');
            } elseif ($status === 'selesai_batal') {
                $query->whereIn('status_pemesanan', ['selesai', 'batal', 'tidak_datang']);
            } else {
                $query->where('status_pemesanan', $status);
            }
        }

        // Search by nama tamu atau kode booking
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode_booking', 'LIKE', "%{$search}%")
                    ->orWhereHas('user', function ($q2) use ($search) {
                        $q2->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 10);
        $pemesanans = $query->paginate($perPage);

        return $this->successResponse($pemesanans, 'Data pemesanan owner berhasil diambil');
    }

    public function showForOwner(Pemesanan $pemesanan)
    {
        return $this->successResponse(
            $pemesanan->load('user', 'detailPemesanans.kamar', 'detailPemesanans.penempatanKamars.kamarUnit', 'promo'),
            'Detail pemesanan owner berhasil diambil'
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pemesanan $pemesanan)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Pemesanan $pemesanan)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pemesanan $pemesanan)
    {
        //
    }

    /**
     * Cancel the specified reservation (Customer).
     */
    public function cancel(Pemesanan $pemesanan)
    {

        if (Auth::id() !== $pemesanan->user_id) {
            return $this->errorResponse('Akses ditolak. Anda bukan pemilik pesanan ini.', 403);
        }


        if ($pemesanan->status_pemesanan !== 'menunggu_pembayaran') {
            return $this->errorResponse(
                'Pemesanan tidak dapat dibatalkan karena status saat ini adalah: ' . $pemesanan->status_pemesanan,
                400
            );
        }


        try {
            DB::beginTransaction();


            $pemesanan->update([
                'status_pemesanan' => 'batal',
                'alasan_batal' => 'Dibatalkan oleh customer',
                'dibatalkan_oleh' => 'customer',
                'dibatalkan_at' => now(),
            ]);

            // Release kuota promo
            if ($pemesanan->promo_id) {
                $promo = Promo::find($pemesanan->promo_id);
                if ($promo) {
                    $promo->decrement('kuota_terpakai');
                }
            }

            // Update penempatan kamar
            foreach ($pemesanan->detailPemesanans as $detail) {

                $detail->penempatanKamars()->update([
                    'status_penempatan' => 'cancelled',
                    'dibatalkan_oleh' => 'customer',
                    'dibatalkan_at' => now(),
                    'catatan' => 'Auto-cancel from booking #' . $pemesanan->id
                ]);
            }

            DB::commit();

            return $this->successResponse($pemesanan, 'Pemesanan berhasil dibatalkan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal membatalkan pesanan.', 500, $e->getMessage());
        }
    }

    /**
     * Cancel the specified reservation by Owner.
     */
    public function cancelByOwner(Request $request, Pemesanan $pemesanan)
    {

        try {
            $validated = $request->validate([
                'alasan' => 'required|string|min:10',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        }


        if ($pemesanan->status_pemesanan === 'selesai') {
            return $this->errorResponse('Pemesanan yang sudah selesai tidak bisa dibatalkan.', 400);
        }

        if ($pemesanan->status_pemesanan === 'batal') {
            return $this->errorResponse('Pemesanan ini sudah dibatalkan sebelumnya.', 400);
        }


        try {
            DB::beginTransaction();


            $pemesanan->update([
                'status_pemesanan' => 'batal',
                'alasan_batal' => $validated['alasan'],
                'dibatalkan_oleh' => 'owner',
                'dibatalkan_at' => now(),
                'catatan' => 'Dibatalkan oleh owner: ' . $validated['alasan'],
            ]);

            // Update penempatan kamar
            foreach ($pemesanan->detailPemesanans as $detail) {
                $detail->penempatanKamars()->update([
                    'status_penempatan' => 'cancelled',
                    'dibatalkan_oleh' => 'owner',
                    'dibatalkan_at' => now(),
                    'catatan' => 'Cancelled by Owner. Reason: ' . $validated['alasan']
                ]);
            }

            // Release kuota promo
            if ($pemesanan->promo_id) {
                $promo = Promo::find($pemesanan->promo_id);
                if ($promo) {
                    $promo->decrement('kuota_terpakai');
                }
            }

            DB::commit();

            if ($pemesanan->user && $pemesanan->user->email) {
                Mail::to($pemesanan->user->email)->send(new PesananDibatalkan($pemesanan));
            }

            return $this->successResponse($pemesanan, 'Pemesanan berhasil dibatalkan oleh owner.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal membatalkan pesanan.', 500, $e->getMessage());
        }
    }


    /**
     * Store a newly created resource in storage (Offline/Walk-in).
     */
    public function storeOffline(Request $request)
    {
        try {

            $request->validate([
                'nama_pemesan' => 'required|string',
                'no_hp' => 'required|string',
                'check_in_date' => 'required|date|after_or_equal:today',
                'durasi' => 'required|integer|min:1',
                'kamars' => 'required|array|min:1',
                'kamars.*.kamar_id' => 'required|exists:kamars,id_kamar',
                'kamars.*.jumlah_kamar' => 'required|integer|min:1',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        }


        $checkInDate = Carbon::parse($request->check_in_date);
        $checkOutDate = $checkInDate->copy()->addDays($request->durasi);
        $durasiMenginap = $request->durasi;

        try {

            $result = DB::transaction(function () use ($request, $checkInDate, $checkOutDate, $durasiMenginap) {


                $totalHarga = 0;
                $bookingPlan = [];

                foreach ($request->kamars as $item) {
                    $kamar = Kamar::findOrFail($item['kamar_id']);


                    $availableUnits = $kamar->getAvailableUnits($checkInDate, $checkOutDate, $item['jumlah_kamar'], true);

                    if ($availableUnits->count() < $item['jumlah_kamar']) {
                        throw new \Exception("Stok kamar \"{$kamar->nama_kamar}\" tidak mencukupi. Hanya tersedia " . $availableUnits->count() . " unit.");
                    }


                    $totalHarga += $kamar->harga * $item['jumlah_kamar'] * $durasiMenginap;

                    $bookingPlan[] = [
                        'kamar' => $kamar,
                        'qty' => $item['jumlah_kamar'],
                        'units' => $availableUnits
                    ];
                }

                // Cari/buat user offline
                $user = User::firstOrCreate(
                    ['email' => $request->no_hp . '@offline.guest'],
                    [
                        'name' => $request->nama_pemesan,
                        'no_hp' => $request->no_hp,
                        'password' => Hash::make('tamu123'),
                        'role_id' => 2,
                        'email_verified_at' => now(),
                    ]
                );


                $pemesanan = Pemesanan::create([
                    'user_id' => $user->id,
                    'tanggal_check_in' => $checkInDate,
                    'tanggal_check_out' => $checkOutDate,
                    'total_bayar' => $totalHarga,
                    'status_pemesanan' => 'dikonfirmasi',
                    'catatan' => 'Pemesanan Offline (Walk-in) via Admin',
                ]);


                Pembayaran::create([
                    'pemesanan_id' => $pemesanan->id,
                    'jumlah_bayar' => $totalHarga,
                    'bank_tujuan' => 'CASH',
                    'nama_pengirim' => $request->nama_pemesan,
                    'status_konfirmasi' => 'verified',
                    'tanggal_konfirmasi' => now(),
                    'bukti_bayar_path' => 'offline_transaction.jpg'
                ]);


                $isCheckInToday = $checkInDate->isToday();

                foreach ($bookingPlan as $plan) {
                    $detail = DetailPemesanan::create([
                        'pemesanan_id' => $pemesanan->id,
                        'kamar_id' => $plan['kamar']->id_kamar,
                        'jumlah_kamar' => $plan['qty'],
                        'harga_per_malam' => $plan['kamar']->harga,
                    ]);

                    foreach ($plan['units'] as $unit) {
                        PenempatanKamar::create([
                            'detail_pemesanan_id' => $detail->id,
                            'kamar_unit_id' => $unit->id,
                            'status_penempatan' => $isCheckInToday ? 'assigned' : 'pending',
                            'check_in_aktual' => $isCheckInToday ? now() : null,
                            'check_out_aktual' => null,
                        ]);
                    }
                }

                return $pemesanan->load('detailPemesanans.kamar', 'detailPemesanans.penempatanKamars');
            });

            return $this->successResponse($result, 'Pemesanan Offline berhasil dibuat', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal membuat pemesanan offline', 500, $e->getMessage());
        }
    }

    /**
     * Mark a booking as tidak datang (Owner only).
     */
    public function markAsNoShow(Pemesanan $pemesanan)
    {

        if ($pemesanan->status_pemesanan !== 'dikonfirmasi') {
            return $this->errorResponse(
                'Hanya pesanan yang sudah dikonfirmasi yang bisa ditandai tidak datang. Status saat ini: ' . $pemesanan->status_pemesanan,
                400
            );
        }

        // Cek apakah sudah check-in
        $hasCheckedIn = $pemesanan->detailPemesanans()
            ->whereHas('penempatanKamars', function ($q) {
                $q->whereNotNull('check_in_aktual');
            })
            ->exists();

        if ($hasCheckedIn) {
            return $this->errorResponse(
                'Pesanan tidak bisa ditandai tidak datang karena pelanggan sudah check-in.',
                400
            );
        }


        try {
            DB::beginTransaction();

            $pemesanan->update([
                'status_pemesanan' => 'tidak_datang',
                'catatan' => 'Ditandai tidak datang oleh owner - Pelanggan tidak datang di tanggal check-in',
                'dibatalkan_oleh' => 'owner',
                'dibatalkan_at' => now(),
            ]);

            // Update penempatan kamar
            foreach ($pemesanan->detailPemesanans as $detail) {
                $detail->penempatanKamars()->update([
                    'status_penempatan' => 'cancelled',
                    'dibatalkan_oleh' => 'owner',
                    'dibatalkan_at' => now(),
                    'catatan' => 'Tidak datang - Pelanggan tidak datang'
                ]);
            }

            DB::commit();

            return $this->successResponse(
                $pemesanan->load('detailPemesanans.penempatanKamars'),
                'Pesanan berhasil ditandai sebagai tidak datang. Kamar telah dirilis.'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal menandai pesanan sebagai tidak datang.', 500, $e->getMessage());
        }
    }

    /**
     * Get soft-deleted pemesanans.
     */
    public function trashed()
    {
        $pemesanans = Pemesanan::onlyTrashed()
            ->with([
                'user',
                'detailPemesanans' => function ($query) {

                    $query->withTrashed()->with([
                        'kamar' => function ($q) {
                            $q->withTrashed();
                        }
                    ]);
                }
            ])
            ->latest('deleted_at')
            ->get();

        return $this->successResponse($pemesanans, 'Data pemesanan terhapus berhasil diambil');
    }

    /**
     * Restore soft-deleted pemesanan.
     */
    public function restore($id)
    {
        $pemesanan = Pemesanan::onlyTrashed()->findOrFail($id);


        $pemesanan->restore();

        // Restore relasi
        foreach ($pemesanan->detailPemesanans()->onlyTrashed()->get() as $detail) {
            $detail->restore();
            $detail->penempatanKamars()->onlyTrashed()->restore();
        }


        if ($pemesanan->pembayaran()->onlyTrashed()->exists()) {
            $pemesanan->pembayaran()->onlyTrashed()->restore();
        }


        if ($pemesanan->review()->onlyTrashed()->exists()) {
            $pemesanan->review()->onlyTrashed()->restore();
        }

        return $this->successResponse($pemesanan, 'Pemesanan berhasil dikembalikan (restore) beserta data terkait.');
    }

    /**
     * Force delete pemesanan.
     */
    public function forceDelete($id)
    {
        $pemesanan = Pemesanan::onlyTrashed()->findOrFail($id);

        try {
            DB::beginTransaction();




            foreach ($pemesanan->detailPemesanans()->withTrashed()->get() as $detail) {

                $detail->penempatanKamars()->withTrashed()->forceDelete();

                $detail->forceDelete();
            }


            if ($pemesanan->pembayaran()->withTrashed()->exists()) {

                $pemesanan->pembayaran()->withTrashed()->forceDelete();
            }


            if ($pemesanan->review()->withTrashed()->exists()) {
                $pemesanan->review()->withTrashed()->forceDelete();
            }


            $pemesanan->forceDelete();

            DB::commit();
            return $this->successResponse(null, 'Pemesanan dan seluruh data terkait berhasil dihapus permanen.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal menghapus permanen pemesanan.', 500, $e->getMessage());
        }
    }
}
