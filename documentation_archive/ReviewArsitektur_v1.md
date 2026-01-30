# Review Arsitektur Sistem - Clarista Homestay v1.0

Dokumen ini berisi analisis teknis mendalam mengenai arsitektur backend Laravel yang telah diimplementasikan dan lolos pengujian (87/87 Passed).

## 1. Pencegahan Race Condition: Database Transactions & Pessimistic Locking

Salah satu tantangan terbesar dalam sistem reservasi adalah **Race Condition** atau **Double Booking**, di mana dua user memesan kamar yang sama di waktu yang bersamaan.

### Implementasi
Pada method `PemesananController::store`, sistem menerapkan mekanisme pertahanan berlapis:

1.  **Database Transaction (`DB::beginTransaction`)**:
    Seluruh proses booking (Validasi ketersediaan, Create Pemesanan, Create Detail, Create Penempatan Kamar) dibungkus dalam satu transaksi atomik. Jika salah satu step gagal, `DB::rollBack()` akan membatalkan semua perubahan.

2.  **Pessimistic Locking (`lockForUpdate`)**:
    Sistem tidak hanya mengecek ketersediaan, tetapi **mengunci baris data** unit kamar yang sedang dipilih sebelum dibooking oleh user lain.
    ```php
    $availableUnits = KamarUnit::where('kamar_id', $kamar->id_kamar)
        ->whereNotIn('id', $occupiedUnitIds)
        // ...
        ->lockForUpdate() // <--- KUNCI DATABASE
        ->get();
    ```
    Perintah ini memastikan bahwa selama transaksi A berjalan, query B yang mencoba membaca atau mengubah baris yang sama harus menunggu transaksi A selesai (commit/rollback). Ini secara efektif menghilangkan kemungkinan double booking pada level database.

## 2. Standarisasi API dengan Trait

Untuk menjaga konsistensi respon JSON antar controller, sistem menggunakan `App\Traits\ApiResponseTrait`.

-   **Konsistensi**: Semua endpoint menjamin struktur respon memiliki key `success` (boolean), `message` (string), dan `data` (mixed).
-   **Maintenance**: Perubahan format respon standar hanya perlu dilakukan di satu file trait, tidak perlu mengubah puluhan controller.
-   **Fungsi Utility**: Menyediakan method helper `successResponse($data, $message, $code)` dan `errorResponse($message, $code, $errors)` yang ringkas.

```php
// Contoh Penggunaan di Controller
return $this->successResponse($data, 'Berhasil diambil');
return $this->errorResponse('Validasi Gagal', 422, $errors);
```

## 3. Analisis Batasan Sistem

### Mengapa Tidak Ada Refund Otomatis?
Sistem ini dibuat dengan model bisnis homestay skala kecil-menengah yang mengutamakan cash-flow sederhana.
-   **Integritas Keuangan**: Refund otomatis membutuhkan integrasi mendalam dengan payment gateway yang kompleks dan memiliki biaya transaksi (MDR) serta biaya transfer balik.
-   **Kebijakan Homestay**: Kebanyakan homestay lokal menerapkan kebijakan *strict* atau *manual refund* by request untuk menghindari kerugian akibat cancellation mendadak.
-   **Kompleksitas**: Mengelola state partial refund atau refund gagal akan meningkatkan kompleksitas kode secara signifikan di luar scope MVP (Minimum Viable Product).

### Mengapa Tidak Menggunakan Payment Gateway (Midtrans/Xendit)?
Sistem saat ini menggunakan **Bukti Transfer Manual**.
-   **Cost Efficiency**: Menghindari potongan biaya layanan per transaksi (biasanya Rp 4.000 - Rp 5.000 untuk VA) yang membebani margin homestay kecil.
-   **Verifikasi Manual**: Owner menginginkan kontrol penuh untuk memverifikasi uang masuk ke rekening secara mutasi sebelum check-in.
-   **Kecukupan Operasional**: Dengan volume transaksi homestay yang tidak setinggi hotel berbintang, verifikasi manual oleh admin masih sangat *manageable*.

## 4. Saran Pengembangan (Future Work)

Meskipun sistem v1.0 sudah stabil, berikut adalah saran untuk iterasi v2.0:

1.  **Integrasi Midtrans (Payment Gateway)**
    -   Menggantikan upload bukti transfer dengan Virtual Account atau QRIS otomatis.
    -   Callback/Webhook dari Midtrans dapat men-trigger update status `menunggu_pembayaran` -> `dikonfirmasi` secara realtime tanpa campur tangan admin.

2.  **Sistem Pembatalan Otomatis (Cron Job)**
    -   Saat ini, pemesanan yang expired mungkin masih menggantung statunya sampai admin mengecek.
    -   Disarankan menggunakan **Laravel Scheduler** (Cron) yang berjalan tiap menit:
        `$schedule->command('booking:expire')->everyMinute();`
    -   Command ini akan otomatis mencari booking status `menunggu_pembayaran` yang melewati `expired_at` dan mengubahnya menjadi `batal` serta melepas `KamarUnit` yang terkunci.

3.  **Email Notification Queue**
    -   Memindahkan proses pengiriman email notifikasi ke **Queue Worker** (Redis/Database) agar respon API saat booking menjadi lebih cepat dan tidak terhambat proses SMTP.

---
**Kesimpulan**: Kode saat ini sangat solid untuk penanganan integritas data (ACID compliance) namun masih tradisional dalam hal metode pembayaran, yang mana adalah keputusan trade-off yang valid untuk skala bisnis homestay.
