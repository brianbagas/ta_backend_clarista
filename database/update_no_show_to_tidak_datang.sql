-- Script SQL untuk update status no_show menjadi tidak_datang
-- Jalankan di database Clarista Homestay

-- 1. Update tabel pemesanans
UPDATE pemesanans 
SET status_pemesanan = 'tidak_datang' 
WHERE status_pemesanan = 'no_show';

-- 2. Cek hasil update
SELECT id, kode_booking, status_pemesanan, catatan 
FROM pemesanans 
WHERE status_pemesanan = 'tidak_datang';

-- 3. (Opsional) Update catatan jika masih ada teks "no-show"
UPDATE pemesanans 
SET catatan = REPLACE(catatan, 'no-show', 'tidak datang')
WHERE catatan LIKE '%no-show%';

UPDATE pemesanans 
SET catatan = REPLACE(catatan, 'No-show', 'Tidak datang')
WHERE catatan LIKE '%No-show%';

-- 4. Update penempatan_kamars jika ada catatan no-show
UPDATE penempatan_kamars 
SET catatan = REPLACE(catatan, 'No-show', 'Tidak datang')
WHERE catatan LIKE '%No-show%';

UPDATE penempatan_kamars 
SET catatan = REPLACE(catatan, 'no-show', 'tidak datang')
WHERE catatan LIKE '%no-show%';

-- 5. Verifikasi tidak ada lagi no_show
SELECT COUNT(*) as jumlah_no_show 
FROM pemesanans 
WHERE status_pemesanan = 'no_show';
-- Harusnya return 0
