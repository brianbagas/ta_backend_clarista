<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\KamarController;
use Illuminate\Support\Facades\Route;
use App\Models\Pemesanan;
use App\Mail\PesananDikonfirmasi;
use App\Mail\PesananDibatalkan;
use App\Mail\PembayaranDitolak;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::apiResource('kamar', KamarController::class);

});

// ========================================
// 📧 EMAIL PREVIEW ROUTES (Development Only)
// ========================================

Route::get('/preview-email/pesanan-dikonfirmasi', function () {
    $pemesanan = Pemesanan::with(['user', 'detailPemesanans.kamar'])->first();

    if (!$pemesanan) {
        return "❌ Belum ada data pemesanan. Buat pemesanan dulu!";
    }

    return new PesananDikonfirmasi($pemesanan);
});

Route::get('/preview-email/pesanan-dibatalkan', function () {
    $pemesanan = Pemesanan::with(['user', 'detailPemesanans.kamar'])->first();

    if (!$pemesanan) {
        return "❌ Belum ada data pemesanan. Buat pemesanan dulu!";
    }

    // Set data pembatalan untuk preview
    $pemesanan->alasan_batal = 'Kamar sedang dalam perbaikan mendadak';
    $pemesanan->dibatalkan_oleh = 'owner';
    $pemesanan->dibatalkan_at = now();

    return new PesananDibatalkan($pemesanan);
});

Route::get('/preview-email/pembayaran-ditolak', function () {
    $pemesanan = Pemesanan::with(['user', 'detailPemesanans.kamar'])->first();

    if (!$pemesanan) {
        return "❌ Belum ada data pemesanan. Buat pemesanan dulu!";
    }

    $catatanAdmin = 'Bukti transfer tidak jelas. Mohon upload ulang dengan foto yang lebih terang dan menampilkan seluruh informasi transfer (nama pengirim, jumlah, tanggal, bank tujuan).';

    return new PembayaranDitolak($pemesanan, $catatanAdmin);
});

Route::get('/preview-email/pembayaran-masuk', function () {
    $pemesanan = Pemesanan::with(['user', 'detailPemesanans.kamar'])->first();

    if (!$pemesanan) {
        return "❌ Belum ada data pemesanan. Buat pemesanan dulu!";
    }

    return new \App\Mail\PembayaranMasuk($pemesanan);
});

// Route lama (backward compatibility)
Route::get('/preview-email', function () {
    $pemesanan = Pemesanan::with(['user', 'detailPemesanans.kamar'])->first();

    if (!$pemesanan) {
        return "Belum ada data pemesanan di database. Buat 1 dulu via Postman/Web!";
    }

    return new PesananDikonfirmasi($pemesanan);
});

require __DIR__ . '/auth.php';

Route::get('/{any}', function () {
    return view('index');
})->where('any', '.*');
