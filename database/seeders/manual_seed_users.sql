-- ============================================
-- SQL SEED DATA USER untuk Clarista Homestay
-- ============================================
-- File ini berisi data seed untuk tabel users dan roles
-- Password default untuk semua user: "password"
-- ============================================

-- ============================================
-- TROUBLESHOOTING: Jika masih error 401
-- ============================================
-- 1. Pastikan SQL ini sudah dijalankan di phpMyAdmin PRODUCTION (shared hosting)
-- 2. Cek apakah data sudah masuk: SELECT * FROM users;
-- 3. Cek apakah roles sudah ada: SELECT * FROM roles;
-- 4. Pastikan email yang digunakan login PERSIS sama: owner@clarista.com
-- 5. Pastikan password yang diketik: password (huruf kecil semua)
-- ============================================

-- ============================================
-- 1. INSERT DATA ROLES (Jika belum ada)
-- ============================================
INSERT INTO `roles` (`id`, `role`, `deskripsi`, `created_at`, `updated_at`) VALUES
(1, 'owner', 'Pemilik atau Administrator utama sistem. Memiliki hak akses penuh untuk manajemen data, verifikasi, dan laporan.', NOW(), NOW()),
(2, 'customer', 'Pengguna umum yang dapat melakukan pendaftaran, melihat kamar, dan membuat pemesanan online.', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    deskripsi = VALUES(deskripsi),
    updated_at = NOW();

-- ============================================
-- 2. INSERT DATA USERS
-- ============================================
-- HAPUS USER LAMA JIKA ADA (untuk menghindari duplicate)
DELETE FROM users WHERE email IN ('owner@clarista.com', 'customer@clarista.com');

-- Password Hash untuk "password" 
-- Hash Standard Laravel (bcrypt cost 10): $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- Ini adalah hash yang paling kompatibel dengan semua versi PHP

-- User Owner
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `role_id`, `no_hp`, `gender`, `created_at`, `updated_at`) VALUES
(1, 'Admin Clarista', 'owner@clarista.com', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 1, '081234567890', 'pria', NOW(), NOW());

-- User Customer
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `role_id`, `no_hp`, `gender`, `created_at`, `updated_at`) VALUES
(2, 'Customer Clarista', 'customer@clarista.com', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 2, '081234567891', 'wanita', NOW(), NOW());

-- ============================================
-- VERIFIKASI DATA BERHASIL MASUK
-- ============================================
-- Jalankan query ini untuk memastikan data sudah masuk:
-- SELECT id, name, email, role_id FROM users WHERE email IN ('owner@clarista.com', 'customer@clarista.com');
-- 
-- Hasilnya harus menampilkan 2 baris:
-- id=1, name=Admin Clarista, email=owner@clarista.com, role_id=1
-- id=2, name=Customer Clarista, email=customer@clarista.com, role_id=2
-- ============================================

-- ============================================
-- INFORMASI LOGIN:
-- ============================================
-- Owner Account:
--   Email: owner@clarista.com
--   Password: password
--   Role: owner
--
-- Customer Account:
--   Email: customer@clarista.com
--   Password: password
--   Role: customer
-- ============================================

-- ============================================
-- JIKA MASIH ERROR 401 SETELAH SQL INI:
-- ============================================
-- Kemungkinan penyebab:
-- 1. SQL belum dijalankan di database PRODUCTION (shared hosting)
-- 2. Salah ketik email atau password saat login
-- 3. Cache browser - coba clear cache atau gunakan incognito mode
-- 4. CORS issue - pastikan API URL sudah benar di frontend
-- 
-- Cara cek manual di phpMyAdmin:
-- 1. Buka phpMyAdmin di shared hosting
-- 2. Pilih database Clarista
-- 3. Klik tab SQL
-- 4. Jalankan: SELECT * FROM users WHERE email = 'owner@clarista.com';
-- 5. Pastikan ada 1 baris hasil dengan password hash yang panjang
-- ============================================

-- ============================================
-- ALTERNATIF: Buat User Baru dengan Password Custom
-- ============================================
-- Jika Anda ingin membuat user dengan password berbeda:
-- 1. Buka terminal di server/local Laravel
-- 2. Jalankan: php artisan tinker
-- 3. Ketik: Hash::make('password_anda')
-- 4. Copy hash yang dihasilkan
-- 5. Ganti hash di query INSERT di atas dengan hash baru
-- ============================================
