<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
// routes/api.php
use App\Http\Controllers\KamarController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\HomestayContentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PemesananController;
use App\Http\Controllers\Auth\ApiAuthController;
use App\Http\Controllers\KamarUnitsController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\PenempatanKamarController;
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->get('/cek-siapa-saya', function (Request $request) {
    return response()->json([
        'message' => 'Token Valid!',
        'user_id' => $request->user()->id,
        'nama' => $request->user()->name, // atau username
        'role' => $request->user()->role, // atau role->name
    ]);
});

use Laravel\Telescope\Http\Controllers\HomeController;
Route::get('/review/latest', [ReviewController::class, 'getFeaturedReviews']);

Route::get('/review', [ReviewController::class, 'index']);
Route::apiResource('review', ReviewController::class);
// routes/api.php
Route::apiResource('/kamar_units', KamarUnitsController::class);
Route::post('/register', [ApiAuthController::class, 'register']);
Route::post('/login', [ApiAuthController::class, 'login']);
Route::apiResource('kamar', KamarController::class);
Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::put('/profil', [ProfileController::class, 'update']);
    Route::get('/pemesanan/{pemesanan}', [PemesananController::class, 'show']);
    Route::get('/pemesanan', [PemesananController::class, 'index']);
    Route::post('/pemesanan', [PemesananController::class, 'store']);
    Route::post('/pemesanan/{pemesanan}/pembayaran', [PembayaranController::class, 'store']);
    Route::post('/review', [ReviewController::class, 'store']);
});
Route::get('/promo/latest', [PromoController::class, 'latest']);
Route::apiResource('promo', PromoController::class);

Route::post('/promo/validate', [PromoController::class, 'validatePromo']);

// Rute Privat (butuh login) untuk mengupdate konten
// Route::middleware('auth:sanctum')->group(function () {
//     // ... rute privat lain

//     // Gunakan POST untuk update karena bisa membawa file gambar
//     Route::post('/content/homepage/update', [HomestayContentController::class, 'update']);
// });

// Route::controller(HomestayContentController::class)->middleware(['auth:api_owner', 'check.role.owner'])->group(function () {
//     // Rute untuk mengelola konten homestay
//     Route::get('/content/homepage', 'show')->name('content.homepage.show');
//     Route::post('/content/homepage/update', 'update')->name('content.homepage.update');
// });
Route::get('/content/homepage', [HomestayContentController::class, 'show']);

Route::middleware(['auth:sanctum', 'role:owner'])->group(function () {
    Route::post('/content/homepage/update', [HomestayContentController::class, 'update']);
    Route::get('/admin/pemesanan/{pemesanan}', [PemesananController::class, 'showForOwner']); // <-- Tambahkan ini
    Route::get('/admin/pemesanan', [PemesananController::class, 'indexOwner']); // <-- Tambahkan ini
    Route::get('/admin/pembayaran/verifikasi', [PembayaranController::class, 'indexForOwner']);
    Route::post('/admin/pembayaran/verifikasi/{pemesanan}', [PembayaranController::class, 'verifikasi']);
    Route::get('/admin/review', [ReviewController::class, 'indexForOwner']);
    Route::put('/admin/review/{review}', [ReviewController::class, 'updateStatus']);
    Route::post('/admin/pemesanan-offline', [PemesananController::class, 'storeOffline']);

});
Route::get('/laporan', [LaporanController::class, 'index']);
Route::middleware(['auth:sanctum', 'role:owner'])->group(function () {
    Route::get('/admin/promo/{promo}', [PromoController::class, 'showForOwner']); // <-- Tambahkan ini
    Route::get('/admin/promo/{promo}', [PromoController::class, 'indexby']); // <-- Tambahkan ini
    Route::get('/admin/promo', [PromoController::class, 'index']); // <-- Tambahkan ini
    Route::post('/admin/promo', [PromoController::class, 'store']);
    Route::put('/admin/promo/{promo}', [PromoController::class, 'update']);
    Route::delete('/admin/promo/{promo}', [PromoController::class, 'destroy']);
    Route::post('/admin/addkamar', [KamarController::class, 'addKamar']);

    Route::post('/admin/check-in', [PenempatanKamarController::class, 'checkIn']);
    Route::post('/admin/check-out/{id}', [PenempatanKamarController::class, 'checkOut']);

    Route::post('/admin/kamar-unit/{id}/set-available', [PenempatanKamarController::class, 'setAvailable']);
    Route::get('/admin/kalender-data', [LaporanController::class, 'getKalenderData']);

});


Route::get('/cek-ketersediaan', [KamarController::class, 'cekKetersediaan']);





Route::post('/cek-promo', [PromoController::class, 'checkPromo']);
