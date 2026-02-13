<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;

use App\Traits\ApiResponseTrait;

class BankAccountController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource for owner (include verified status etc if needed, but simple list for now)
     */
    public function indexForOwner()
    {
        $banks = BankAccount::orderBy('created_at', 'desc')->get();
        return $this->successResponse($banks, 'Data bank berhasil diambil');
    }

    /**
     * Display a listing of the resource for public.
     */
    public function index()
    {
        $banks = BankAccount::where('is_active', true)->get();
        return $this->successResponse($banks, 'Data bank berhasil diambil');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_bank' => 'required|string|max:50',
            'nomor_rekening' => 'required|string|max:50',
            'atas_nama' => 'required|string|max:100',
            'is_active' => 'boolean'
        ]);

        $bank = BankAccount::create($validated);
        return $this->successResponse($bank, 'Rekening bank berhasil ditambahkan', 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BankAccount $bankAccount)
    {
        $validated = $request->validate([
            'nama_bank' => 'required|string|max:50',
            'nomor_rekening' => 'required|string|max:50',
            'atas_nama' => 'required|string|max:100',
            'is_active' => 'boolean'
        ]);

        $bankAccount->update($validated);
        return $this->successResponse($bankAccount, 'Rekening bank berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BankAccount $bankAccount)
    {
        $bankAccount->delete(); // Soft delete
        return $this->successResponse(null, 'Rekening bank berhasil dihapus (soft delete)');
    }

    /**
     * Get soft-deleted bank accounts.
     */
    public function trashed()
    {
        $banks = BankAccount::onlyTrashed()
            ->latest('deleted_at')
            ->get();

        return $this->successResponse($banks, 'Data rekening bank terhapus berhasil diambil');
    }

    /**
     * Restore soft-deleted bank account.
     */
    public function restore($id)
    {
        $bank = BankAccount::onlyTrashed()->findOrFail($id);

        $bank->restore();

        return $this->successResponse($bank, 'Rekening bank berhasil dikembalikan (restore).');
    }

    /**
     * Force delete bank account.
     */
    public function forceDelete($id)
    {
        $bank = BankAccount::onlyTrashed()->findOrFail($id);

        $bank->forceDelete();

        return $this->successResponse(null, 'Rekening bank berhasil dihapus permanen.');
    }
}
