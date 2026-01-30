<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pemesanan;
use App\Models\Promo;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HandleExpiredBookings extends Command
{
    protected $signature = 'booking:handle-expired';
    protected $description = 'Batalkan pesanan expired dan kembalikan kuota promo';

    public function handle()
    {
        // Cari pesanan yang statusnya menunggu & waktunya sudah lewat
        $expiredBookings = Pemesanan::where('status_pemesanan', 'menunggu_pembayaran')
            ->where('expired_at', '<', Carbon::now())
            ->get();

        foreach ($expiredBookings as $booking) {
            DB::transaction(function () use ($booking) {
                // 1. Ubah status jadi Batal
                $booking->update([
                    'status_pemesanan' => 'batal',
                    'dibatalkan_oleh' => 'system',
                    'dibatalkan_at' => now(),
                    'catatan' => 'Otomatis dibatalkan oleh sistem (Expired)'
                ]);

                // 2. KEMBALIKAN KUOTA PROMO (Decrement)
                if ($booking->promo_id) {
                    Promo::where('id', $booking->promo_id)->decrement('kuota_terpakai');
                }

                // 3. (Opsional) Lepaskan unit kamar di tabel penempatan_kamar
                // Agar kamar bisa dibooking orang lain lagi
                // $booking->detailPemesanans->each(function($detail){ ... });
                $booking->detailPemesanans->each(function ($detail) {

                    // Update status semua unit yang terkait dengan detail ini menjadi 'cancelled'
                    // Pastikan kolom 'status_penempatan' ada di tabel penempatan_kamars
                    $detail->penempatanKamars()->update([
                        'status_penempatan' => 'cancelled',
                        'catatan' => 'Dibatalkan otomatis oleh sistem (Expired)',
                        'dibatalkan_oleh' => 'system'
                    ]);

                    // ALTERNATIF: Jika Anda lebih suka menghapus datanya (Hard Delete)
                    // $detail->penempatanKamars()->delete();
                });

            });
            $this->info("Booking ID {$booking->id} dibatalkan & kuota dikembalikan.");
        }
    }
}