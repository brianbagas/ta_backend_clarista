<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Pemesanan; // Sesuaikan nama model
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
// Jalankan setiap menit

Schedule::command('booking:handle-expired')->everyMinute();
Schedule::command('app:deactivate-expired-promos')->daily();
