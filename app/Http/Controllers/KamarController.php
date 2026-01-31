<?php

namespace App\Http\Controllers;
use App\Models\DetailPemesanan;
use App\Models\Kamar;
use App\Models\KamarUnit;
use App\Models\KamarImage;
use App\Models\PenempatanKamar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Traits\ApiResponseTrait;

class KamarController extends Controller
{
    use ApiResponseTrait;

    /**
     * Menampilkan semua data kamar.
     * GET /api/kamar
     */
    public function index()
    {
        // Mengambil semua data kamar dari database
        $kamars = Kamar::with('images')->get();

        // Mengembalikan data sebagai respons JSON
        return $this->successResponse($kamars, 'Daftar semua kamar berhasil ditampilkan.');
    }
    // Di method index
    public function indexwithImages()
    {
        // Kita gunakan 'with' untuk mengikutsertakan data gambar
        $kamars = Kamar::with('images')->get();
        return $this->successResponse($kamars, 'Data berhasil diambil');
    }

    // Di method show
    public function showwithImages(Kamar $kamar)
    {
        $kamar->load('images');
        return $this->successResponse($kamar, 'Detail kamar ditemukan');
    }

    /**
     * Menyimpan data kamar baru.
     * POST /api/kamar
     */
    public function addKamar(Request $request)
    {
        // 1. Validasi
        $validator = Validator::make($request->all(), [
            'tipe_kamar' => 'required|string|max:50',
            'harga' => 'required|numeric|min:0',
            'deskripsi' => 'nullable|string',
            'jumlah_total' => 'required|integer|min:1',
            'gambar_kamar' => 'nullable|array',
            'gambar_kamar.*' => 'image|mimes:jpg,png,jpeg|max:2048'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal', 422, $validator->errors());
        }

        // Mulai Transaksi Database
        DB::beginTransaction();

        try {
            // 2. Buat Data Kamar
            $kamar = Kamar::create([
                'tipe_kamar' => $request->tipe_kamar,
                'harga' => $request->harga,
                'deskripsi' => $request->deskripsi,
                'jumlah_total' => $request->jumlah_total,
                'status_ketersediaan' => true,
            ]);

            // 3. Handle Upload Gambar (Multiple)
            if ($request->hasFile('gambar_kamar')) {
                foreach ($request->file('gambar_kamar') as $file) {
                    $pathRaw = $file->store('kamars', 'public');
                    $fullPath = 'storage/' . $pathRaw;

                    KamarImage::create([
                        'kamar_id' => $kamar->id_kamar,
                        'image_path' => $fullPath,
                    ]);
                }
            }

            // 4. Generate Kamar Units
            // 4. Generate Kamar Units
            $prefixAngka = $kamar->id_kamar * 100;

            for ($i = 1; $i <= $request->jumlah_total; $i++) {
                $nomorKamar = $prefixAngka + $i;
                $namaUnit = (string) $nomorKamar;

                KamarUnit::create([
                    'kamar_id' => $kamar->id_kamar,
                    'nomor_unit' => $namaUnit,
                    'status_unit' => 'available',
                ]);
            }

            DB::commit();

            return $this->successResponse($kamar->load('kamarUnits', 'images'), 'Tipe kamar dan ' . $request->jumlah_total . ' unit berhasil dibuat.', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal menyimpan data: ' . $e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        // Validasi input dari request
        $validator = Validator::make($request->all(), [
            'tipe_kamar' => 'required|string|max:50',
            'harga' => 'required|numeric',
            'deskripsi' => 'nullable|string',
            'status_ketersediaan' => 'nullable|boolean',
            'gambar_kamar' => 'nullable|image|mimes:jpg,png,jpeg|max:2048'
        ]);

        // Jika validasi gagal, kembalikan pesan error
        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal', 422, $validator->errors());
        }

        if ($request->hasFile('gambar_kamar')) {
            $path = $request->file('gambar_kamar')->store('public/kamars');
            $validatedData['image_path'] = $path;
        }

        // Membuat data kamar baru
        $kamar = Kamar::create($request->all());

        // Mengembalikan respons sukses beserta data yang baru dibuat
        return $this->successResponse($kamar, 'Data kamar berhasil ditambahkan.', 201);
    }

    /**
     * Menampilkan satu data kamar spesifik.
     * GET /api/kamar/{id}
     */
    public function show(Kamar $kamar)
    {
        // Mengembalikan data kamar yang ditemukan sebagai respons JSON
        $kamar->load('images');
        return $this->successResponse($kamar, 'Detail kamar berhasil ditampilkan.');
    }

    /**
     * Mengupdate data kamar yang sudah ada.
     * PUT /api/kamar/{id}
     */
    public function update(Request $request, Kamar $kamar)
    {
        // Validasi input dari request
        $validator = Validator::make($request->all(), [
            'tipe_kamar' => 'required|string|max:50',
            'harga' => 'required|numeric',
            'deskripsi' => 'nullable|string',
            'status_ketersediaan' => 'nullable|boolean',
            'gambar_kamar' => 'nullable|array',
            'gambar_kamar.*' => 'image|mimes:jpg,png,jpeg|max:2048'
        ]);

        // Jika validasi gagal, kembalikan pesan error
        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal', 422, $validator->errors());
        }

        // Melakukan update pada data kamar
        $kamar->update($request->except(['gambar_kamar', '_method', 'deleted_images']));

        // 0. Handle Deletion (Deferred)
        if ($request->has('deleted_images')) {
            $idsToDelete = $request->deleted_images;
            if (!is_array($idsToDelete)) {
                $idsToDelete = [$idsToDelete];
            }

            foreach ($idsToDelete as $id) {
                $img = KamarImage::find($id);
                // Pastikan gambar milik kamar ini
                if ($img && $img->kamar_id == $kamar->id_kamar) {
                    // Hapus file
                    $relativePath = str_replace('storage/', '', $img->image_path);
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($relativePath)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($relativePath);
                    }
                    // Hapus record
                    $img->delete();
                }
            }
        }

        // Handle Image Upload if exists
        // Handle Image Upload if exists (APPEND Only)
        if ($request->hasFile('gambar_kamar')) {
            try {
                // KITA KOMENTARI BAGIAN DELETE AGAR GAMBAR BARU DITAMBAHKAN (APPEND)
                // $kamar->images()->delete();

                // 2. Upload Baru (Multiple Loop)
                foreach ($request->file('gambar_kamar') as $file) {
                    $path = $file->store('kamars', 'public');
                    $fullPath = 'storage/' . $path;

                    // 3. Simpan ke KamarImage
                    KamarImage::create([
                        'kamar_id' => $kamar->id_kamar,
                        'image_path' => $fullPath,
                    ]);
                }

            } catch (\Exception $e) {
                return $this->errorResponse('Gagal mengupload gambar: ' . $e->getMessage(), 500);
            }
        }

        // Mengembalikan respons sukses beserta data yang telah diupdate
        return $this->successResponse($kamar, 'Data kamar berhasil diupdate.');
    }

    /**
     * Menghapus data kamar.
     * DELETE /api/kamar/{id}
     */
    public function destroy(Kamar $kamar)
    {
        // Menghapus data kamar dari database
        try {
            $kamar->delete();
            return $this->successResponse(null, 'Data kamar berhasil dihapus.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data kamar: ' . $e->getMessage());
        }
    }

    public function cekKetersediaan(Request $request)
    {
        // 1. Validasi Input
        $validator = Validator::make($request->all(), [
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal', 422, $validator->errors());
        }

        $checkIn = $request->check_in;
        $checkOut = $request->check_out;

        // 2. Ambil Data Kamar
        // Tambahkan where('status_ketersediaan', 1) agar kamar yang dinonaktifkan tidak muncul
        $kamars = Kamar::with('images')
            ->where('status_ketersediaan', 1)
            ->withCount([
                'kamarUnits as total_fisik' => function (Builder $query) {
                    $query->where('status_unit', 'available');
                }
            ])
            ->get()
            ->map(function ($kamar) use ($checkIn, $checkOut) {

                // 3. LOGIKA UTAMA: Hitung Unit yang SUDAH TERISI (via PenempatanKamar)
                // Gunakan PenempatanKamar karena ini mencerminkan unit fisik yang sebenarnya terpakai
                $occupiedUnitIds = PenempatanKamar::whereHas('kamarUnit', function ($q) use ($kamar) {
                    $q->where('kamar_id', $kamar->id_kamar);
                })
                    ->whereHas('detailPemesanan.pemesanan', function ($q) use ($checkIn, $checkOut) {
                    $q->where('status_pemesanan', '!=', 'batal') // Abaikan yang batal
                        ->where(function ($query) use ($checkIn, $checkOut) {
                            // (StartBooking < RequestEnd) AND (EndBooking > RequestStart)
                            $query->where('tanggal_check_in', '<', $checkOut)
                                ->where('tanggal_check_out', '>', $checkIn);
                        });
                })
                    ->whereNotIn('status_penempatan', ['cancelled', 'checked_out']) // Exclude yang dibatalkan/selesai
                    ->pluck('kamar_unit_id')
                    ->unique()
                    ->count();

                // 4. Hitung Sisa
                $sisa = $kamar->total_fisik - $occupiedUnitIds;

                // 5. Masukkan data ke object response
                $kamar->sisa_kamar = max($sisa, 0);
                $kamar->is_available = $sisa > 0;

                return $kamar;
            })
            ->values();

        return $this->successResponse($kamars, 'Cek ketersediaan berhasil');
    }

    public function deleteImage($id)
    {
        $image = KamarImage::find($id);

        if (!$image) {
            return $this->errorResponse('Gambar tidak ditemukan', 404);
        }

        try {

            $relativePath = str_replace('storage/', '', $image->image_path);

            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($relativePath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($relativePath);
            }

            $image->delete();

            return $this->successResponse(null, 'Gambar berhasil dihapus.');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus gambar: ' . $e->getMessage(), 500);
        }
    }
}