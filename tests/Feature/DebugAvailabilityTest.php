<?php

namespace Tests\Feature;

use App\Models\Kamar;
use App\Models\KamarUnit;
use App\Models\DetailPemesanan;
use App\Models\Pemesanan;
use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['role' => 'owner']);
        Role::create(['role' => 'customer']);
        $this->user = User::factory()->create(['role_id' => 2]);
    }

    /** @test */
    public function debug_availability_logic()
    {
        // 1. Setup Kamar: Total 5 rooms
        $kamar = Kamar::factory()->create(['jumlah_total' => 5, 'status_ketersediaan' => 1]);
        KamarUnit::factory()->count(5)->create(['kamar_id' => $kamar->id_kamar]);

        // 2. Create CANCELLED Booking for 2 rooms
        $booking = Pemesanan::factory()->create([
            'user_id' => $this->user->id,
            'tanggal_check_in' => Carbon::now()->addDays(1)->format('Y-m-d'),
            'tanggal_check_out' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'status_pemesanan' => 'batal'
        ]);

        DetailPemesanan::factory()->create([
            'pemesanan_id' => $booking->id,
            'kamar_id' => $kamar->id_kamar,
            'jumlah_kamar' => 2
        ]);

        // 3. Action: Check availability
        \DB::enableQueryLog();
        $checkIn = Carbon::now()->addDays(2)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(4)->format('Y-m-d');
        $response = $this->getJson("/api/cek-ketersediaan?check_in={$checkIn}&check_out={$checkOut}");
        $log = \DB::getQueryLog();
        dump($log);

        $data = $response->json('data');

        echo "\nDEBUG OUTPUT:\n";
        echo "Total Fisik: " . ($data[0]['total_fisik'] ?? 'NULL') . "\n";
        echo "Sisa Kamar: " . ($data[0]['sisa_kamar'] ?? 'NULL') . "\n";
        echo "Status DB: " . Pemesanan::find($booking->id)->status_pemesanan . "\n";

        $response->assertStatus(200);

        if (($data[0]['sisa_kamar'] ?? 0) != 5) {
            throw new \Exception("TEST FAILED: Expected 5, got " . ($data[0]['sisa_kamar'] ?? 'NULL'));
        }
    }
}
