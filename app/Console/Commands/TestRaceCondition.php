<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use App\Models\User;
use App\Models\Kamar;
use Illuminate\Support\Str;

class TestRaceCondition extends Command
{
    protected $signature = 'test:race-condition {--url=http://127.0.0.1:8000}';
    protected $description = 'Simulate concurrent booking to test race conditions';

    public function handle()
    {
        $this->info("Simulating truly concurrent booking to: " . $this->option('url'));

        // Pastikan ada kamar untuk di-test
        $kamar = Kamar::first();
        if (!$kamar) {
            $this->error("Tidak ada kamar untuk ditest.");
            return;
        }

        // Ambil atau buat 2 User testing
        $user1 = User::firstOrCreate(
            ['email' => 'racetest1@example.com'],
            ['name' => 'Race Tester 1', 'password' => bcrypt('password'), 'role_id' => 2]
        );
        $user2 = User::firstOrCreate(
            ['email' => 'racetest2@example.com'],
            ['name' => 'Race Tester 2', 'password' => bcrypt('password'), 'role_id' => 2]
        );

        $token1 = $user1->createToken('test1')->plainTextToken;
        $token2 = $user2->createToken('test2')->plainTextToken;

        $checkIn = now()->addDays(30)->format('Y-m-d');
        $checkOut = now()->addDays(31)->format('Y-m-d');

        // Cari tau sisa kamar di tanggal tersebut
        $sisaKamar = $kamar->getAvailableUnits($checkIn, $checkOut, 100)->count();

        if ($sisaKamar === 0) {
            $this->error("Kamar {$kamar->tipe_kamar} sudah full di tanggal $checkIn. Tolong ubah tanggal di source code atau kosongkan data.");
            return;
        }

        $this->info("Kamar {$kamar->tipe_kamar} (ID: {$kamar->id_kamar}) memiliki SISA: {$sisaKamar} unit.");
        $this->info("Kita akan mensimulasikan 2 orang memesan SEMUA sisa unit ({$sisaKamar}) secara bersamaan (di detik/milidetik yang sama).");
        $this->warn("Jika Race Condition terjadi, KEDUANYA akan berhasil (Overbooking).");
        $this->warn("Jika sudah fix (karena lockForUpdate), SALAH SATU akan gagal (422/500).\n");

        $payload = [
            'tanggal_check_in' => $checkIn,
            'tanggal_check_out' => $checkOut,
            'kamars' => [
                [
                    'kamar_id' => $kamar->id_kamar,
                    'jumlah_kamar' => $sisaKamar // Pesan semua sisa kamar
                ]
            ]
        ];

        // Jalankan 2 request BERSAMAAN menggunakan Http Pool (curl_multi)
        $responses = Http::pool(fn(Pool $pool) => [
            $pool->as('req1')->withToken($token1)->post($this->option('url') . '/api/pemesanan', $payload),
            $pool->as('req2')->withToken($token2)->post($this->option('url') . '/api/pemesanan', $payload),
        ]);

        $status1 = $responses['req1']->status();
        $status2 = $responses['req2']->status();

        $this->line("--- HASIL ---");
        $this->info("Response 1 status: " . $status1);
        $this->info("Response 1 message: " . json_decode($responses['req1']->body(), true)['message'] ?? 'Tidak ada pesan');

        $this->info("Response 2 status: " . $status2);
        $this->info("Response 2 message: " . json_decode($responses['req2']->body(), true)['message'] ?? 'Tidak ada pesan');

        $this->line("\n--- KESIMPULAN ---");
        if ($status1 == 201 && $status2 == 201) {
            $this->error("❌ GAGAL! RACE CONDITION TERJADI. Kedua pesanan masuk padahal unit tidak cukup.");
            $this->error("Database lock (lockForUpdate) sepertinya tidak bekerja atau transaksi tidak terisolasi.");
        } else if (($status1 == 201 && $status2 != 201) || ($status1 != 201 && $status2 == 201)) {
            $this->info("✅ BERHASIL! Race condition tertangani dengan baik berkat `lockForUpdate`.");
            $this->info("Hanya 1 request yang tembus, yang lainnya ditolak (menunggu antrian lock lalu kehabisan stok).");
        } else {
            $this->warn("Keduanya gagal (Status $status1 & $status2). Mungkin ada error validasi validasi atau server down.");
        }
    }
}
