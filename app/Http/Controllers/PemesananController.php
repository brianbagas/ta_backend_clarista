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
    { /** @var \App\Models\User $user */
        $user = Auth::user();

        // Ambil pemesanan milik user, urutkan dari yang terbaru dengan relasinya
        $pemesanans = $user->pemesanans()
            ->with(['detailPemesanans.kamar.images'])
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

                $occupiedUnitIds = PenempatanKamar::whereHas('detailPemesanan.pemesanan', function ($q) use ($checkIn, $checkOut) {
                    $q->where('status_pemesanan', '!=', 'batal')
                        ->where(function ($query) use ($checkIn, $checkOut) {
                            $query->where('tanggal_check_in', '<', $checkOut)
                                ->where('tanggal_check_out', '>', $checkIn);
                        });
                })
                    ->whereNotIn('status_penempatan', ['cancelled', 'checked_out']) // PENTING: Exclude penempatan yang dibatalkan/selesai
                    ->pluck('kamar_unit_id');


                $availableUnits = KamarUnit::where('kamar_id', $kamar->id_kamar)
                    ->where('status_unit', 'available')
                    ->whereNotIn('id', $occupiedUnitIds)
                    ->take($item['jumlah_kamar'])
                    ->lockForUpdate()
                    ->get();


                if ($availableUnits->count() < $item['jumlah_kamar']) {
                    throw ValidationException::withMessages([
                        'kamars' => 'Stok untuk tipe "' . $kamar->tipe_kamar . '" tidak mencukupi di tanggal tersebut.'
                    ]);
                }

                // Hitung Subtotal
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
                    // Lock row promo secara eksklusif
                    $promoLocked = Promo::where('id', $promoId)->lockForUpdate()->first();

                    if (!$promoLocked) {
                        DB::rollBack();
                        return $this->errorResponse('Promo tidak ditemukan atau tidak aktif.', 404);
                    }

                    // Cek kuota lagi setelah dilock
                    if (!is_null($promoLocked->kuota) && $promoLocked->kuota_terpakai >= $promoLocked->kuota) {
                        DB::rollBack();
                        return $this->errorResponse('Maaf, kuota promo baru saja habis digunakan pengguna lain.', 409);
                    }

                    // Aman untuk increment
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

            // B. Detail & Penempatan
            foreach ($bookingPlan as $plan) {
                // 1. Simpan Detail Pemesanan (Transaksi Barang)
                $detail = DetailPemesanan::create([
                    'pemesanan_id' => $pemesanan->id,
                    'kamar_id' => $plan['kamar_obj']->id_kamar,
                    'jumlah_kamar' => $plan['qty'],
                    'harga_per_malam' => $plan['kamar_obj']->harga,
                ]);

                // 2. Simpan Penempatan Kamar
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
            // Return error biar gampang debugging
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
        // Tambahkan 'promo' ke dalam load()
        return $this->successResponse(
            $pemesanan->load('user', 'detailPemesanans.kamar', 'promo'),
            'Detail pemesanan berhasil diambil'
        );
    }

    public function indexOwner()
    {
        // Ambil semua pemesanan owner
        $pemesanans = Pemesanan::with(['user', 'detailPemesanans.penempatanKamars'])
            ->latest()
            ->get();

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
        // 1. Cek Kepemilikan (Authorization)
        if (Auth::id() !== $pemesanan->user_id) {
            return $this->errorResponse('Akses ditolak. Anda bukan pemilik pesanan ini.', 403);
        }

        // 2. Cek Status Pemesanan
        if ($pemesanan->status_pemesanan !== 'menunggu_pembayaran') {
            return $this->errorResponse(
                'Pemesanan tidak dapat dibatalkan karena status saat ini adalah: ' . $pemesanan->status_pemesanan,
                400
            );
        }

        // 3. Proses Pembatalan
        try {
            DB::beginTransaction();

            // Ubah status menjadi 'batal' dengan tracking
            $pemesanan->update([
                'status_pemesanan' => 'batal',
                'alasan_batal' => 'Dibatalkan oleh customer',
                'dibatalkan_oleh' => 'customer',
                'dibatalkan_at' => now(),
            ]);

            // Release kuota promo jika digunakan
            if ($pemesanan->promo_id) {
                $promo = Promo::find($pemesanan->promo_id);
                if ($promo) {
                    $promo->decrement('kuota_terpakai');
                }
            }

            // PenempatanKamar tidak perlu dihapus manual, logic availableCheck sudah mengecualikan status 'batal'.
            // Update status untuk data rapi
            foreach ($pemesanan->detailPemesanans as $detail) {
                // Mass update semua penempatan di detail ini
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
        // 1. Validasi Input
        try {
            $validated = $request->validate([
                'alasan' => 'required|string|min:10',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        }

        // 2. Cek Status Pemesanan
        if ($pemesanan->status_pemesanan === 'selesai') {
            return $this->errorResponse('Pemesanan yang sudah selesai tidak bisa dibatalkan.', 400);
        }

        if ($pemesanan->status_pemesanan === 'batal') {
            return $this->errorResponse('Pemesanan ini sudah dibatalkan sebelumnya.', 400);
        }

        // 3. Proses Pembatalan
        try {
            DB::beginTransaction();

            // Ubah status menjadi 'batal' dengan tracking lengkap
            $pemesanan->update([
                'status_pemesanan' => 'batal',
                'alasan_batal' => $validated['alasan'],
                'dibatalkan_oleh' => 'owner',
                'dibatalkan_at' => now(),
                'catatan' => 'Dibatalkan oleh owner: ' . $validated['alasan'],
            ]);

            // Sync status PenempatanKamar -> Cancelled
            foreach ($pemesanan->detailPemesanans as $detail) {
                $detail->penempatanKamars()->update([
                    'status_penempatan' => 'cancelled',
                    'dibatalkan_oleh' => 'owner',
                    'dibatalkan_at' => now(),
                    'catatan' => 'Cancelled by Owner. Reason: ' . $validated['alasan']
                ]);
            }

            // Release kuota promo jika digunakan
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
            // 1. Validasi Input
            $request->validate([
                'nama_pemesan' => 'required|string',
                'no_hp' => 'required|string',
                'check_in_date' => 'required|date|after_or_equal:today',
                'durasi' => 'required|integer|min:1',
                'kamar_id' => 'required|exists:kamars,id_kamar',
                'jumlah_kamar' => 'required|integer|min:1',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        }

        // Hitung tanggal check out
        $checkInDate = Carbon::parse($request->check_in_date);
        $checkOutDate = $checkInDate->copy()->addDays($request->durasi);

        // 2. Cek Ketersediaan Kamar
        $kamar = Kamar::findOrFail($request->kamar_id);

        $totalHarga = $kamar->harga * $request->durasi * $request->jumlah_kamar;

        try {
            // Mulai Transaksi Database
            $result = DB::transaction(function () use ($kamar, $request, $checkInDate, $checkOutDate, $totalHarga) {

                // A. Cari User berdasarkan No HP, atau Buat Baru jika belum ada
                $user = User::firstOrCreate(
                    ['email' => $request->no_hp . '@offline.guest'],
                    [
                        'name' => $request->nama_pemesan,
                        'no_hp' => $request->no_hp,
                        'password' => Hash::make('tamu123'), // Password default
                        'role_id' => 2, // Asumsikan 2 = customer
                        'email_verified_at' => now(),
                    ]
                );

                // B. Buat Pesanan (Status langsung Confirmed/Paid)
                $pemesanan = Pemesanan::create([
                    'user_id' => $user->id,
                    'kamar_id' => $request->kamar_id,
                    'tanggal_check_in' => $checkInDate,
                    'tanggal_check_out' => $checkOutDate,
                    'jumlah_kamar' => $request->jumlah_kamar,
                    'total_bayar' => $totalHarga,
                    'status_pemesanan' => 'dikonfirmasi',
                    'catatan' => 'Pemesanan Offline (Walk-in) via Admin',
                ]);

                // C. Buat Record Pembayaran (Langsung Lunas)
                Pembayaran::create([
                    'pemesanan_id' => $pemesanan->id,
                    'jumlah_bayar' => $totalHarga,
                    'bank_tujuan' => 'CASH',
                    'nama_pengirim' => $request->nama_pemesan,
                    'status' => 'verified',
                    'tanggal_bayar' => now(),
                    'bukti_bayar_path' => 'offline_transaction.jpg'
                ]);

                // 3. Cari Unit Kamar Kosong
                $kamarUnits = KamarUnit::where('kamar_id', $request->kamar_id)
                    ->where('status_unit', 'available')
                    ->whereDoesntHave('penempatankamars', function ($q) use ($checkInDate, $checkOutDate) {
                        $q->where(function ($query) use ($checkInDate, $checkOutDate) {
                            $query->whereBetween('check_in_aktual', [$checkInDate, $checkOutDate])
                                ->orWhere('status_penempatan', 'assigned');
                        })
                            ->orWhereHas('detailPemesanan.pemesanan', function ($q2) use ($checkInDate, $checkOutDate) {
                                // 2. Cek Jadwal Online
                                $q2->where('status_pemesanan', '!=', 'batal')
                                    ->where(function ($q3) use ($checkInDate, $checkOutDate) {
                                    $q3->where('tanggal_check_in', '<', $checkOutDate)
                                        ->where('tanggal_check_out', '>', $checkInDate);
                                });
                            });
                    })
                    ->take($request->jumlah_kamar)
                    ->get();

                // Validasi jumlah unit yang didapat
                if ($kamarUnits->count() < $request->jumlah_kamar) {
                    $totalTotal = KamarUnit::where('kamar_id', $request->kamar_id)->count();
                    throw new \Exception("Stok kamar tidak mencukupi. Hanya tersedia " . $kamarUnits->count() . " unit (Total Unit: $totalTotal).");
                }
                // 4. Buat Detail Pemesanan
                $detail = DetailPemesanan::create([
                    'pemesanan_id' => $pemesanan->id,
                    'kamar_id' => $request->kamar_id,
                    'jumlah_kamar' => $request->jumlah_kamar,
                    'harga_per_malam' => $kamar->harga,
                ]);

                foreach ($kamarUnits as $unit) {
                    PenempatanKamar::create([
                        'detail_pemesanan_id' => $detail->id,
                        'kamar_unit_id' => $unit->id,
                        'status_penempatan' => 'assigned',
                        'check_in_aktual' => now(),
                        'check_out_aktual' => null,
                    ]);
                }

                return $pemesanan;
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
        // 1. Validasi: Status pemesanan harus 'dikonfirmasi'
        if ($pemesanan->status_pemesanan !== 'dikonfirmasi') {
            return $this->errorResponse(
                'Hanya pesanan yang sudah dikonfirmasi yang bisa ditandai tidak datang. Status saat ini: ' . $pemesanan->status_pemesanan,
                400
            );
        }

        // 2. Validasi: Cek apakah sudah check-in
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

        // 3. Proses Penandaan Tidak Datang
        try {
            DB::beginTransaction();

            $pemesanan->update([
                'status_pemesanan' => 'tidak_datang',
                'catatan' => 'Ditandai tidak datang oleh owner - Pelanggan tidak datang di tanggal check-in',
                'dibatalkan_oleh' => 'owner',
                'dibatalkan_at' => now(),
            ]);

            // Update status penempatan kamar menjadi 'cancelled'
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

}
