<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Traits\ApiResponseTrait;

class PromoController extends Controller
{
    use ApiResponseTrait;

    public function latest()
    {
        // Mengambil 3 promo terbaru
        $latestPromos = Promo::where('is_active', true)
            ->whereDate('berlaku_mulai', '<=', now())
            ->whereDate('berlaku_selesai', '>=', now())
            ->latest()
            ->take(3)
            ->get();

        return $this->successResponse($latestPromos, 'Detail Promo berhasil ditampilkan.');
    }
    public function index()
    {
        $allPromos = Promo::where('is_active', true)
            ->whereDate('berlaku_mulai', '<=', now())
            ->whereDate('berlaku_selesai', '>=', now())
            ->get();

        return $this->successResponse($allPromos, 'Detail Promo berhasil ditampilkan.');
    }
    public function indexforOwner()
    {
        $allPromos = Promo::all();
        return $this->successResponse($allPromos, 'Detail Promo berhasil ditampilkan.');
    }
    public function indexby($promo_id)
    {
        $promo = Promo::find($promo_id);
        if (!$promo) {
            return $this->errorResponse('Promo tidak ditemukan', 404);
        }
        return $this->successResponse($promo, 'Detail Promo berhasil ditampilkan.');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nama_promo' => 'required|string|max:255',
            'kode_promo' => 'required|string|unique:promos|max:50',
            'deskripsi' => 'nullable|string',
            'tipe_diskon' => ['required', Rule::in(['persen', 'nominal'])],
            'nilai_diskon' => 'required|numeric|min:0',
            'kuota' => 'nullable|integer|min:0',
            'min_transaksi' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'berlaku_mulai' => 'required|date',
            'berlaku_selesai' => 'required|date|after_or_equal:berlaku_mulai',
        ]);

        $promo = Promo::create($validatedData);

        return $this->successResponse($promo, 'Promo berhasil dibuat.', 201);
    }

    public function show(Promo $promo)
    {
        return $this->successResponse($promo, 'Detail promo berhasil ditampilkan.');
    }

    public function update(Request $request, Promo $promo)
    {
        $validatedData = $request->validate([
            'nama_promo' => 'required|string|max:255',
            'kode_promo' => ['required', 'string', 'max:50', Rule::unique('promos')->ignore($promo->id)],
            'deskripsi' => 'nullable|string',
            'tipe_diskon' => ['required', Rule::in(['persen', 'nominal'])],
            'nilai_diskon' => 'required|numeric|min:0',
            'kuota' => 'nullable|integer|min:0',
            'min_transaksi' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'berlaku_mulai' => 'required|date',
            'berlaku_selesai' => 'required|date|after_or_equal:berlaku_mulai',
        ]);

        $promo->update($validatedData);

        return $this->successResponse($promo, 'Promo berhasil diupdate.');
    }

    public function destroy($promo_id)
    {
        // 1. Cari data (Laravel otomatis hanya mencari yang deleted_at nya NULL)
        $promo = Promo::find($promo_id);

        if (!$promo) {
            return $this->errorResponse('Unit tidak ditemukan', 404);
        }
        $promo->delete();

        return $this->successResponse(['data_id' => $promo->id], 'Unit berhasil dihapus sementara (Soft Delete).');
    }

    public function destroyPermanently($id)
    {
        // Kita harus pakai 'withTrashed()' karena data mungkin sudah di-soft delete sebelumnya
        $data = Promo::withTrashed()->find($id);

        if (!$data) {
            return $this->errorResponse('Data tidak ditemukan', 404);
        }

        // Pakai forceDelete()
        $data->forceDelete();

        return $this->successResponse(null, 'Data berhasil dihapus permanen.');
    }

    public function restore($id)
    {
        // Cari data di tong sampah
        $data = Promo::onlyTrashed()->find($id);

        if (!$data) {
            return $this->errorResponse('Data tidak ditemukan di trash', 404);
        }

        // Kembalikan ke meja kerja
        $data->restore();

        return $this->successResponse($data, 'Data berhasil dikembalikan.');
    }

    public function checkPromo(Request $request)
    {
        $request->validate([
            'kode_promo' => 'required|string',
            'total_transaksi' => 'required|numeric'
        ]);

        // 1. Cari Promo berdasarkan kode dan validitas waktu/status
        $promo = Promo::where('kode_promo', $request->kode_promo)
            ->where('is_active', true) // Sesuai kolom boolean
            ->whereDate('berlaku_mulai', '<=', now())
            ->whereDate('berlaku_selesai', '>=', now())
            ->sharedLock()
            ->first();

        // 2. Cek apakah promo ditemukan
        if (!$promo) {
            return $this->errorResponse('Kode promo tidak valid atau sudah kadaluarsa.', 404);
        }

        // 3. Cek Kuota (Jika kuota tidak null)
        if (!is_null($promo->kuota) && $promo->kuota_terpakai >= $promo->kuota) {
            return $this->errorResponse('Kuota promo ini telah habis.', 400);
        }

        // 4. Cek Minimal Transaksi
        if ($request->total_transaksi < $promo->min_transaksi) {
            return $this->errorResponse('Total transaksi minimal untuk promo ini adalah Rp ' . number_format($promo->min_transaksi, 0, ',', '.'), 400);
        }

        // 5. Hitung Nilai Diskon
        $nilaiDiskon = 0;
        if ($promo->tipe_diskon === 'persen') {
            $nilaiDiskon = ($request->total_transaksi * $promo->nilai_diskon) / 100;
        } else {
            // Jika nominal
            $nilaiDiskon = $promo->nilai_diskon;
        }

        $finalDiskon = min($nilaiDiskon, $request->total_transaksi);

        return $this->successResponse([
            'id' => $promo->id,
            'nama_promo' => $promo->nama_promo,
            'kode_promo' => $promo->kode_promo,
            'nilai_potongan' => $finalDiskon,
            'tipe' => $promo->tipe_diskon
        ], 'Promo valid');
    }

}
