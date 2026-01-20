<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Pemesanan; // Sesuaikan nama model
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
// Jalankan setiap menit
Schedule::call(function () {
    // Cari pesanan yang 'menunggu_pembayaran' DAN sudah lewat waktu expired-nya
    Pemesanan::where('status_pemesanan', 'menunggu_pembayaran')
        ->where('expired_at', '<', now()) // Jika waktu expired lebih kecil dari sekarang
        ->update(['status_pemesanan' => 'batal']);
        
})->everyMinute();
Schedule::command('booking:handle-expired')->everyMinute();
