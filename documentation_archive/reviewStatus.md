# Review Konsistensi Status Aplikasi

## Ringkasan
Saat ini, penggunaan status di aplikasi Clarista Homestay belum konsisten. Terdapat percampuran antara Bahasa Indonesia dan Bahasa Inggris, serta penggunaan *hardcoded strings* di banyak tempat yang rentan terhadap *typo* dan sulit di-*maintenance*.

---

## 1. Analisis Status per Tabel

### A. Tabel `pemesanans` (Transaksi Utama)
- **Kolom**: `status_pemesanan`
- **Tipe**: `String`
- **Nilai Saat Ini (Bahasa Indonesia)**:
    - `'menunggu_pembayaran'` (Default)
    - `'menunggu_konfirmasi'` (Digunakan di `PembayaranController`, tapi tidak terdokumentasi di migration)
    - `'dikonfirmasi'`
    - `'selesai'`
    - `'batal'`
- **Masalah**: Penggunaan string manual di Controller. Risiko typo tinggi (misal: `menunggu_konfirmasi` vs `menunggu_verifikasi`).

### B. Tabel `kamar_units` (Fisik Kamar)
- **Kolom**: `status_unit`
- **Tipe**: `Enum`
- **Nilai Saat Ini (Bahasa Inggris)**:
    - `'available'`
    - `'occupied'`
    - `'maintenance'`
- **Masalah**: Menggunakan Bahasa Inggris, berbeda dengan `pemesanan` yang menggunakan Bahasa Indonesia.

### C. Tabel `penempatan_kamars` (Logika Check-in)
- **Kolom**: `status_penempatan`
- **Tipe**: `Enum`
- **Nilai Saat Ini (Bahasa Inggris)**:
    - `'pending'`
    - `'assigned'`
    - `'checked_in'`
    - `'checked_out'`
    - `'cleaning'`
    - `'cancelled'`
- **Masalah**: Inkonsistensi bahasa dengan tabel utama.

### D. Tabel `pembayarans` (Bukti Transfer)
- **Kolom**: `status_verifikasi`
- **Tipe**: `String`
- **Nilai Saat Ini (Bahasa Indonesia)**:
    - `'menunggu_verifikasi'` (Default)
- **Masalah**: Kadang tertukar istilah dengan `menunggu_konfirmasi` pada pemesanan.

---

## 2. Temuan Inkonsistensi Utama

1.  **Bahasa Ganda**:
    - Transaksi Customer (`Pemesanan`, `Pembayaran`) menggunakan **Bahasa Indonesia**.
    - Manajemen Internal/Operasional (`Unit`, `Penempatan`) menggunakan **Bahasa Inggris**.
    - *Rekomendasi*: Sebaiknya diseragamkan ke satu bahasa (Inggris disarankan untuk kode program), atau pertahankan *separation of concern* ini namun bungkus dalam Constant agar tidak membingungkan.

2.  **Hardcoded Strings**:
    - Kode penuh dengan string `'menunggu_pembayaran'`, `'batal'`, dll.
    - Jika ada perubahan bisnis (misal: ganti istilah 'batal' jadi 'cancelled'), Anda harus *find & replace* ratusan file.

3.  **Hidden Status**:
    - Status `'menunggu_konfirmasi'` muncul di `PembayaranController` saat user upload bukti bayar, namun di migration komentar hanya mencantumkan `menunggu_pembayaran`, `dikonfirmasi`, `selesai`. Hal ini membingungkan developer baru.

---

## 3. Rekomendasi Perbaikan (Action Plan)

### A. Gunakan Konstanta di Model (Wajib)
Alihkan semua string manual ke dalam konstanta di Model masing-masing.

**Contoh Refactoring `app/Models/Pemesanan.php`:**
```php
class Pemesanan extends Model {
    const STATUS_UNPAID = 'menunggu_pembayaran';
    const STATUS_PENDING_CONFIRMATION = 'menunggu_konfirmasi'; // Status transisi setelah upload
    const STATUS_CONFIRMED = 'dikonfirmasi';
    const STATUS_COMPLETED = 'selesai';
    const STATUS_CANCELLED = 'batal';
}
```

**Penggunaan di Controller:**
```php
// SEBELUM:
$pemesanan->update(['status_pemesanan' => 'batal']);

// SESUDAH:
$pemesanan->update(['status_pemesanan' => Pemesanan::STATUS_CANCELLED]);
```

### B. Standarisasi Bahasa (Rekomendasi Utama: Bahasa Indonesia)
Mengingat tabel transaksi utama (`pemesanan`, `pembayaran`, `review`) sudah menggunakan **Bahasa Indonesia**, disarankan untuk menyamakan status operasional (`kamar_units`, `penempatan_kamars`) ke dalam Bahasa Indonesia agar konsisten dan mudah dipahami oleh tim pengembang lokal.

**Usulan Perubahan (Mapping):**
- **Unit Kamar**:
    - `available` -> `'tersedia'`
    - `occupied` -> `'terisi'`
    - `maintenance` -> `'perbaikan'`
- **Penempatan Kamar**:
    - `pending` -> `'menunggu'`
    - `assigned` -> `'ditetapkan'`
    - `checked_in` -> `'masuk'`
    - `checked_out` -> `'keluar'`
    - `cancelled` -> `'batal'`

*Dengan penyamaan ini, seluruh sistem akan menggunakan satu bahasa yang seragam.*

### C. Mapping Status Global
Buatlah file helper atau documentation khusus yang memetakan alur status:
1.  **New Booking** -> `STATUS_UNPAID`
2.  **Upload Bukti** -> `STATUS_PENDING_CONFIRMATION`
3.  **Admin Approve** -> `STATUS_CONFIRMED`
4.  **Check-out & Clean** -> `STATUS_COMPLETED`
