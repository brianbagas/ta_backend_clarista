<?php
// Script untuk cek duplikasi PenempatanKamar
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CEK DUPLIKASI PENEMPATAN KAMAR ===\n\n";

// Cek unit yang punya lebih dari 1 penempatan aktif untuk tanggal yang sama
$duplicates = DB::select("
    SELECT 
        pk.kamar_unit_id,
        ku.nomor_unit,
        k.tipe_kamar,
        COUNT(*) as jumlah_penempatan,
        GROUP_CONCAT(CONCAT('Pemesanan #', p.id, ' (', p.status_pemesanan, ')') SEPARATOR ', ') as detail_pemesanan
    FROM penempatan_kamars pk
    JOIN kamar_units ku ON pk.kamar_unit_id = ku.id
    JOIN kamars k ON ku.kamar_id = k.id_kamar
    JOIN detail_pemesanans dp ON pk.detail_pemesanan_id = dp.id
    JOIN pemesanans p ON dp.pemesanan_id = p.id
    WHERE p.status_pemesanan != 'batal'
      AND pk.status_penempatan NOT IN ('cancelled', 'checked_out')
      AND p.tanggal_check_in < '2026-02-02'
      AND p.tanggal_check_out > '2026-02-01'
    GROUP BY pk.kamar_unit_id, ku.nomor_unit, k.tipe_kamar
    HAVING COUNT(*) > 1
");

if (empty($duplicates)) {
    echo "✓ Tidak ada duplikasi penempatan untuk tanggal 2026-02-01 s/d 2026-02-02\n";
} else {
    echo "✗ DITEMUKAN DUPLIKASI:\n";
    foreach ($duplicates as $dup) {
        echo "  - Unit #{$dup->nomor_unit} ({$dup->tipe_kamar}): {$dup->jumlah_penempatan} penempatan aktif\n";
        echo "    Detail: {$dup->detail_pemesanan}\n";
    }
}

echo "\n=== CEK KETERSEDIAAN PER TIPE KAMAR ===\n\n";

$availability = DB::select("
    SELECT 
        k.tipe_kamar,
        COUNT(DISTINCT ku.id) as total_unit_fisik,
        COUNT(DISTINCT CASE 
            WHEN pk.id IS NOT NULL 
            AND p.status_pemesanan != 'batal'
            AND pk.status_penempatan NOT IN ('cancelled', 'checked_out')
            AND p.tanggal_check_in < '2026-02-02'
            AND p.tanggal_check_out > '2026-02-01'
            THEN ku.id 
        END) as unit_terisi,
        (COUNT(DISTINCT ku.id) - COUNT(DISTINCT CASE 
            WHEN pk.id IS NOT NULL 
            AND p.status_pemesanan != 'batal'
            AND pk.status_penempatan NOT IN ('cancelled', 'checked_out')
            AND p.tanggal_check_in < '2026-02-02'
            AND p.tanggal_check_out > '2026-02-01'
            THEN ku.id 
        END)) as unit_tersedia
    FROM kamars k
    LEFT JOIN kamar_units ku ON k.id_kamar = ku.kamar_id AND ku.status_unit = 'available'
    LEFT JOIN penempatan_kamars pk ON ku.id = pk.kamar_unit_id
    LEFT JOIN detail_pemesanans dp ON pk.detail_pemesanan_id = dp.id
    LEFT JOIN pemesanans p ON dp.pemesanan_id = p.id
    WHERE k.status_ketersediaan = 1
    GROUP BY k.tipe_kamar
");

foreach ($availability as $avail) {
    echo "{$avail->tipe_kamar}:\n";
    echo "  Total Unit: {$avail->total_unit_fisik}\n";
    echo "  Terisi: {$avail->unit_terisi}\n";
    echo "  Tersedia: {$avail->unit_tersedia}\n\n";
}

echo "=== SELESAI ===\n";
