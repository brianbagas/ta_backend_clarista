<?php

namespace App\Http\Controllers;

use App\Models\KamarUnit;
use App\Models\PenempatanKamar;
use App\Models\DetailPemesanan;
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

        $request->validate([
            'detail_pemesanan_id' => 'required|exists:detail_pemesanans,id',
            'kamar_unit_id' => 'required|exists:kamar_units,id',
            'catatan' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {

            // Cek status unit (lock untuk race condition)
            $unit = KamarUnit::where('id', $request->kamar_unit_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($unit->status_unit != 'available') {
                DB::rollBack();
                return $this->errorResponse('Gagal! Unit kamar sedang ' . $unit->status_unit . '. Tidak bisa check-in.', 400);
            }

            // Cek apakah unit sedang dipakai
            $isUnitOccupied = PenempatanKamar::where('kamar_unit_id', $request->kamar_unit_id)
                ->where('status_penempatan', 'assigned')
                ->exists();

            if ($isUnitOccupied) {
                DB::rollBack();
                return $this->errorResponse('Gagal! Unit kamar sedang digunakan tamu lain.', 400);
            }

            // Proses check-in
            $penempatan = PenempatanKamar::where('detail_pemesanan_id', $request->detail_pemesanan_id)
                ->where('kamar_unit_id', $request->kamar_unit_id)
                ->first();

            if ($penempatan) {
                if ($penempatan->status_penempatan == 'assigned') {
                    DB::rollBack();
                    return $this->errorResponse('Gagal! Pesanan ini sudah Check-In sebelumnya.', 400);
                }

                $penempatan->update([
                    'status_penempatan' => 'assigned',
                    'check_in_aktual' => Carbon::now(),
                    'catatan' => $request->catatan,
                ]);
            } else {
                $penempatan = PenempatanKamar::create([
                    'detail_pemesanan_id' => $request->detail_pemesanan_id,
                    'kamar_unit_id' => $request->kamar_unit_id,
                    'status_penempatan' => 'assigned',
                    'check_in_aktual' => Carbon::now(),
                    'check_out_aktual' => null,
                    'catatan' => $request->catatan,
                ]);
            }

            DB::commit();

            return $this->successResponse($penempatan, 'Check-in berhasil dilakukan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }


    public function checkOut($id)
    {
        $penempatan = PenempatanKamar::findOrFail($id);


        if ($penempatan->status_penempatan != 'assigned') {
            return $this->errorResponse('Gagal! Tamu belum check-in atau sudah check-out.', 400);
        }

        DB::beginTransaction();
        try {

            $penempatan->update([
                'status_penempatan' => 'checked_out',
                'check_out_aktual' => Carbon::now(),
            ]);


            $unit = KamarUnit::findOrFail($penempatan->kamar_unit_id);

            $unit->update([
                'status_unit' => 'kotor'
            ]);

            $pemesanan = $penempatan->detailPemesanan->pemesanan;


            $stillOccupied = PenempatanKamar::whereHas('detailPemesanan', function ($q) use ($pemesanan) {
                $q->where('pemesanan_id', $pemesanan->id);
            })
                ->where('status_penempatan', '!=', 'checked_out')
                ->count();


            if ($stillOccupied == 0 && $pemesanan->status_pemesanan == 'dikonfirmasi') {
                $pemesanan->update(['status_pemesanan' => 'selesai']);
            }

            DB::commit();

            return $this->successResponse($penempatan, 'Check-out berhasil. Unit kamar kini berstatus KOTOR.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }

    public function setAvailable($id)
    {


        DB::beginTransaction();
        try {

            $unit = KamarUnit::findOrFail($id);
            if ($unit->status_unit == 'available') {
                DB::rollBack();
                return $this->errorResponse('Unit ini sudah berstatus available.', 400);
            }


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

    public function gantiUnit(Request $request)
    {
        $request->validate([
            'penempatan_id' => 'required|exists:penempatan_kamars,id',
            'new_kamar_unit_id' => 'required|exists:kamar_units,id',
            'old_unit_status' => 'nullable|in:available,kotor,maintenance',
        ]);

        DB::beginTransaction();
        try {
            $penempatan = PenempatanKamar::findOrFail($request->penempatan_id);
            $oldUnitId = $penempatan->kamar_unit_id;


            $newUnit = KamarUnit::findOrFail($request->new_kamar_unit_id);
            $oldUnit = KamarUnit::findOrFail($oldUnitId);

            $detail = $penempatan->detailPemesanan;
            $pemesanan = $detail->pemesanan;
            $checkIn = $pemesanan->tanggal_check_in;
            $checkOut = $pemesanan->tanggal_check_out;

            $isOccupied = PenempatanKamar::where('kamar_unit_id', $newUnit->id)
                ->where('id', '!=', $penempatan->id)
                ->whereHas('detailPemesanan.pemesanan', function ($q) use ($checkIn, $checkOut) {
                    $q->where('status_pemesanan', '!=', 'batal')
                        ->where(function ($query) use ($checkIn, $checkOut) {
                            $query->where('tanggal_check_in', '<', $checkOut)
                                ->where('tanggal_check_out', '>', $checkIn);
                        });
                })->exists();

            if ($isOccupied) {
                return $this->errorResponse('Gagal! Unit pengganti sudah terisi di tanggal tersebut.', 400);
            }


            $penempatan->update([
                'kamar_unit_id' => $newUnit->id,
                'catatan' => $penempatan->catatan . " [Pindah dari Unit {$oldUnit->nomor_unit} ke {$newUnit->nomor_unit}]",
            ]);

            if ($oldUnit->kamar_id != $newUnit->kamar_id) {

                $currentDetail = $penempatan->detailPemesanan;

                if ($currentDetail->jumlah_kamar == 1) {


                    $currentDetail->update([
                        'kamar_id' => $newUnit->kamar_id
                    ]);
                } else {
                    $currentDetail->decrement('jumlah_kamar');

                    // Buat detail baru untuk tipe kamar baru
                    $newDetail = DetailPemesanan::create([
                        'pemesanan_id' => $currentDetail->pemesanan_id,
                        'kamar_id' => $newUnit->kamar_id,
                        'jumlah_kamar' => 1,
                        'harga_per_malam' => $currentDetail->harga_per_malam, // Harga ikut yang lama
                    ]);


                    $penempatan->update([
                        'detail_pemesanan_id' => $newDetail->id
                    ]);
                }
            }


            // Update status unit lama
            if ($request->old_unit_status === 'maintenance') {
                $oldUnit->update(['status_unit' => 'maintenance']);
            }

            DB::commit();

            return $this->successResponse($penempatan, "Berhasil mengganti unit ke {$newUnit->nomor_unit}.");

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }

    public function getAvailableUnits(Request $request)
    {
        $request->validate([
            'kamar_id' => 'required|exists:kamars,id_kamar',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'current_unit_id' => 'nullable'
        ]);

        $checkIn = $request->check_in;
        $checkOut = $request->check_out;

        $units = KamarUnit::where('kamar_id', $request->kamar_id)
            ->whereIn('status_unit', ['available', 'kotor'])
            ->whereDoesntHave('penempatankamars', function ($query) use ($checkIn, $checkOut) {
                $query->whereHas('detailPemesanan.pemesanan', function ($q) {
                    $q->where('status_pemesanan', '!=', 'batal');
                })
                    ->whereHas('detailPemesanan.pemesanan', function ($q) use ($checkIn, $checkOut) {

                        $q->where(function ($sub) use ($checkIn, $checkOut) {
                            $sub->where('tanggal_check_in', '<', $checkOut)
                                ->where('tanggal_check_out', '>', $checkIn);
                        });
                    })
                    ->where('status_penempatan', '!=', 'cancelled')
                    ->where('status_penempatan', '!=', 'checked_out');
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $units
        ]);
    }
}
