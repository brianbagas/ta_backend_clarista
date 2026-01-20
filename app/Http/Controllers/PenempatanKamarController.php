<?php

namespace App\Http\Controllers;

use App\Models\KamarUnit;
use App\Models\PenempatanKamar;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PenempatanKamarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(PenempatanKamar $PenempatanKamar)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PenempatanKamar $PenempatanKamar)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PenempatanKamar $PenempatanKamar)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PenempatanKamar $PenempatanKamar)
    {
        //
    }

        public function checkIn(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'detail_pemesanan_id' => 'required|exists:detail_pemesanans,id',
            'kamar_unit_id'       => 'required|exists:KamarUnit,id',
        ]);

        // 1. Cek apakah Detail Pesanan ini SUDAH check-in (status 'assigned')?
    $isAlreadyCheckIn = PenempatanKamar::where('detail_pemesanan_id', $request->detail_pemesanan_id)
                                       ->where('status_penempatan', 'assigned')
                                       ->exists();

    if ($isAlreadyCheckIn) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal! Pesanan ini sudah Check-In sebelumnya (Data Duplikat).'
        ], 400);
    }

    // 2. Cek apakah Unit Kamar ini SEDANG DIPAKAI orang lain?
    $isUnitOccupied = PenempatanKamar::where('kamar_unit_id', $request->kamar_unit_id)
                                     ->where('status_penempatan', 'assigned')
                                     ->exists();

    if ($isUnitOccupied) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal! Unit kamar ini sedang terisi (Occupied).'
        ], 400);
    }

        DB::beginTransaction(); // Mulai Transaksi Database
        try {
            // 2. Cek apakah unit sedang maintenance? (Opsional, untuk keamanan)
            $unit = KamarUnit::findOrFail($request->kamar_unit_id);
            if ($unit->status_unit != 'available') {
                return response()->json(['message' => 'Gagal! Unit kamar sedang maintenance/kotor.'], 400);
            }

            // 3. Simpan Data ke Tabel PenempatanKamars
            $penempatan = PenempatanKamar::create([
                'detail_pemesanan_id' => $request->detail_pemesanan_id,
                'kamar_unit_id'       => $request->kamar_unit_id,
                'status_penempatan'   => 'assigned', // Status aktif
                'check_in_aktual'     => Carbon::now(), // Waktu saat tombol ditekan
                'check_out_aktual'    => null,
            ]);

            // Catatan: status_unit di tabel KamarUnit TETAP 'available' 
            // karena sistem Anda menggunakan tabel penempatan untuk cek availability.

            DB::commit(); // Simpan perubahan

            return response()->json([
                'success' => true,
                'message' => 'Check-in berhasil dilakukan.',
                'data'    => $penempatan
            ]);

        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan jika error
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    public function checkOut($id)
    {
        // $id adalah ID dari tabel PenempatanKamars yang mau di-checkout
// 1. Cari Data Penempatan
    // Gunakan findOrFail agar otomatis 404 jika ID ngawur
    $penempatan = PenempatanKamar::findOrFail($id);

    // --- VALIDASI TAMBAHAN (MENCEGAH DOUBLE CHECK-OUT) ---
    
    // Cek apakah statusnya memang 'assigned' (sedang menginap)?
    // Jika statusnya sudah 'checked_out', tolak request ini.
    if ($penempatan->status_penempatan == 'checked_out') {
        return response()->json([
            'success' => false,
            'message' => 'Gagal! Tamu ini sudah Check-Out sebelumnya.'
        ], 400);
    }
        DB::beginTransaction();
        try {
            // 1. Cari Data Penempatan
            $penempatan = PenempatanKamar::findOrFail($id);

            // Pastikan belum di-checkout sebelumnya
            if ($penempatan->status_penempatan == 'checked_out') {
                return response()->json(['message' => 'Tamu ini sudah check-out sebelumnya.'], 400);
            }

            // 2. Update Tabel PenempatanKamars (Tamu Keluar)
            $penempatan->update([
                'status_penempatan' => 'checked_out', // Status riwayat
                'check_out_aktual'  => Carbon::now(), // Catat waktu keluar
            ]);

            // 3. Update Tabel KamarUnit (Fisik Kamar jadi Maintenance)
            $unit = KamarUnit::findOrFail($penempatan->kamar_unit_id);
            
            // Ubah status jadi 'maintenance' (atau 'cleaning')
            // Ini akan membuat kamar HILANG dari pencarian pelanggan
            $unit->update([
                'status_unit' => 'maintenance' 
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Check-out berhasil. Unit kamar kini berstatus MAINTENANCE.',
                'data'    => $penempatan
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

 public function setAvailable($id)
{
    // $id adalah ID dari tabel KamarUnit (Primary Key), BUKAN nomor kamar (101, 102)

    DB::beginTransaction();
    try {
        // 1. Cari Unit Kamar
        $unit = KamarUnit::findOrFail($id);

        // 2. Cek status saat ini (Opsional, untuk validasi)
        // Kita hanya mau ubah jika statusnya 'maintenance' atau 'cleaning'
        if ($unit->status_unit == 'available') {
            return response()->json(['message' => 'Unit ini sudah berstatus available.'], 400);
        }

        // 3. Update Status Menjadi Available
        $unit->update([
            'status_unit' => 'available'
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Status unit berhasil diubah menjadi AVAILABLE. Kamar siap dijual.',
            'data'    => $unit
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
}

}
