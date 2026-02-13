<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Handle CORS preflight requests
Route::options('{any}', function () {
    return response()->noContent();
})->where('any', '.*');

// ===============================================================================================
// 1. PUBLIC ROUTES (Akses Bebas)
// ===============================================================================================

// Auth
Route::post('/register', [ApiAuthController::class, 'register']);
Route::post('/login', [ApiAuthController::class, 'login']);

// Kamar (Read Only untuk Customer/Publik)
Route::get('/kamar', [KamarController::class, 'index']);
Route::get('/kamar/{kamar}', [KamarController::class, 'show']);
Route::get('/cek-ketersediaan', [KamarController::class, 'cekKetersediaan']);
Route::get('/bank-accounts', [App\Http\Controllers\BankAccountController::class, 'index']);

// Reviews
Route::get('/review', [ReviewController::class, 'index']);
Route::get('/review/latest', [ReviewController::class, 'getFeaturedReviews']);

// Promo & Content
Route::get('/promo/latest', [PromoController::class, 'latest']);
Route::get('/promo', [PromoController::class, 'index']); // Public list
Route::post('/promo/check', [PromoController::class, 'checkPromo'])->middleware('throttle:30,1');
Route::get('/content/homepage', [HomestayContentController::class, 'show']);

// ===============================================================================================
// 2. AUTHENTICATED ROUTES (Umum)
// ===============================================================================================
// routes/api.php

Route::middleware('auth:sanctum')->get('/cek-siapa-saya', [ApiAuthController::class, 'checkMe']);
Route::middleware('auth:sanctum')->get('/user', [ApiAuthController::class, 'getUser']);

Route::middleware('auth:sanctum')->post('/logout', [ApiAuthController::class, 'logout']);
Route::middleware('auth:sanctum')->put('/password', [ProfileController::class, 'updatePassword']);
Route::middleware('auth:sanctum')->put('/profil', [ProfileController::class, 'update']);

// ===============================================================================================
// 3. CUSTOMER ROUTES (Role: Customer)
// ===============================================================================================
Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    // Route::put('/profil', [ProfileController::class, 'update']); // Moved to general auth

    // Pemesanan
    Route::get('/pemesanan', [PemesananController::class, 'index']);
    Route::post('/pemesanan', [PemesananController::class, 'store']);
    Route::get('/pemesanan/{pemesanan}', [PemesananController::class, 'show']);
    Route::post('/pemesanan/{pemesanan}/cancel', [PemesananController::class, 'cancel']);
    Route::post('/pemesanan/{pemesanan}/pembayaran', [PembayaranController::class, 'store']);

    // Review
    Route::post('/review', [ReviewController::class, 'store']);
});

// ===============================================================================================
// 4. OWNER ROUTES (Role: Owner)
// ===============================================================================================
Route::middleware(['auth:sanctum', 'role:owner'])->group(function () {

    // --- Manajemen Promo (Explicit) ---
    Route::get('/admin/promo', [PromoController::class, 'indexForOwner']);
    Route::post('/admin/promo', [PromoController::class, 'store']);
    Route::get('/admin/promo/{promo}', [PromoController::class, 'showForOwner']);
    Route::put('/admin/promo/{promo}', [PromoController::class, 'update']);
    Route::delete('/admin/promo/{promo}', [PromoController::class, 'destroy']);

    // --- Manajemen Kamar (Explicit as requested) ---
    // Menggunakan POST /admin/kamar untuk addKamar (Create dengan Unit)
    Route::get('/admin/kamar', [KamarController::class, 'index']); // Bisa reuse index atau buat indexOwner jika perlu
    Route::post('/admin/kamar', [KamarController::class, 'addKamar']); // <-- Custom logic creation
    Route::put('/admin/kamar/{kamar}', [KamarController::class, 'update']);
    Route::delete('/admin/kamar/{kamar}', [KamarController::class, 'destroy']);
    Route::delete('/admin/kamar-image/{id}', [KamarController::class, 'deleteImage']); // New Route

    // --- Manajemen Pemesanan & Pembayaran ---
    Route::get('/admin/pemesanan', [PemesananController::class, 'indexOwner']);
    Route::get('/admin/pemesanan/{pemesanan}', [PemesananController::class, 'showForOwner']);
    Route::post('/admin/pemesanan-offline', [PemesananController::class, 'storeOffline']);
    Route::post('/admin/pemesanan/{pemesanan}/cancel', [PemesananController::class, 'cancelByOwner']);
    Route::post('/admin/pemesanan/{pemesanan}/mark-no-show', [PemesananController::class, 'markAsNoShow']);


    Route::get('/admin/pembayaran-notifikasi', [PembayaranController::class, 'getPembayaranNotifikasi']);

    Route::get('/admin/pembayaran/verifikasi', [PembayaranController::class, 'indexForOwner']);
    Route::get('/admin/pembayaran/verifikasi/{pemesanan}', [PembayaranController::class, 'showVerificationDetail']); // <-- New Route
    Route::post('/admin/pembayaran/verifikasi/{pemesanan}', [PembayaranController::class, 'verifikasi']);

    // --- Manajemen Review ---
    Route::get('/admin/review', [ReviewController::class, 'indexForOwner']);
    Route::put('/admin/review/{review}', [ReviewController::class, 'updateStatus']);
    Route::delete('/admin/review/{review}', [ReviewController::class, 'destroy']);

    // --- Manajemen Konten Homepage ---
    Route::post('/content/homepage/update', [HomestayContentController::class, 'update']);

    // --- Laporan & Kalender ---
    Route::get('/laporan', [LaporanController::class, 'index']);
    Route::get('/laporan/export-pdf', [LaporanController::class, 'exportPdf']);
    Route::get('/admin/dashboard-stats', [LaporanController::class, 'dashboard']); // New Route
    Route::get('/admin/kalender-data', [LaporanController::class, 'getKalenderData']);

    // --- Operasional Check-In/Out ---
    Route::post('/admin/check-in', [PenempatanKamarController::class, 'checkIn']);
    Route::post('/admin/check-out/{id}', [PenempatanKamarController::class, 'checkOut']);
    Route::post('/admin/ganti-unit', [PenempatanKamarController::class, 'gantiUnit']);
    Route::get('/admin/available-units', [PenempatanKamarController::class, 'getAvailableUnits']); // <-- Baru
    Route::post('/admin/kamar-unit/{id}/set-available', [PenempatanKamarController::class, 'setAvailable']);
    Route::put('/admin/kamar-units/{id}', [PenempatanKamarController::class, 'setAvailable']);

    // --- Manajemen Kamar Units (Dirty/Maintenance) ---
    Route::get('/admin/kamar-dirty', [KamarUnitsController::class, 'indexdirty']);

    // --- Manajemen Bank Account (Owner) ---
    Route::get('/admin/bank-accounts', [App\Http\Controllers\BankAccountController::class, 'indexForOwner']);
    Route::post('/admin/bank-accounts', [App\Http\Controllers\BankAccountController::class, 'store']);
    Route::put('/admin/bank-accounts/{bankAccount}', [App\Http\Controllers\BankAccountController::class, 'update']);
    Route::delete('/admin/bank-accounts/{bankAccount}', [App\Http\Controllers\BankAccountController::class, 'destroy']);

    // --- Data Terhapus (Soft Delete Management) ---
    // Kamar
    Route::get('/admin/trashed/kamar', [KamarController::class, 'trashed']);
    Route::post('/admin/trashed/kamar/{id}/restore', [KamarController::class, 'restore']);
    Route::delete('/admin/trashed/kamar/{id}', [KamarController::class, 'forceDelete']);

    // Kamar Unit
    Route::get('/admin/trashed/kamar-unit', [KamarUnitsController::class, 'trashed']);
    Route::post('/admin/trashed/kamar-unit/{id}/restore', [KamarUnitsController::class, 'restore']);
    Route::delete('/admin/trashed/kamar-unit/{id}', [KamarUnitsController::class, 'forceDelete']);

    // Promo
    Route::get('/admin/trashed/promo', [PromoController::class, 'trashed']);
    Route::post('/admin/trashed/promo/{id}/restore', [PromoController::class, 'restore']);
    Route::delete('/admin/trashed/promo/{id}', [PromoController::class, 'forceDelete']);

    // Pemesanan
    Route::get('/admin/trashed/pemesanan', [PemesananController::class, 'trashed']);
    Route::post('/admin/trashed/pemesanan/{id}/restore', [PemesananController::class, 'restore']);
    Route::delete('/admin/trashed/pemesanan/{id}', [PemesananController::class, 'forceDelete']);

    // Pembayaran
    Route::get('/admin/trashed/pembayaran', [PembayaranController::class, 'trashed']);
    Route::post('/admin/trashed/pembayaran/{id}/restore', [PembayaranController::class, 'restore']);
    Route::delete('/admin/trashed/pembayaran/{id}', [PembayaranController::class, 'forceDelete']);

    // Review
    Route::get('/admin/trashed/review', [ReviewController::class, 'trashed']);
    Route::post('/admin/trashed/review/{id}/restore', [ReviewController::class, 'restore']);
    Route::delete('/admin/trashed/review/{id}', [ReviewController::class, 'forceDelete']);

    // Bank Account
    Route::get('/admin/trashed/bank-account', [App\Http\Controllers\BankAccountController::class, 'trashed']);
    Route::post('/admin/trashed/bank-account/{id}/restore', [App\Http\Controllers\BankAccountController::class, 'restore']);
    Route::delete('/admin/trashed/bank-account/{id}', [App\Http\Controllers\BankAccountController::class, 'forceDelete']);


    // --- Misc ---
    Route::apiResource('/kamar-units', KamarUnitsController::class); // Jika owner butuh akses direct ke unit
});
