<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Kamar;
use App\Models\Promo;
use App\Models\HomestayContent;
use App\Models\Pembayaran;
use App\Models\Review;
use App\Models\KamarImage;
use App\Models\Pemesanan;
use App\Models\DetailPemesanan;
use App\Models\kamar_units;
use App\Models\Role;
use App\Models\penempatan_kamar;
use App\Models\penempatan_kamars;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Data yang akan dimasukkan
        $roles = [
            [
                'role' => 'owner',
                'deskripsi' => 'Pemilik atau Administrator utama sistem. Memiliki hak akses penuh untuk manajemen data, verifikasi, dan laporan.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'role' => 'customer',
                'deskripsi' => 'Pengguna umum yang dapat melakukan pendaftaran, melihat kamar, dan membuat pemesanan online.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Opsional: Jika Anda ingin menambahkan role lain
            // [
            //     'role' => 'housekeeping_staff',
            //     'deskripsi' => 'Staf yang bertugas menangani kebersihan dan maintenance kamar.',
            //     'created_at' => now(),
            //     'updated_at' => now(),
            // ]
        ];

        // Hapus data lama (opsional, jika Anda ingin bersih-bersih)
        // DB::table('roles')->truncate();
        
        // Masukkan data ke tabel
        DB::table('roles')->insert($roles);
    }
}
class KamarSeeder extends Seeder
{
    public function run(): void
    {
        // Hapus data lama (opsional)
        // Kamar::query()->delete();

        // Buat beberapa data kamar spesifik
        Kamar::factory()->create([
            'tipe_kamar' => 'Single Bed',
            'deskripsi' => 'Kamar yang nyaman dan ringkas, cocok untuk solo traveler.',
            'harga' => 350000,
        ]);

        Kamar::factory()->create([
            'tipe_kamar' => 'Double Bed',
            'deskripsi' => 'Ruangan yang lebih luas, ideal untuk pasangan atau dua orang.',
            'harga' => 550000,
        ]);

        // Buat 5 data kamar acak lainnya
        // Kamar::factory()->count(5)->create();
    }
}
class PromoSeeder extends Seeder
{
    public function run(): void
    {
        // Hapus data lama (opsional)
        Promo::query()->delete();

        // Buat beberapa promo spesifik
        Promo::factory()->create([
            'nama_promo' => 'Diskon Akhir Pekan',
            'kode_promo' => 'WEEKENDSERU',
            'tipe_diskon' => 'persen',
            'nilai_diskon' => 15,
            'berlaku_selesai' => now()->addMonths(1),
        ]);

        Promo::factory()->create([
            'nama_promo' => 'Potongan Langsung Pengguna Baru',
            'kode_promo' => 'NEWUSER25K',
            'tipe_diskon' => 'nominal',
            'nilai_diskon' => 25000,
            'berlaku_selesai' => now()->addMonths(3),
        ]);

        // Buat 5 promo acak lainnya
        Promo::factory()->count(5)->create();
    }
}
class KamarImageSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil semua kamar yang ada
        $kamars = Kamar::all();

        // Loop setiap kamar, beri masing-masing 3 foto dummy
        foreach ($kamars as $kamar) {
            KamarImage::factory()->count(3)->create([
                'kamar_id' => $kamar->id_kamar
            ]);
        }
    }
}

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class, 
        ]);
        // Buat 1 Akun Owner
        User::factory()->owner()->create([
            'name' => 'Admin Clarista',
            'email' => 'owner@clarista.com',
        ]);
                User::factory()->customer()->create([
            'name' => 'Customer Clarista',
            'email' => 'customer@clarista.com',
        ]);

        // Buat 10 Akun Customer dummy
        User::factory()->count(10)->create();
        
        // Panggil Seeder untuk Kamar
        $this->call([
            KamarSeeder::class,
            PromoSeeder::class,
            HomestayContentSeeder::class,
            // PemesananManualSeeder::class,
            ReviewSeeder::class,
            KamarImageSeeder::class,
            KamarUnitSeeder::class,     
            PemesananSeeder::class,
            MultiPemesananSeeder ::class
            // PenempatanKamarSeeder::class,
        ]);
    }
}

class HomestayContentSeeder extends Seeder
{
    public function run(): void
    {
        // Membuat satu record default jika belum ada
        HomestayContent::updateOrCreate(
            ['id' => 1],
            [
                'alamat' => 'Jl. Parangtritis KM 20, Kretek, Bantul',
                'telepon' => '081234567890',
                'email' => 'kontak@claristahomestay.com',
                'link_gmaps' => 'https://maps.google.com/',
                'hero_title' => 'SELAMAT DATANG DI CLARISTA HOMESTAY',
                'hero_subtitle' => 'Penginapan nyaman dengan sentuhan personal.',
                // Data Bank Baru
                'nama_bank' => 'BCA',
                'nomor_rekening' => '123-456-7890',
                'atas_nama_rekening' => 'Clarista Owner'
            ]
        );
    }
}

// class PemesananManualSeeder extends Seeder
// {
//     public function run(): void
//     {
//         // 1. CARI USER TARGET YANG SUDAH ADA
//         $customerRoleId = Role::where('role', 'customer')->value('id');
//         $customer = User::where('role_id', $customerRoleId)->inRandomOrder()->first();
//         // SKENARIO 1: Buat Data Booking SPESIFIK (Untuk Tes Manual Kita)
//         // Kita akan booking Kamar ID 1 untuk 3 hari ke depan.
//         // Jadi kalau Anda cek ketersediaan di tanggal ini, harusnya PENUH/BERKURANG.
        
//         $kamarTarget = Kamar::first(); // Ambil kamar pertama
        
//         if ($kamarTarget && $customer) {
//             $bookingManual = Pemesanan::factory()->create([
//                 // Tentukan user_id secara eksplisit
//                 'user_id' => $customer->id, 
                
//                 'tanggal_check_in' => Carbon::now()->addDays(2)->format('Y-m-d'),
//                 'tanggal_check_out' => Carbon::now()->addDays(5)->format('Y-m-d'),
//                 'status_pemesanan' => 'dikonfirmasi', // Gunakan 'dikonfirmasi' (sesuai ENUM)
//                 'total_bayar' => 0 
//             ]);

//             // Hitung durasi dan total
//             $durasi = 3; 
//             $total = 1 * $kamarTarget->harga * $durasi;

//             // Masukkan ke detail
//             DetailPemesanan::create([
//                 'pemesanan_id' => $bookingManual->id,
//                 'kamar_id' => $kamarTarget->id_kamar,
//                 'jumlah_kamar' => 1,
//                 'harga_per_malam' => $kamarTarget->harga
//             ]);

//             // Update total bayar
//             $bookingManual->update(['total_bayar' => $total]);
//         }
//     }
// }
class PemesananSeeder extends Seeder
{
    public function run(): void
    {
        $customerRoleId = Role::where('role', 'customer')->value('id');
        $customers = User::where('role_id', $customerRoleId)->get();
        $kamars = Kamar::all();
        $statuses = ['menunggu_pembayaran', 'menunggu_konfirmasi', 'dikonfirmasi', 'selesai'];

        if ($customers->isEmpty() || $kamars->isEmpty()) {
            $this->command->info('Tidak ada customer atau kamar, seeder dilewati.');
            return;
        }

        $this->command->info('--- Membuat 5 Data Riwayat Masa Lalu ---');
    
    for ($i = 0; $i < 10; $i++) {
        // 1. TANGGAL MUNDUR (1 - 60 hari ke belakang)
        $checkIn = Carbon::now()->subDays(rand(5, 60)); 
        $checkOut = $checkIn->copy()->addDays(rand(1, 3));
        
        // Logika cari unit (Sama persis, copy logic Anda)
        // ... (Logic OccupiedUnitIds Anda taruh disini) ...
        // Agar kode ringkas, saya persingkat di sini:
        $customer = $customers->random();
        $kamarTipe = $kamars->random();
        
        // Cek ketersediaan (Logic Anda sudah benar, pakai saja)
        // Anggaplah $availableUnit sudah dapat unit yang available di tanggal masa lalu tsb
        // (Pastikan tetap pakai logic whereNotIn punya Anda agar tidak bentrok)
        
        // SAYA TULIS ULANG LOGIC ANDA BIAR JELAS:
        $occupiedUnitIds = penempatan_kamar::whereHas('detailPemesanan.pemesanan', function($q) use ($checkIn, $checkOut) {
             $q->where('status_pemesanan', '!=', 'batal')
               ->where(function($query) use ($checkIn, $checkOut) {
                   $query->where('tanggal_check_in', '<', $checkOut)
                         ->where('tanggal_check_out', '>', $checkIn);
               });
        })->pluck('kamar_unit_id');

        $availableUnit =kamar_units::where('kamar_id', $kamarTipe->id_kamar)
            ->where('status_unit', 'available') // Fisik sekarang available (karena tamunya udh pulang)
            ->whereNotIn('id', $occupiedUnitIds)
            ->first();

        if (!$availableUnit) continue;

        DB::transaction(function () use ($customer, $kamarTipe, $availableUnit, $checkIn, $checkOut) {
            
            // A. HEADER: Status PASTI 'selesai'
            $pemesanan = Pemesanan::create([
                'user_id' => $customer->id,
                'tanggal_check_in' => $checkIn,
                'tanggal_check_out' => $checkOut,
                'total_bayar' => $kamarTipe->harga,
                'status_pemesanan' => 'selesai', // <--- KUNCI MASA LALU
            ]);

            $detail = DetailPemesanan::create([
                'pemesanan_id' => $pemesanan->id,
                'kamar_id' => $kamarTipe->id_kamar,
                'jumlah_kamar' => 1,
                'harga_per_malam' => $kamarTipe->harga,
            ]);

            // B. PENEMPATAN: Status 'checked_out' + Waktu Aktual
            penempatan_kamar::create([
                'detail_pemesanan_id' => $detail->id,
                'kamar_unit_id' => $availableUnit->id,
                'status_penempatan' => 'checked_out', // <--- KARENA SUDAH PULANG
                'check_in_aktual' => $checkIn->copy()->addHour(14), // Datang jam 2 siang
                'check_out_aktual' => $checkOut->copy()->addHour(10), // Pulang jam 10 pagi
            ]);

            // C. PEMBAYARAN: Pasti Lunas
            Pembayaran::create([
                'pemesanan_id' => $pemesanan->id,
                'bukti_bayar_path' => 'dummy.jpg',
                'status_verifikasi' => 'terverifikasi',
            ]);
        });
    }

    // ==========================================
    // BAGIAN 2: DATA MASA DEPAN (Untuk Tes Check-in)
    // ==========================================
    $this->command->info('--- Membuat 5 Data Upcoming Booking ---');

        // Kita coba buat 5 pesanan dummy
        for ($i = 0; $i < 5; $i++) {
            try{
                // 1. Tentukan Tanggal Acak (Range 1 bulan ke depan)
                $checkIn = Carbon::now()->addDays(rand(1, 30));
                $checkOut = $checkIn->copy()->addDays(rand(1, 5));
                
                // 2. Pilih Customer & Tipe Kamar Acak
                $customer = $customers->random();
                $kamarTipe = $kamars->random(); 

                // 3. LOGIKA UTAMA: Cari Unit Fisik yang Available di tanggal tersebut
                // Kita cari Unit ID yang TIDAK ada di daftar penempatan pada tanggal tabrakan
                $occupiedUnitIds = penempatan_kamar::whereHas('detailPemesanan.pemesanan', function($q) use ($checkIn, $checkOut) {
                        $q->where('status_pemesanan', '!=', 'batal') // Abaikan yang batal
                        ->where(function($query) use ($checkIn, $checkOut) {
                            $query->where('tanggal_check_in', '<', $checkOut)
                                    ->where('tanggal_check_out', '>', $checkIn);
                        });
                    })
                    ->pluck('kamar_unit_id');

                // Ambil 1 unit yang bebas (tidak ada di list occupied) dan status fisiknya 'available'
                $availableUnit = kamar_units::where('kamar_id', $kamarTipe->id_kamar)
                    ->where('status_unit', 'available') // Pastikan fisik tidak rusak/maintenance
                    ->whereNotIn('id', $occupiedUnitIds)
                    ->first();

                // Jika tidak ada unit kosong di tanggal itu, skip iterasi ini (cari tanggal lain di loop berikutnya)
                if (!$availableUnit) {
                    continue; 
                }

                // 4. Mulai Transaksi Simpan Data
                DB::transaction(function () use ($customer, $kamarTipe, $availableUnit, $checkIn, $checkOut, $statuses) {
                    
                    $statusAcak = $statuses[array_rand($statuses)];
                    
                    // A. Buat Header Pemesanan
                    $pemesanan = Pemesanan::create([
                        'user_id' => $customer->id,
                        'tanggal_check_in' => $checkIn,
                        'tanggal_check_out' => $checkOut,
                        'total_bayar' => $kamarTipe->harga, // Asumsi 1 kamar dulu biar mudah
                        'status_pemesanan' => $statusAcak,
                    ]);

                    // B. Buat Detail Pemesanan
                    $detail = DetailPemesanan::create([
                        'pemesanan_id' => $pemesanan->id,
                        'kamar_id' => $kamarTipe->id_kamar,
                        'jumlah_kamar' => 1,
                        'harga_per_malam' => $kamarTipe->harga,
                    ]);

                    // C. PENTING: Assign Unit (Penempatan Kamar)
                    // Inilah yang membuat kamar dianggap "Terisi" oleh sistem dinamis
                    penempatan_kamar::create([
                        'detail_pemesanan_id' => $detail->id,
                        'kamar_unit_id' => $availableUnit->id,
                        'status_penempatan' => 'assigned', // Default assigned
                    ]);

                    // D. Buat Pembayaran Dummy (Jika bukan menunggu pembayaran)
                    if ($statusAcak !== 'menunggu_pembayaran') {
                        Pembayaran::create([
                            'pemesanan_id' => $pemesanan->id,
                            'bukti_bayar_path' => 'public/bukti_pembayaran/dummy_proof.jpg',
                            'status_verifikasi' => ($statusAcak === 'dikonfirmasi' || $statusAcak === 'selesai') ? 'terverifikasi' : 'menunggu_verifikasi',
                        ]);
                    }
                });
                $this->command->warn("Sukses pesanan ke-" . ($i + 1));

            }catch (\Exception $e) {
                // INI AKAN MENAMPILKAN ERROR DI TERMINAL
                $this->command->warn("Gagal di pesanan ke-" . ($i + 1) . ": " . $e->getMessage());
                }
        }
    }
}

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil semua pesanan yang sudah selesai atau dikonfirmasi
        $pemesanans = Pemesanan::whereIn('status_pemesanan', ['dikonfirmasi', 'selesai'])->get();

        foreach ($pemesanans as $pemesanan) {
            // Buat review untuk setiap pesanan yang memenuhi syarat
            Review::factory()->create([
                'pemesanan_id' => $pemesanan->id,
                'user_id' => $pemesanan->user_id,
            ]);
        }
    }
}

class KamarUnitSeeder extends Seeder
{
    public function run(): void
    {
        $kamarTypes = Kamar::all();
        $unitCounter = 101; // Mulai penomoran kamar dari 101

        foreach ($kamarTypes as $kamar) {
            
            // Loop sejumlah total kamar fisik yang dimiliki tipe ini
            for ($i = 0; $i < $kamar->jumlah_total; $i++) {
                
                kamar_units::create([
                    'kamar_id' => $kamar->id_kamar,
                    'nomor_unit' => (string) $unitCounter++, // Nomor 101, 102, 103, dst
                    'status_unit' => 'available',
                ]);
            }
        }
    }
}

class MultiPemesananSeeder extends Seeder
{
    public function run(): void
    {
        $customerRoleId = Role::where('role', 'customer')->value('id');
        $customers = User::where('role_id', $customerRoleId)->get();
        $kamars = Kamar::all();
        $statuses = ['menunggu_pembayaran', 'menunggu_konfirmasi', 'dikonfirmasi', 'selesai'];

        if ($customers->isEmpty() || $kamars->isEmpty()) {
            $this->command->info('Skip: Data master user/kamar kosong.');
            return;
        }

        $this->command->info('--- Memulai Seeder Multi-Room Booking ---');

        // Kita coba buat 20 Transaksi Campuran
        for ($i = 0; $i < 20; $i++) {
            
            // 1. Tentukan Skenario (Masa Lalu atau Masa Depan?)
            // Biar adil: 70% data masa lalu (biar grafik bagus), 30% masa depan (buat tes)
            $isPast = rand(1, 10) <= 7; 

            if ($isPast) {
                // Masa Lalu (Histori)
                $checkIn = Carbon::now()->subDays(rand(5, 60));
                $checkOut = $checkIn->copy()->addDays(rand(1, 3));
                $statusPemesanan = 'selesai';
            } else {
                // Masa Depan (Upcoming)
                $checkIn = Carbon::now()->addDays(rand(1, 30));
                $checkOut = $checkIn->copy()->addDays(rand(1, 3));
                $statusPemesanan = $statuses[array_rand($statuses)];
            }

            // Hitung durasi malam
            $durasiMalam = $checkIn->diffInDays($checkOut);

            // 2. Random Customer & Tipe Kamar
            $customer = $customers->random();
            $kamarTipe = $kamars->random();

            // 3. TENTUKAN JUMLAH KAMAR YANG MAU DIPESAN (1 s/d 3 Kamar)
            $jumlahKamarDipesan = rand(1, 3); 

            // 4. CEK KETERSEDIAAN (Logic Filter)
            // Cari ID unit yang sibuk
            $occupiedUnitIds = penempatan_kamar::whereHas('detailPemesanan.pemesanan', function($q) use ($checkIn, $checkOut) {
                $q->where('status_pemesanan', '!=', 'batal')
                  ->where(function($query) use ($checkIn, $checkOut) {
                      $query->where('tanggal_check_in', '<', $checkOut)
                            ->where('tanggal_check_out', '>', $checkIn);
                  });
            })->pluck('kamar_unit_id');

            // AMBIL UNIT DALAM JUMLAH BANYAK
            // Perhatikan bedanya: kita pakai take() dan get()
            $availableUnits = kamar_units::where('kamar_id', $kamarTipe->id_kamar)
                ->where('status_unit', 'available')
                ->whereNotIn('id', $occupiedUnitIds)
                ->inRandomOrder() // Acak unitnya biar ga dapet 101 terus
                ->take($jumlahKamarDipesan) // Ambil sebanyak yg diminta
                ->get();

            // VALIDASI: Kalau jumlah unit yang didapat KURANG dari yang diminta, skip transaksi ini
            // Contoh: Minta 3, tapi cuma ada 2 unit kosong -> Gagal Booking
            if ($availableUnits->count() < $jumlahKamarDipesan) {
                // Opsional: Coba turunkan ekspektasi jadi pesan 1 kamar saja (fallback)
                // Tapi untuk tes ini, kita skip saja biar strict.
                continue; 
            }

            // 5. EKSEKUSI PENYIMPANAN
            try {
                DB::transaction(function () use ($customer, $kamarTipe, $availableUnits, $checkIn, $checkOut, $durasiMalam, $statusPemesanan, $jumlahKamarDipesan, $isPast) {
                    
                    // Hitung Total Bayar: Harga x Jumlah Kamar x Jumlah Malam
                    $totalHarga = $kamarTipe->harga * $jumlahKamarDipesan * $durasiMalam;

                    // A. Header Pemesanan
                    $pemesanan = Pemesanan::create([
                        'user_id' => $customer->id,
                        'tanggal_check_in' => $checkIn,
                        'tanggal_check_out' => $checkOut,
                        'total_bayar' => $totalHarga,
                        'status_pemesanan' => $statusPemesanan,
                    ]);

                    // B. Detail Pemesanan (Satu baris detail untuk banyak kamar)
                    $detail = DetailPemesanan::create([
                        'pemesanan_id' => $pemesanan->id,
                        'kamar_id' => $kamarTipe->id_kamar,
                        'jumlah_kamar' => $jumlahKamarDipesan, // Misal: 3
                        'harga_per_malam' => $kamarTipe->harga,
                    ]);

                    // C. Penempatan Kamar (LOOPING SEBANYAK UNIT YANG DIDAPAT)
                    foreach ($availableUnits as $unit) {
                        penempatan_kamar::create([
                            'detail_pemesanan_id' => $detail->id, // Induknya sama
                            'kamar_unit_id' => $unit->id,         // Anaknya beda-beda (101, 102, 103)
                            'status_penempatan' => $isPast ? 'checked_out' : 'assigned',
                            'check_in_aktual' => $isPast ? $checkIn->copy()->addHour(14) : null,
                            'check_out_aktual' => $isPast ? $checkOut->copy()->addHour(11) : null,
                        ]);
                    }

                    // D. Pembayaran
                    if ($statusPemesanan !== 'menunggu_pembayaran') {
                        Pembayaran::create([
                            'pemesanan_id' => $pemesanan->id,
                            'bukti_bayar_path' => 'public/bukti_pembayaran/dummy_multi.jpg',
                            'status_verifikasi' => ($statusPemesanan == 'dikonfirmasi' || $statusPemesanan == 'selesai') ? 'terverifikasi' : 'menunggu_verifikasi',
                        ]);
                    }
                });

                $this->command->info("✅ Sukses: Order {$jumlahKamarDipesan} unit kamar {$kamarTipe->tipe_kamar}");

            } catch (\Exception $e) {
                $this->command->error("❌ Gagal: " . $e->getMessage());
            }
        }
    }
}

// class PenempatanKamarSeeder extends Seeder
// {
//     public function run(): void
//     {
//         // 1. Ambil unit kamar fisik pertama (misal Kamar 101)
//         $unitPertama = kamar_units::where('nomor_unit', '101')->first();
        
//         // 2. Ambil detail pemesanan pertama yang ada di database Anda
//         // Berdasarkan SQL dump, Detail ID 1 memesan 1 kamar tipe 5
//         $detailPemesanan = DetailPemesanan::find(1);

//         if ($unitPertama && $detailPemesanan) {
            
//             // 3. Catat Penempatan Kamar
//             penempatan_kamar::create([
//                 'detail_pemesanan_id' => $detailPemesanan->id,
//                 'kamar_unit_id' => $unitPertama->id,
//                 'status_penempatan' => 'checked_in', // Langsung dianggap Check-in untuk testing
//                 'check_in_aktual' => now(), 
//             ]);
            
//             // Opsional: Update status unit menjadi occupied
//             $unitPertama->update(['status_unit' => 'occupied']);
            
//             $this->command->info('Unit 101 berhasil di-assign ke Pemesanan ID 1.');
//         } else {
//             $this->command->warn('Gagal seeding penempatan: Pastikan KamarUnitSeeder dan data DetailPemesanan sudah ada.');
//         }
//     }
// }