# Review Database Seeder (Clarista Homestay)

**Status**: ⚠️ **Incomplete / Berisiko Error**
**Auditor**: AI Assistant

Berdasarkan analisis file di `database/seeders` dan `database/factories`, berikut adalah hasil review:

## 1. Analisis `DatabaseSeeder.php`
Logika di dalam `DatabaseSeeder.php` sebenarnya sudah **sangat komprehensif** dan mencakup skenario nyata:
*   ✅ **Role & User**: Membuat Admin/Owner dan Customer dummy.
*   ✅ **Kamar & Unit**: Membuat tipe kamar dan loop membuat unit fisik (101, 102, dst).
*   ✅ **Transaksi (Masa Lalu & Depan)**: `PemesananSeeder` memiliki logika canggih untuk mensimulasikan booking historis (untuk grafik) dan booking masa depan (untuk tes check-in).
*   ✅ **Multi-Room**: `MultiPemesananSeeder` menangani kasus satu orang pesan banyak kamar.

## 2. Masalah Utama (Critical Findings)
Meskipun logika pemanggilannya ada di `DatabaseSeeder.php`, file **Factory** pendukungnya **TIDAK DITEMUKAN** di folder `database/factories`. Ini akan menyebabkan error `Class 'Database\Factories\KamarFactory' not found` saat `db:seed` dijalankan.

**File Factory yang Hilang:**
1.  `KamarFactory.php` (Dibutuhkan oleh `KamarSeeder`)
2.  `PromoFactory.php` (Dibutuhkan oleh `PromoSeeder`)
3.  `KamarImageFactory.php` (Dibutuhkan oleh `KamarImageSeeder`)
4.  `ReviewFactory.php` (Dibutuhkan oleh `ReviewSeeder`)
5.  `PemesananFactory.php` (Logic seeding saat ini manual `::create`, tapi factory-nya tidak ada).

**File Factory yang Ada:**
*   `UserFactory.php` (OK)
*   `DetailPemesananFactory.php` (Ada, tapi logic seeder menggunakan manual create)
*   `KamarUnitFactory.php` (Ada, tapi logic seeder manual loop)
*   `PenempatanKamarFactory.php` (Ada)

## 3. Redundansi Kode
*   **HomestayContentSeeder**:
    *   Ada file `database/seeders/HomestayContentSeeder.php` (Kosong/Default).
    *   Tapi class `HomestayContentSeeder` juga didefinisikan secara *inline* di dalam `DatabaseSeeder.php` (Line 163).
    *   **Saran**: Pindahkan logic dari inline ke file terpisah agar rapi.

## 4. Kesimpulan & Rekomendasi
Seeder **belum siap dijalankan (Full Seed)** karena hilangnya Factory.

**Rekomendasi Perbaikan:**
1.  **Buat File Factory yang Hilang**: Saya bisa bantu buatkan `KamarFactory`, `PromoFactory`, `ReviewFactory`, dan `KamarImageFactory` sekarang agar logic di `DatabaseSeeder` bisa jalan.
2.  **Refactor**: Pindahkan class-class seeder yang menumpuk di `DatabaseSeeder.php` ke file masing-masing agar mudah dimaintain.
