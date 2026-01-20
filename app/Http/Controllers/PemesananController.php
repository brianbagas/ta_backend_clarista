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
use Carbon\Carbon;

class PemesananController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    { /** @var \App\Models\User $user */
        $user = Auth::user();

        // Ambil pemesanan milik user, urutkan dari yang terbaru
        // Muat juga relasi detailPemesanans dan kamar di dalamnya
        $pemesanans = $user->pemesanans()
            ->with(['detailPemesanans.kamar'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($pemesanans);
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
    $validated = $request->validate([
        'tanggal_check_in' => 'required|date|after_or_equal:today',
        'tanggal_check_out' => 'required|date|after:tanggal_check_in',
        'kamars' => 'required|array',
        'kamars.*.kamar_id' => 'required|exists:kamars,id_kamar',
        'kamars.*.jumlah_kamar' => 'required|integer|min:1',
        'kode_promo' => 'nullable|string|exists:promos,kode_promo',
    ]);

    DB::beginTransaction();
    try {
        $checkIn = Carbon::parse($validated['tanggal_check_in']);
        $checkOut = Carbon::parse($validated['tanggal_check_out']);
        $durasiMenginap = $checkIn->diffInDays($checkOut);
        $subtotal = 0;   
        $bookingPlan = []; 

        foreach ($validated['kamars'] as $item) {
            $kamar = Kamar::findOrFail($item['kamar_id']);
            
            $occupiedUnitIds = PenempatanKamar::whereHas('detailPemesanan.pemesanan', function($q) use ($checkIn, $checkOut) {
                $q->where('status_pemesanan', '!=', 'batal')
                  ->where(function($query) use ($checkIn, $checkOut) {
                      $query->where('tanggal_check_in', '<', $checkOut)
                            ->where('tanggal_check_out', '>', $checkIn);
                  });
            })->pluck('kamar_unit_id');


            $availableUnits = KamarUnit::where('kamar_id', $kamar->id_kamar)
                ->where('status_unit', 'available') 
                ->whereNotIn('id', $occupiedUnitIds) 
                ->take($item['jumlah_kamar']) 
                ->lockForUpdate()
                ->get();


            if ($availableUnits->count() < $item['jumlah_kamar']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
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
            // Pastikan nama kolom 'tanggal_mulai' & 'tanggal_berakhir' sesuai database Anda
            $promo = Promo::where('kode_promo', $validated['kode_promo'])
              ->where('is_active', true)
              ->where('berlaku_mulai', '<=', now())
                ->where('berlaku_selesai', '>=', now())
                ->first();

            if ($promo) {
                $nilaiDiskon = ($promo->tipe_diskon === 'persen') // Sesuaikan nama kolom jenis/tipe
                    ? ($subtotal * $promo->nilai_diskon) / 100 // Sesuaikan nama kolom nilai
                    : $promo->nilai_diskon;

                $diskonFinal = min($subtotal, $nilaiDiskon);
                $totalBayar = $subtotal - $diskonFinal;
                $promoId = $promo->id;
            }
            // --- LOGIC LOCK KUOTA PROMO ---
    if ($promoId) {
        // Kita gunakan lockForUpdate() atau validasi ulang untuk memastikan
        // saat detik terakhir ini kuota belum diambil orang lain di milidetik yang sama
$affectedRows = Promo::where('id', $promoId)
        ->where(function ($query) {
            $query->whereNull('kuota') // Kondisi 1: Unlimited
                  ->orWhereColumn('kuota_terpakai', '<', 'kuota'); // Kondisi 2: Terbatas tapi masih ada sisa
        })
        ->increment('kuota_terpakai');

        // Jika affectedRows 0, artinya query gagal update karena kuota sudah penuh duluan
if ($affectedRows === 0) {
        // Cek dulu apakah gagalnya karena ID-nya valid tapi penuh?
        // (Optional check agar error message akurat)
        $isFull = Promo::where('id', $promoId)
             ->whereNotNull('kuota')
             ->whereColumn('kuota_terpakai', '>=', 'kuota')
             ->exists();

        DB::rollBack();
        
        $message = $isFull 
            ? 'Maaf, kuota promo baru saja habis digunakan pengguna lain.' 
            : 'Promo tidak valid.';

        return response()->json(['message' => $message], 409);
    }
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

        // B. Detail & Penempatan (Looping dari rencana yang kita buat di atas)
        foreach ($bookingPlan as $plan) {
            // 1. Simpan Detail Pemesanan (Transaksi Barang)
            $detail = DetailPemesanan::create([
                'pemesanan_id' => $pemesanan->id,
                'kamar_id' => $plan['kamar_obj']->id_kamar,
                'jumlah_kamar' => $plan['qty'],
                'harga_per_malam' => $plan['kamar_obj']->harga,
            ]);

            // 2. Simpan Penempatan Kamar (Mapping Unit Fisik)
            // INI YANG HILANG DI KODE LAMA ANDA
            foreach ($plan['units'] as $unit) {
                PenempatanKamar::create([
                    'detail_pemesanan_id' => $detail->id,
                    'kamar_unit_id' => $unit->id,
                    'status_penempatan' => 'assigned', // Status awal
                ]);
            }
        }

        DB::commit();
        return response()->json([
            'message' => 'Booking berhasil dibuat',
            'data' => $pemesanan->load('detailPemesanans.penempatanKamars') // Load relasi biar kelihatan unitnya
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        // Return error biar gampang debugging
        return response()->json([
            'message' => 'Gagal membuat pesanan.', 
            'error' => $e->getMessage()
        ], $e instanceof \Illuminate\Validation\ValidationException ? 422 : 500);
    }
}

    /**
     * Display the specified resource.
     */
public function show(Pemesanan $pemesanan)
{
    if (Auth::id() !== $pemesanan->user_id) {
        return response()->json(['message' => 'Akses ditolak.'], 403);
    }
    // Tambahkan 'promo' ke dalam load()
    return response()->json($pemesanan->load('user', 'detailPemesanans.kamar', 'promo'));
}

        public function indexOwner()
    {
        // Ambil semua pemesanan, muat relasi user, urutkan dari yang terbaru
        $pemesanans = Pemesanan::with('user')
                           ->latest()
                           ->get();

        return response()->json($pemesanans);
    }

public function showForOwner(Pemesanan $pemesanan)
{
    // Tambahkan 'promo' ke dalam load()
    return response()->json($pemesanan->load('user', 'detailPemesanans.kamar', 'promo'));
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

    public function storeOffline(Request $request)
    {
    // 1. Validasi Input
    $request->validate([
        'nama_pemesan' => 'required|string',
        'no_hp'        => 'required|string', // Kunci utama identitas tamu offline
        'kamar_id'     => 'required|exists:kamars,id_kamar',
        'check_in'     => 'required|date|after_or_equal:today',
        'durasi'       => 'required|integer|min:1',
        'jumlah_kamar' => 'required|integer|min:1',
    ]);

    // Hitung tanggal check out
    $checkInDate = Carbon::parse($request->check_in);
    $checkOutDate = $checkInDate->copy()->addDays($request->durasi);

    // 2. Cek Ketersediaan Kamar (PENTING!)
    // Logika ini harus memastikan tidak ada booking lain di tanggal yang sama
    // Saya asumsikan kamu sudah punya logic cek ketersediaan, ini contoh sederhananya:
    $kamar = Kamar::findOrFail($request->kamar_id);
    
    // (Opsional: Tambahkan logika cek kuota kamar di sini)
    // if ($kamar->stok < $request->jumlah_kamar) return error...

    $totalHarga = $kamar->harga * $request->durasi * $request->jumlah_kamar;

    try {
        // Mulai Transaksi Database
        $result = DB::transaction(function () use ($kamar, $request, $checkInDate, $checkOutDate, $totalHarga) {
            
            // A. Cari User berdasarkan No HP, atau Buat Baru jika belum ada
            // Ini agar tamu walk-in tetap tercatat di database user
            $user = User::firstOrCreate(
                ['email' => $request->no_hp . '@offline.guest'], // Dummy email unik
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
                'status_pemesanan' => 'confirmed', // Langsung dikonfirmasi
                'catatan' => 'Pemesanan Offline (Walk-in) via Admin',
            ]);

            // C. Buat Record Pembayaran (Langsung Lunas)
            Pembayaran::create([
                'pemesanan_id' => $pemesanan->id,
                'jumlah_bayar' => $totalHarga,
                'bank_tujuan' => 'CASH', // Atau 'EDC/Debit'
                'nama_pengirim' => $request->nama_pemesan,
                'status' => 'verified', // Admin yang input, jadi otomatis verified
                'tanggal_bayar' => now(),
                'bukti_bayar_path' => 'offline_transaction.jpg' // Dummy placeholder
            ]);

            // 3. Cari Unit Kamar Kosong (PERBAIKAN: Mengambil Banyak Unit sekaligus)
            $kamarUnits = KamarUnit::where('kamar_id', $request->kamar_id)
                ->whereDoesntHave('PenempatanKamar', function($q) use ($checkInDate, $checkOutDate) {
                    $q->where(function($query) use ($checkInDate, $checkOutDate) {
                        $query->whereBetween('check_in_aktual', [$checkInDate, $checkOutDate])
                            ->orWhere('status_penempatan', 'check_in');
                    });
                })
                ->take($request->jumlah_kamar) // AMBIL SEJUMLAH YANG DIMINTA
                ->get();

            // Validasi jumlah unit yang didapat
            if ($kamarUnits->count() < $request->jumlah_kamar) {
                throw new \Exception("Stok kamar tidak mencukupi. Hanya tersedia " . $kamarUnits->count() . " unit.");
            }
            // 4. Buat Detail Pemesanan
            $detail = DetailPemesanan::create([
                'pemesanan_id' => $pemesanan->id,
                'kamar_id' => $request->kamar_id,
                'jumlah_kamar' => $request->jumlah_kamar, // Pastikan kolom ini ada
                'harga_per_malam' => $kamar->harga, // Simpan harga satuan, bukan total
            ]);

            foreach ($kamarUnits as $unit) {
                PenempatanKamar::create([
                    'detail_pemesanan_id' => $detail->id,
                    'kamar_unit_id' => $unit->id,
                    'status_penempatan' => 'checked_in',
                    'check_in_aktual' => now(),
                    'check_out_aktual' => null,
                ]);
            }

            return $pemesanan;
        });

        return response()->json([
            'message' => 'Pemesanan Offline berhasil dibuat',
            'data' => $result
        ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat pemesanan offline',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
