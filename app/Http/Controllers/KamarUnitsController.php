<?php

namespace App\Http\Controllers;

use App\Models\KamarUnit;
use Illuminate\Http\Request;

class KamarUnitsController extends Controller
{
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

        return response()->json([
            'message' => 'List unit kamar retrieved successfully',
            'data' => $units
        ]);
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
            'status_unit' => 'required|in:available,occupied,maintenance',
        ]);

        $kamarUnit->update([
            'status_unit' => $request->status_unit
        ]);

        return response()->json([
            'message' => 'Status unit berhasil diperbarui',
            'data' => $kamarUnit
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KamarUnit $kamarUnit)
    {
        $kamarUnit->delete();

        return response()->json([
            'message' => 'Unit kamar berhasil dihapus'
        ]);
    }
}
