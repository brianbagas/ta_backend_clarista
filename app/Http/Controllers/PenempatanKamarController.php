<?php

namespace App\Http\Controllers;

use App\Models\KamarUnit;
use App\Models\PenempatanKamar;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

class PenempatanKamarController extends Controller
{
    use ApiResponseTrait;

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
            'kamar_unit_id' => 'required|exists:kamar_units,id',
        ]);

        // 2. Cek apakah Unit Kamar ini SEDANG DIPAKAI orang lain
        $isUnitOccupied = PenempatanKamar::where('kamar_unit_id', $request->kamar_unit_id)
            ->where('status_penempatan', 'assigned')
            ->exists();

        DB::beginTransaction(); // Mulai Transaksi Database
        try {
            // A. Cari Existing Penempatan (dari Booking)
            $penempatan = PenempatanKamar::where('detail_pemesanan_id', $request->detail_pemesanan_id)
                ->where('kamar_unit_id', $request->kamar_unit_id)
                ->first();

            if ($penempatan) {
                // UPDATE
                if ($penempatan->status_penempatan == 'assigned') {
                    DB::rollBack();
                    return $this->errorResponse('Gagal! Pesanan ini sudah Check-In sebelumnya.', 400);
                }

                $penempatan->update([
                    'status_penempatan' => 'assigned',
                    'check_in_aktual' => Carbon::now(),
                ]);
            } else {
                $penempatan = PenempatanKamar::create([
                    'detail_pemesanan_id' => $request->detail_pemesanan_id,
                    'kamar_unit_id' => $request->kamar_unit_id,
                    'status_penempatan' => 'assigned', // Status aktif
                    'check_in_aktual' => Carbon::now(), // Waktu saat tombol ditekan
                    'check_out_aktual' => null,
                ]);
            }

            // 3. Cek apakah unit sedang maintenance?
            $unit = KamarUnit::findOrFail($request->kamar_unit_id);
            if ($unit->status_unit != 'available') {
                // Warning: jika unit maintenance tapi kita paksa check-in?
                // Biasanya kita tolak.
                DB::rollBack();
                return $this->errorResponse('Gagal! Unit kamar sedang maintenance/kotor.', 400);
            }

            DB::commit(); // Simpan perubahan

            return $this->successResponse($penempatan, 'Check-in berhasil dilakukan.');

        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan jika error
            return $this->errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }


    public function checkOut($id)
    {
        // 1. Cari Data Penempatan
        $penempatan = PenempatanKamar::findOrFail($id);

        // --- VALIDASI TAMBAHAN (MENCEGAH DOUBLE CHECK-OUT) ---
        if ($penempatan->status_penempatan != 'assigned') {
            return $this->errorResponse('Gagal! Tamu belum check-in atau sudah check-out.', 400);
        }

        DB::beginTransaction();
        try {
            // 2. Update Tabel PenempatanKamars (Tamu Keluar)
            $penempatan->update([
                'status_penempatan' => 'checked_out', // Status riwayat
                'check_out_aktual' => Carbon::now(), // Catat waktu keluar
            ]);

            // 3. Update Tabel KamarUnit (Fisik Kamar jadi Maintenance)
            $unit = KamarUnit::findOrFail($penempatan->kamar_unit_id);

            // Ubah status jadi 'maintenance' (atau 'cleaning')
            // Ini akan membuat kamar HILANG dari pencarian pelanggan
            $unit->update([
                'status_unit' => 'maintenance'
            ]);

            $pemesanan = $penempatan->detailPemesanan->pemesanan;

            // Cek apakah masih ada unit yang belum check-out untuk pemesanan ini
            $stillOccupied = PenempatanKamar::whereHas('detailPemesanan', function ($q) use ($pemesanan) {
                $q->where('pemesanan_id', $pemesanan->id);
            })
                ->where('status_penempatan', '!=', 'checked_out')
                ->count();

            // Jika semua unit sudah check-out, update status pemesanan
            if ($stillOccupied == 0 && $pemesanan->status_pemesanan == 'dikonfirmasi') {
                $pemesanan->update(['status_pemesanan' => 'selesai']);
            }

            DB::commit();

            return $this->successResponse($penempatan, 'Check-out berhasil. Unit kamar kini berstatus MAINTENANCE.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }

    public function setAvailable($id)
    {
        // $id adalah ID dari tabel KamarUnit

        DB::beginTransaction();
        try {
            // 1. Cari Unit Kamar
            $unit = KamarUnit::findOrFail($id);

            // 2. Cek status saat ini
            // Kita hanya mau ubah jika statusnya 'maintenance' atau 'cleaning'
            if ($unit->status_unit == 'available') {
                DB::rollBack();
                return $this->errorResponse('Unit ini sudah berstatus available.', 400);
            }

            // 3. Update Status Menjadi Available
            $unit->update([
                'status_unit' => 'available'
            ]);

            DB::commit();

            return $this->successResponse($unit, 'Status unit berhasil diubah menjadi AVAILABLE. Kamar siap dijual.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }

}
