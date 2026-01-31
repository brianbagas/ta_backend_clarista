<?php
// Debug Script untuk Cek Ketersediaan Kamar
// Jalankan: php debug_availability.php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\KamarUnit;
use App\Models\PenempatanKamar;
use App\Models\Kamar;
use Carbon\Carbon;

$checkIn = '2026-02-01';
$checkOut = '2026-02-02';

echo "=== DEBUG KETERSEDIAAN KAMAR ===\n";
echo "Check In: $checkIn\n";
echo "Check Out: $checkOut\n\n";

// Ambil semua kamar
$kamars = Kamar::all();

foreach ($kamars as $kamar) {
    echo "--- {$kamar->tipe_kamar} (ID: {$kamar->id_kamar}) ---\n";

    // Total unit fisik
    $totalUnits = KamarUnit::where('kamar_id', $kamar->id_kamar)
        ->where('status_unit', 'available')
        ->count();
    echo "Total Unit Fisik (Available): $totalUnits\n";

    // Unit yang terisi (menggunakan logic dari PemesananController)
    $occupiedUnitIds = PenempatanKamar::whereHas('detailPemesanan.pemesanan', function ($q) use ($checkIn, $checkOut) {
        $q->where('status_pemesanan', '!=', 'batal')
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->where('tanggal_check_in', '<', $checkOut)
                    ->where('tanggal_check_out', '>', $checkIn);
            });
    })->pluck('kamar_unit_id')->toArray();

    echo "Unit IDs yang Terisi: " . json_encode($occupiedUnitIds) . "\n";
    echo "Jumlah Unit Terisi: " . count($occupiedUnitIds) . "\n";

    // Available units
    $availableUnits = KamarUnit::where('kamar_id', $kamar->id_kamar)
        ->where('status_unit', 'available')
        ->whereNotIn('id', $occupiedUnitIds)
        ->get();

    echo "Unit yang Tersedia: " . $availableUnits->count() . "\n";
    echo "Detail Unit Tersedia:\n";
    foreach ($availableUnits as $unit) {
        echo "  - Unit #{$unit->nomor_unit} (ID: {$unit->id})\n";
    }

    // Cek detail penempatan untuk unit yang terisi
    if (count($occupiedUnitIds) > 0) {
        echo "\nDetail Penempatan yang Aktif:\n";
        $penempatans = PenempatanKamar::whereIn('kamar_unit_id', $occupiedUnitIds)
            ->with('detailPemesanan.pemesanan')
            ->get();

        foreach ($penempatans as $p) {
            $pemesanan = $p->detailPemesanan->pemesanan ?? null;
            if ($pemesanan) {
                echo "  - Unit ID: {$p->kamar_unit_id}, Status Pemesanan: {$pemesanan->status_pemesanan}, ";
                echo "Check In: {$pemesanan->tanggal_check_in}, Check Out: {$pemesanan->tanggal_check_out}\n";
            }
        }
    }

    echo "\n";
}

echo "=== SELESAI ===\n";
