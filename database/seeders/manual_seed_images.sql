-- ============================================
-- SQL SEED DATA GAMBAR untuk Clarista Homestay
-- ============================================
-- File ini berisi data seed untuk tabel kamar_images
-- Menambahkan gambar placeholder untuk kamar yang sudah di-seed
-- ============================================

-- ============================================
-- INSERT DATA GAMBAR KAMAR
-- ============================================
-- Gambar untuk Deluxe Room (kamar_id = 1)
INSERT INTO `kamar_images` (`id`, `kamar_id`, `image_path`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'storage/kamars/deluxe-room-1.jpg', NOW(), NOW(), NULL),
(2, 1, 'storage/kamars/deluxe-room-2.jpg', NOW(), NOW(), NULL);

-- Gambar untuk Standard Room (kamar_id = 2)
INSERT INTO `kamar_images` (`id`, `kamar_id`, `image_path`, `created_at`, `updated_at`, `deleted_at`) VALUES
(3, 2, 'storage/kamars/standard-room-1.jpg', NOW(), NOW(), NULL),
(4, 2, 'storage/kamars/standard-room-2.jpg', NOW(), NOW(), NULL);

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- 1. File gambar di atas adalah PATH PLACEHOLDER
-- 2. Anda perlu upload gambar fisik ke folder storage/kamars/ di shared hosting
-- 3. Atau, biarkan kosong dan aplikasi akan menampilkan gambar placeholder otomatis
-- 4. Jika ingin menggunakan URL eksternal (Unsplash), ubah image_path menjadi:
--    'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?q=80&w=1000'
-- 
-- ALTERNATIF: Gunakan URL Unsplash langsung (tidak perlu upload file)
-- ============================================

-- Hapus data di atas jika ingin menggunakan URL eksternal, lalu jalankan ini:
/*
DELETE FROM kamar_images WHERE kamar_id IN (1, 2);

INSERT INTO `kamar_images` (`id`, `kamar_id`, `image_path`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?q=80&w=1000&auto=format&fit=crop', NOW(), NOW(), NULL),
(2, 1, 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?q=80&w=1000&auto=format&fit=crop', NOW(), NOW(), NULL),
(3, 2, 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?q=80&w=1000&auto=format&fit=crop', NOW(), NOW(), NULL),
(4, 2, 'https://images.unsplash.com/photo-1595526114035-0d45ed16cfbf?q=80&w=1000&auto=format&fit=crop', NOW(), NOW(), NULL);
*/
