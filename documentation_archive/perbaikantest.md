# Analisis dan Perbaikan Test Failures

## Penyebab Error
Error yang terjadi pada `BookingTest`, `DebugAvailabilityTest`, dan `KamarAvailabilityTest` disebabkan oleh satu akar masalah yang sama, yaitu **data kamar yang dibuat di database memiliki `status_ketersediaan = 0` (False)**, sehingga tidak muncul saat dicek oleh API `cek-ketersediaan`.

### Detail Masalah
1.  **Logika Model `Kamar`**:
    Pada file `app/Models/Kamar.php`, terdapat method `booted()` yang berisi logic:
    ```php
    static::saving(function ($kamar) {
        // Jika jumlah tersedia adalah 0, set status menjadi false (tidak tersedia)
        if ($kamar->jumlah_tersedia <= 0) {
            $kamar->status_ketersediaan = false;
        } else {
            $kamar->status_ketersediaan = true;
        }
    });
    ```
    
2.  **Inkonsistensi Database**:
    Kolom `jumlah_tersedia` **tidak ada** di tabel `kamars` (berdasarkan migration `2025_06_03_031409_create_kamars_table.php`). Data yang ada hanyalah `jumlah_total`.

3.  **Efek pada Test**:
    Saat test membuat data kamar menggunakan:
    ```php
    Kamar::create([
        'jumlah_total' => 5,
        'status_ketersediaan' => true,
        // 'jumlah_tersedia' TIDAK DISET
    ]);
    ```
    Nilai `$kamar->jumlah_tersedia` adalah `null` (atau 0 karena tidak ada di atribut). Kondisi `($kamar->jumlah_tersedia <= 0)` menjadi **TRUE**, sehingga sistem **memaksa** `status_ketersediaan` menjadi `false`.

4.  **Efek pada API**:
    Controller `KamarController::cekKetersediaan` melakukan query:
    ```php
    $kamars = Kamar::...->where('status_ketersediaan', 1)...->get();
    ```
    Karena status kamar di database adalah 0 (False), maka **tidak ada data kamar yang dikembalikan**, menyebabkan array kosong dan failing assertions pada test.

---

## Rekomendasi Perbaikan

Kami merekomendasikan **Perbaikan 1** sebagai solusi utama karena paling sesuai dengan skema database saat ini yang menghitung ketersediaan secara dinamis berdasarkan `KamarUnit` dan `Pemesanan`.

### Perbaikan 1: Ubah Logika `booted` di Model `Kamar` (Recommended)
Ubah dependensi status dari `jumlah_tersedia` (yang tidak ada) menjadi `jumlah_total`. Jika total kamar 0, maka otomatis tidak tersedia.

**File:** `app/Models/Kamar.php`

```php
    protected static function booted(): void
    {
        // Event ini akan berjalan setiap kali model akan disimpan (dibuat atau diupdate)
        static::saving(function ($kamar) {
            // PERBAIKAN: Gunakan jumlah_total, bukan jumlah_tersedia
            // Jika jumlah total kamar 0, set status menjadi false (tidak tersedia)
            if ($kamar->jumlah_total <= 0) {
                $kamar->status_ketersediaan = false;
            } 
            // Opsional: Jika user manual set status ke false, jangan dipaksa true
            // elseif (!isset($kamar->status_ketersediaan)) { 
            //    $kamar->status_ketersediaan = true; 
            // }
        });
    }
```
*Atau, hapus logic `else` yang memaksa `true` jika Anda ingin menghormati input manual.*

### Perbaikan 2: Hapus Logic `booted` Sepenuhnya
Jika status ketersediaan diatur sepenuhnya manual oleh admin (misal saat input data), maka logic otomatis ini bisa dihapus saja.

**File:** `app/Models/Kamar.php`
Hapus seluruh method `booted()`.

### Langkah Konkret
Silakan terapkan perubahan pada `app/Models/Kamar.php` dengan mengganti referensi `jumlah_tersedia` menjadi `jumlah_total`.

```diff
-            if ($kamar->jumlah_tersedia <= 0) {
+            if ($kamar->jumlah_total <= 0) {
```
