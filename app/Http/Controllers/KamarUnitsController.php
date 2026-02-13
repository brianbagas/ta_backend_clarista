<?php

namespace App\Http\Controllers;

use App\Models\KamarUnit;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

class KamarUnitsController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = KamarUnit::query();

        if ($request->filled('kamar_id')) {
            $query->where('kamar_id', $request->kamar_id);
        }

        $units = $query->orderBy('nomor_unit', 'asc')->get();

        return $this->successResponse($units, 'List unit kamar retrieved successfully');
    }

    public function indexdirty()
    {
        $units = KamarUnit::with('kamar')
            ->where('status_unit', 'kotor')
            ->orderBy('updated_at', 'desc')
            ->get();

        return $this->successResponse($units, 'List kamar kotor berhasil diambil');
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
        $request->validate([
            'kamar_id' => 'required|exists:kamars,id_kamar',
            'nomor_unit' => 'required|string|max:50',
            'status_unit' => 'required|in:available,unavailable,kotor,maintenance',
        ]);

        $unit = KamarUnit::create([
            'kamar_id' => $request->kamar_id,
            'nomor_unit' => $request->nomor_unit,
            'status_unit' => $request->status_unit,
        ]);

        return $this->successResponse($unit, 'Unit kamar berhasil ditambahkan', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(KamarUnit $kamar_units)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(KamarUnit $kamar_units)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, KamarUnit $kamarUnit)
    {
        $request->validate([
            'status_unit' => 'required|in:available,unavailable,kotor,maintenance',
        ]);

        $kamarUnit->update([
            'status_unit' => $request->status_unit
        ]);

        return $this->successResponse($kamarUnit, 'Status unit berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KamarUnit $kamarUnit)
    {
        $kamarUnit->delete();

        return $this->successResponse(null, 'Unit kamar berhasil dihapus');
    }

    /**
     * Get soft-deleted kamar units.
     */
    public function trashed()
    {
        $units = KamarUnit::onlyTrashed()
            ->with([
                'kamar' => function ($query) {
                    $query->withTrashed();
                }
            ])
            ->latest('deleted_at')
            ->get();

        return $this->successResponse($units, 'Data unit kamar terhapus berhasil diambil');
    }

    /**
     * Restore soft-deleted kamar unit.
     */
    public function restore($id)
    {
        $unit = KamarUnit::onlyTrashed()->findOrFail($id);

        // Check if parent Kamar is also deleted? If so, maybe warn or fail?
        // For now, allow restore. If parent is deleted, relation will return null/empty in simple queries.

        $unit->restore();

        return $this->successResponse($unit, 'Unit kamar berhasil dikembalikan (restore).');
    }

    /**
     * Force delete kamar unit.
     */
    public function forceDelete($id)
    {
        $unit = KamarUnit::onlyTrashed()->findOrFail($id);

        // Safety Check: Check if this unit is used in any PenempatanKamar
        // We use withTrashed() on PenempatanKamar just in case
        $isUsed = \App\Models\PenempatanKamar::withTrashed()->where('kamar_unit_id', $id)->exists();

        if ($isUsed) {
            return $this->errorResponse(
                'Unit kamar tidak dapat dihapus permanen karena ada riwayat penempatan (check-in/out) yang terkait.',
                409
            );
        }

        $unit->forceDelete();

        return $this->successResponse(null, 'Unit kamar berhasil dihapus permanen.');
    }
}
