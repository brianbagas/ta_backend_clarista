-- Query untuk cek ketersediaan kamar Standard Room
-- Tanggal: 2026-02-01 s/d 2026-02-02

-- 1. Total Unit Fisik Standard Room yang Available
SELECT 
    k.tipe_kamar,
    COUNT(ku.id) as total_unit_fisik
FROM kamars k
LEFT JOIN kamar_units ku ON k.id_kamar = ku.kamar_id
WHERE k.tipe_kamar = 'Standard Room'
  AND ku.status_unit = 'available'
GROUP BY k.tipe_kamar;

-- 2. Unit yang Terisi (via PenempatanKamar)
SELECT 
    pk.kamar_unit_id,
    ku.nomor_unit,
    p.status_pemesanan,
    p.tanggal_check_in,
    p.tanggal_check_out,
    pk.status_penempatan,
    pk.created_at as penempatan_created,
    pk.updated_at as penempatan_updated
FROM penempatan_kamars pk
JOIN kamar_units ku ON pk.kamar_unit_id = ku.id
JOIN detail_pemesanans dp ON pk.detail_pemesanan_id = dp.id
JOIN pemesanans p ON dp.pemesanan_id = p.id
JOIN kamars k ON ku.kamar_id = k.id_kamar
WHERE k.tipe_kamar = 'Standard Room'
  AND p.status_pemesanan != 'batal'
  AND p.tanggal_check_in < '2026-02-02'
  AND p.tanggal_check_out > '2026-02-01'
ORDER BY pk.kamar_unit_id, pk.updated_at DESC;

-- 3. Cek duplikasi PenempatanKamar untuk unit yang sama
SELECT 
    kamar_unit_id,
    COUNT(*) as jumlah_record
FROM penempatan_kamars pk
JOIN detail_pemesanans dp ON pk.detail_pemesanan_id = dp.id
JOIN pemesanans p ON dp.pemesanan_id = p.id
WHERE p.status_pemesanan != 'batal'
  AND p.tanggal_check_in < '2026-02-02'
  AND p.tanggal_check_out > '2026-02-01'
GROUP BY kamar_unit_id
HAVING COUNT(*) > 1;
