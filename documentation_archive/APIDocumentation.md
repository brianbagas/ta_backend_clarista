# API Documentation - Clarista Homestay v1.1

**Base URL**: `http://localhost:8000/api` (Disesuaikan dengan local/production environment)
**Authentication**: Bearer Token (Laravel Sanctum)

---

## 1. Authentication (Public)

### Register Customer
*Mendaftarkan pengguna baru dengan role customer.*
- **Endpoint**: `POST /register`
- **Headers**: `Accept: application/json`
- **Request Body**:
  ```json
  {
    "name": "Bagas Doe",
    "email": "bagas@example.com",
    "password": "password123"
  }
  ```
- **Response (201 Created)**:
  ```json
  {
    "status": "success",
    "message": "Registrasi berhasil",
    "data": {
      "access_token": "4|...token_string...",
      "token_type": "Bearer",
      "name": "Bagas Doe",
      "email": "bagas@example.com",
      "role": "customer"
    }
  }
  ```

### Login
*Masuk ke sistem untuk mendapatkan Access Token.*
- **Endpoint**: `POST /login`
- **Request Body**:
  ```json
  {
    "email": "bagas@example.com",
    "password": "password123"
  }
  ```
- **Response (200 OK)**:
  ```json
  {
    "status": "success",
    "message": "Login berhasil",
    "data": {
      "access_token": "...",
      "token_type": "Bearer",
      "role": "customer"
    }
  }
  ```
- **Error (401 Unauthorized)**: Kredensial salah.

---

## 2. Public Data (Guest Access)

### List Kamar
*Melihat daftar tipe kamar.*
- **Endpoint**: `GET /kamar`
- **Response**: Array data kamar, termasuk relasi `images`.

### Detail Kamar
*Melihat detail satu tipe kamar.*
- **Endpoint**: `GET /kamar/{id}`

### Cek Ketersediaan (Penting)
*Mengecek ketersediaan kamar pada tanggal tertentu.*
- **Endpoint**: `GET /cek-ketersediaan`
- **Query Params**:
  - `check_in`: YYYY-MM-DD
  - `check_out`: YYYY-MM-DD
- **Response**: List kamar beserta `sisa_kamar` dan `is_available` (boolean).
  ```json
  {
    "data": [
      {
        "id_kamar": 1,
        "tipe_kamar": "Deluxe",
        "total_fisik": 10,
        "sisa_kamar": 2, // Hasil kalkulasi overlapping booking
        "is_available": true
      }
    ]
  }
  ```

### List Promo
*Melihat promo yang sedang aktif.*
- **Endpoint**: `GET /promo`

### Validate Promo Check
*Pre-validasi kode promo sebelum booking.*
- **Endpoint**: `POST /cek-promo`
- **Request Body**:
  ```json
  {
    "kode_promo": "LEBARAN2025",
    "total_transaksi": 500000
  }
  ```
- **Response**: Mengembalikan nilai potongan harga.
- **Error (400)**: Transaksi kurang dari minimum atau kuota habis.

---

## 3. Customer Actions (Auth Required)

### Get User Profile
- **Endpoint**: `GET /user`
- **Headers**: `Authorization: Bearer {token}`

### Update Profile
- **Endpoint**: `PUT /profil`
- **Request Body**: `name`, `email` (opsional).

### Create Booking (Pemesanan)
*Membuat pesanan baru. Sistem akan me-lock unit kamar dan kuota promo secara atomik.*
- **Endpoint**: `POST /pemesanan`
- **Request Body**:
  ```json
  {
    "tanggal_check_in": "2025-06-01",
    "tanggal_check_out": "2025-06-03",
    "kamars": [
      {
        "kamar_id": 1,
        "jumlah_kamar": 1
      }
    ],
    "kode_promo": "DISKON10" // Optional
  }
  ```
- **Response (201 Created)**: Mengembalikan object `Pemesanan` dengan status `menunggu_pembayaran` dan `expired_at`.
- **Error (409 Conflict)**: Kuota promo habis saat race condition.
- **Error (422 Unprocessable)**: Stok kamar tidak mencukupi.

### List My Bookings
*Melihat riwayat pesanan user login.*
- **Endpoint**: `GET /pemesanan`

### Upload Bukti Bayar
- **Endpoint**: `POST /pemesanan/{id}/pembayaran`
- **Request Body**: `Multipart/Form-Data`
  - `bukti_bayar`: File (jpg, png)
  - `jumlah_bayar`: Numeric (harus sama dengan `total_bayar`)
  - `bank_pengirim`: string
  - `nama_pengirim`: string
- **Effect**: Mengubah status pemesanan menjadi `menunggu_konfirmasi`.

### Cancel Booking
- **Endpoint**: `POST /pemesanan/{id}/cancel`
- **Effect**: Mengubah status menjadi `batal` dan mengembalikan kuota promo jika dipakai. Hanya bisa dilakukan jika status masih `menunggu_pembayaran`.

### Post Review
*Memberikan ulasan setelah pesanan selesai.*
- **Endpoint**: `POST /review`
- **Request Body**:
  ```json
  {
    "pemesanan_id": 123, // Harus milik user yang login dan status 'selesai'
    "rating": 5, // 1-5
    "komentar": "Sangat nyaman!"
  }
  ```

---

## 4. Owner / Admin Actions (Role 'owner')

### Dashboard & Laporan
- **Endpoint**: `GET /laporan` (Ringkasan pendapatan bulan ini)
- **Endpoint**: `GET /admin/kalender-data` (Data okupansi untuk kalender visual)

### Manajemen Kamar (Physical Units)
- **Add Kamar & Auto-Generate Units**: `POST /admin/kamar`
  - Input `jumlah_total` akan otomatis membuat N baris data di `kamar_units`.
- **Set Unit Status**: `PUT /admin/kamar-units/{id}` (Manual override: maintenance/available)

### Operasional Check-In / Check-Out
- **Check-In Tamu**: `POST /admin/check-in`
  - **Body**: `{ "detail_pemesanan_id": X, "kamar_unit_id": Y }`
  - **Effect**: Status unit menjadi `assigned` (di tabel penempatan).
- **Check-Out Tamu**: `POST /admin/check-out/{id_penempatan}`
  - **Effect**: Status penempatan `checked_out`, status unit **otomatis berubah** jadi `maintenance` (perlu dibersihkan).
- **Set Unit Available (After Cleaning)**: `POST /admin/kamar-unit/{id}/set-available`
  - **Effect**: Mengembalikan unit dari `maintenance` ke `available` agar bisa dijual kembali.

### Manajemen Pemesanan
- **Verifikasi Pembayaran**: `POST /admin/pembayaran/verifikasi/{id}`
  - **Body**: `{ "status": "dikonfirmasi" }` (atau `batal`)
  - **Effect**: Mengupdate status pemesanan. Email konfirmasi terkirim (jika setup mailer aktif).
- **Cancel by Owner**: `POST /admin/pemesanan/{id}/cancel`
  - **Body**: `{ "alasan": "..." }`
- **Booking Offline (Walk-In)**: `POST /admin/pemesanan-offline`
  - **Body**: Nama, No HP, CheckIn, Durasi, KamarID, Qty.
  - **Effect**: User dibuat otomatis (jika belum ada), Pesanan langsung `dikonfirmasi`, Pembayaran langsung `verified` (via Cash), Unit langsung auto-assign.

### Manage Promos
- **CRUD**: `GET|POST|PUT|DELETE /admin/promo`

---

## Status Codes Summary
- `200`: Success
- `201`: Created (Resource baru berhasil dibuat)
- `400`: Bad Request (Logic bisnis menolak, misal kuota habis atau status tidak valid)
- `401`: Unauthorized (Token tidak valid/tidak ada)
- `403`: Forbidden (Akses ditolak, misal User mencoba akses fitur Owner)
- `422`: Unprocessable Entity (Validasi input gagal)
- `409`: Conflict (Race condition resource, misal rebutan tiket terakhir)
- `500`: Server Error
