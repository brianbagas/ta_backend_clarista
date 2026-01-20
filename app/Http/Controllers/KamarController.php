<?php

namespace App\Http\Controllers;
use App\Models\DetailPemesanan;
use App\Models\Kamar;
use App\Models\KamarUnit;
use App\Models\KamarImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
class KamarController extends Controller
{
    /**
     * Menampilkan semua data kamar.
     * GET /api/kamar
     */
    public function index()
    {
        // Mengambil semua data kamar dari database
        $kamars = Kamar::all();
        
        // Mengembalikan data sebagai respons JSON
        return response()->json([
            'success' => true,
            'message' => 'Daftar semua kamar berhasil ditampilkan.',
            'data' => $kamars,
        ], 200);
    }

    /**
     * Menyimpan data kamar baru.
     * POST /api/kamar
     */
public function addKamar(Request $request)
{
    // 1. Validasi Tambahan: 'jumlah_kamar'
    $validator = Validator::make($request->all(), [
        'tipe_kamar' => 'required|string|max:50',
        'harga' => 'required|numeric|min:0',
        'deskripsi' => 'nullable|string',
        'jumlah_total' => 'required|integer|min:1', // Wajib diisi: mau bikin berapa kamar?
        'gambar_kamar' => 'nullable|image|mimes:jpg,png,jpeg|max:2048'
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }

    // Mulai Transaksi Database
    // (Penting: agar jika gagal bikin unit, data tipe kamar juga batal dibuat)
    DB::beginTransaction();

    try {
        // 1. Tentukan Prefix (Angka Depan)
    // Hitung berapa tipe kamar yang SUDAH ada di database (termasuk yang soft deleted jika perlu, atau yang aktif saja)
    $jumlahTipeKamarLama = Kamar::count(); 
    
    // Tipe kamar baru ini adalah urutan selanjutnya
    $urutanTipeIni = $jumlahTipeKamarLama + 1; 

    // Jika urutan ke-1 jadi 100, ke-2 jadi 200, dst.
    $prefixAngka = $urutanTipeIni * 100;
        // 2. Handle Upload Gambar
        $imagePath = null;


        // 3. Buat Data Parent (Tipe Kamar)
        $kamar = Kamar::create([
            'tipe_kamar' => $request->tipe_kamar,
            'harga' => $request->harga,
            'deskripsi' => $request->deskripsi,
            // Simpan path gambar ke kolom yang sesuai di DB (misal: image_path atau gambar)
            'image_path' => $imagePath, 
            // Set stok awal sesuai input jumlah
            'jumlah_total' => $request->jumlah_total,
            // 'jumlah_tersedia' => $request->jumlah_kamar,
            'status_ketersediaan' => true,
        ]);

        if ($request->hasFile('gambar_kamar')) {
            // Upload File
            $pathRaw = $request->file('gambar_kamar')->store('kamars', 'public');
            $fullPath = 'storage/' . $pathRaw;

            // Create data di tabel child (kamar_images)
            KamarImage::create([
                'kamar_id' => $kamar->id_kamar,
                'image_path' => $fullPath,
            ]);
        }

        // 4. Buat Data Child (Unit Kamar Fisik) secara Otomatis
        // Jika input jumlah_kamar = 3, maka loop 3 kali.
for ($i = 1; $i <= $request->jumlah_total; $i++) {
        
        // Kalkulasi Nomor: Misal Prefix 100 + 1 = 101
        $nomorKamar = $prefixAngka + $i; 
        
        // Format String (Opsional): 
        // Jika ingin murni angka: 101
        // Jika ingin ada nama: "Single Bed - 101"
        // Disini saya buat murni angka atau kombinasi simpel sesuai request
        $namaUnit = (string) $nomorKamar; 

        KamarUnit::create([
            'kamar_id' => $kamar->id_kamar,
            // Hasilnya akan masuk sebagai "101", "102", dst.
            'nomor_unit' => $namaUnit, 
            'status_unit' => true,
        ]);
    }
        // Jika semua lancar, simpan permanen
        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Tipe kamar dan ' . $request->jumlah_kamar . ' unit berhasil dibuat.',
            'data' => $kamar->load('kamarUnits'), // Return data beserta unit-nya
        ], 201);

    } catch (\Exception $e) {
        // Jika ada error, batalkan semua perubahan database
        DB::rollBack();
        
        // Hapus gambar jika terlanjur ter-upload (opsional, untuk kebersihan server)
        // if ($imagePath) Storage::delete(...) 

        return response()->json([
            'success' => false,
            'message' => 'Gagal menyimpan data: ' . $e->getMessage()
        ], 500);
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
            return response()->json($validator->errors(), 422);
        }

        if ($request->hasFile('gambar_kamar')) {
            $path = $request->file('gambar_kamar')->store('public/kamars');
            $validatedData['image_path'] = $path;
        }

        // Membuat data kamar baru
        $kamar = Kamar::create($request->all());

        // Mengembalikan respons sukses beserta data yang baru dibuat
        return response()->json([
            'success' => true,
            'message' => 'Data kamar berhasil ditambahkan.',
            'data' => $kamar,
        ], 201);
    }

    /**
     * Menampilkan satu data kamar spesifik.
     * GET /api/kamar/{id}
     */
    public function show(Kamar $kamar)
    {
        // Mengembalikan data kamar yang ditemukan sebagai respons JSON
        return response()->json([
            'success' => true,
            'message' => 'Detail kamar berhasil ditampilkan.',
            'data' => $kamar,
        ], 200);
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
        ]);

        // Jika validasi gagal, kembalikan pesan error
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Melakukan update pada data kamar
        $kamar->update($request->all());

        // Mengembalikan respons sukses beserta data yang telah diupdate
        return response()->json([
            'success' => true,
            'message' => 'Data kamar berhasil diupdate.',
            'data' => $kamar,
        ], 200);
    }

    /**
     * Menghapus data kamar.
     * DELETE /api/kamar/{id}
     */
    public function destroy(Kamar $kamar)
    {
        // Menghapus data kamar dari database
        $kamar->delete();

        // Mengembalikan respons sukses
        return response()->json([
            'success' => true,
            'message' => 'Data kamar berhasil dihapus.',
        ], 200);
    }

    public function cekKetersediaan(Request $request)
{
    // 1. Validasi Input
    $request->validate([
        'check_in'  => 'required|date|after_or_equal:today', // Tidak boleh tanggal lampau
        'check_out' => 'required|date|after:check_in',       // Harus setelah check_in
    ]);

    $checkIn  = $request->check_in;
    $checkOut = $request->check_out;

    // 2. Ambil Data Kamar
    // Tambahkan where('status_ketersediaan', 1) agar kamar yang dinonaktifkan tidak muncul
    $kamars = Kamar::with('images')
        ->where('status_ketersediaan', 0) 
        ->withCount('KamarUnit as total_fisik') // Hitung total aset fisik (misal: 10 unit)
        ->get()
        ->map(function ($kamar) use ($checkIn, $checkOut) {
            
            // 3. LOGIKA UTAMA: Hitung Kamar yang SUDAH DIPESAN (Booked)
            // Kita cari di tabel transaksi (DetailPemesanan), bukan tabel fisik.
            $jumlahTerpesan = DetailPemesanan::where('kamar_id', $kamar->id_kamar)
                ->whereHas('pemesanan', function($q) use ($checkIn, $checkOut) {
                    $q->where('status_pemesanan', '!=', 'batal') // Abaikan yang batal
                      ->where(function($query) use ($checkIn, $checkOut) {
                          // RUMUS SAKTI: Cek Bentrok Tanggal (Overlap)
                          // (StartBooking < RequestEnd) AND (EndBooking > RequestStart)
                          $query->where('tanggal_check_in', '<', $checkOut)
                                ->where('tanggal_check_out', '>', $checkIn);
                      });
                })
                ->sum('jumlah_kamar'); // Gunakan SUM, karena 1 pesanan bisa > 1 kamar

            // 4. Hitung Sisa
            // Sisa = Total Punya Kita - Total Yang Dipesan Orang
            $sisa = $kamar->total_fisik - $jumlahTerpesan;

            // 5. Masukkan data ke object response
            $kamar->sisa_kamar = max($sisa, 0); // Hindari angka minus
            $kamar->is_available = $sisa > 0;
            
            // (Opsional) Debugging info
            $kamar->debug_info = [
                'total_aset' => $kamar->total_fisik,
                'sedang_dibooking' => $jumlahTerpesan
            ];

            return $kamar;
        });

    return response()->json([
        'success' => true,
        'message' => 'Cek ketersediaan berhasil',
        'data' => $kamars,
    ]);
}
}