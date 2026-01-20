<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromoController extends Controller
{
    public function latest()
{
    // Mengambil 3 promo terbaru berdasarkan tanggal dibuat
    $latestPromos = Promo::latest()->take(3)->get();
    
        return response()->json([
            'success' => true,
            'message' => 'Detail Promo berhasil ditampilkan.',
            'data' => $latestPromos,
        ], 200);
}
    public function index()
    {
        $allPromos = Promo::all();
        return response()->json([
                'success' => true,
                'message' => 'Detail Promo berhasil ditampilkan.',
                'data' => $allPromos,
        ]);
    }
        public function indexby($promo_id)
    {
    $promo = Promo::find($promo_id);
        return response()->json([
                'success' => true,
                'message' => 'Detail Promo berhasil ditampilkan.',
                'data' => $promo,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nama_promo' => 'required|string|max:255',
            'kode_promo' => 'required|string|unique:promos|max:50',
            'deskripsi' => 'nullable|string',
            'tipe_diskon' => ['required', Rule::in(['persen', 'nominal'])],
            'nilai_diskon' => 'required|numeric|min:0',
            'berlaku_mulai' => 'required|date',
            'berlaku_selesai' => 'required|date|after_or_equal:berlaku_mulai',
        ]);

        $promo = Promo::create($validatedData);

        return response()->json($promo, 201);
    }

    public function show(Promo $promo)
    {
        return response()->json($promo);
    }

    public function update(Request $request, Promo $promo)
    {
        $validatedData = $request->validate([
            'nama_promo' => 'required|string|max:255',
            'kode_promo' => ['required', 'string', 'max:50', Rule::unique('promos')->ignore($promo->id)],
            'deskripsi' => 'nullable|string',
            'tipe_diskon' => ['required', Rule::in(['persen', 'nominal'])],
            'nilai_diskon' => 'required|numeric|min:0',
            'berlaku_mulai' => 'required|date',
            'berlaku_selesai' => 'required|date|after_or_equal:berlaku_mulai',
        ]);

        $promo->update($validatedData);

        return response()->json($promo);
    }

    public function destroy($promo_id)
    {
        // 1. Cari data (Laravel otomatis hanya mencari yang deleted_at nya NULL)
    $promo = Promo::find($promo_id);

    if (!$promo) {
        return response()->json([
            'status' => 'error',
            'message' => 'Unit tidak ditemukan (Mungkin sudah dihapus?)'
        ], 404);
    }
        $promo->delete();

        return response()->json([
        'status' => 'success',
        'message' => 'Unit berhasil dihapus sementara (Soft Delete).',
        'data_id' => $promo->id
    ], 200);
    }

    public function destroyPermanently($id)
    {
        // Kita harus pakai 'withTrashed()' karena data mungkin sudah di-soft delete sebelumnya
        $data = Promo::withTrashed()->find($id);

        // INI BEDANYA: Pakai forceDelete()
        $data->forceDelete(); 

        return back()->with('success', 'Data musnah selamanya');
    }

    public function restore($id)
{
    // Cari data di tong sampah
    $data = Promo::onlyTrashed()->find($id);

    // Kembalikan ke meja kerja
    $data->restore();

    return back()->with('success', 'Data berhasil dikembalikan');
}
        
    public function validatePromo(Request $request)
    {
        $validated = $request->validate([
            'kode_promo' => 'required|string|exists:promos,kode_promo',
        ]);

        $promo = Promo::where('kode_promo', $validated['kode_promo'])
            ->where('berlaku_mulai', '<=', now())
            ->where('berlaku_selesai', '>=', now())
            ->first();

        if (!$promo) {
            return response()->json(['message' => 'Kode promo tidak valid atau sudah kedaluwarsa.'], 404);
        }

        return response()->json($promo);
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
        ->whereDate('berlaku_mulai', '<=', now()) // Sesuai kolom date
        ->whereDate('berlaku_selesai', '>=', now()) // Sesuai kolom date
        ->first();

    // 2. Cek apakah promo ditemukan
    if (!$promo) {
        return response()->json(['message' => 'Kode promo tidak valid atau sudah kadaluarsa.'], 404);
    }

    // 3. Cek Kuota (Jika kuota tidak null)
    if (!is_null($promo->kuota) && $promo->kuota_terpakai >= $promo->kuota) {
        return response()->json(['message' => 'Kuota promo ini telah habis.'], 400);
    }

    // 4. Cek Minimal Transaksi
    if ($request->total_transaksi < $promo->min_transaksi) {
        return response()->json([
            'message' => 'Total transaksi minimal untuk promo ini adalah Rp ' . number_format($promo->min_transaksi, 0, ',', '.')
        ], 400);
    }

    // 5. Hitung Nilai Diskon
    $nilaiDiskon = 0;
    if ($promo->tipe_diskon === 'persen') {
        $nilaiDiskon = ($request->total_transaksi * $promo->nilai_diskon) / 100;
    } else {
        // Jika nominal
        $nilaiDiskon = $promo->nilai_diskon;
    }

    // Pastikan diskon tidak melebihi total harga (agar tidak minus)
    $finalDiskon = min($nilaiDiskon, $request->total_transaksi);

    return response()->json([
        'status' => 'valid',
        'data' => [
            'id' => $promo->id,
            'nama_promo' => $promo->nama_promo,
            'kode_promo' => $promo->kode_promo,
            'nilai_potongan' => $finalDiskon,
            'tipe' => $promo->tipe_diskon
        ]
    ]);
}

}

