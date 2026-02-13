<?php

namespace Tests\Feature;

use App\Models\Kamar;
use App\Models\KamarUnit;
use App\Models\DetailPemesanan;
use App\Models\PenempatanKamar;
use App\Models\Pemesanan;
use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KamarAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['role' => 'owner']);
        Role::create(['role' => 'customer']);

        // Setup User Customer for referencing if needed
        $this->user = User::factory()->customer()->create();
    }

    /** @test */
    public function check_availability_returns_correct_remaining_stock()
    {
        // 1. Setup Kamar: Total 5 rooms
        $kamar = Kamar::factory()->create([
            'jumlah_total' => 5,
            'status_ketersediaan' => true
        ]);

        // Create 5 physical units to be safe with any logic
        KamarUnit::factory()->count(5)->create(['kamar_id' => $kamar->id_kamar]);

        // 2. Create Conflicting Booking for 2 rooms on Feb 1-3
        $booking = Pemesanan::factory()->create([
            'user_id' => $this->user->id,
            'tanggal_check_in' => Carbon::now()->addDays(1)->format('Y-m-d'),
            'tanggal_check_out' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'status_pemesanan' => 'dikonfirmasi'
        ]);

        $detail = DetailPemesanan::factory()->create([
            'pemesanan_id' => $booking->id,
            'kamar_id' => $kamar->id_kamar,
            'jumlah_kamar' => 2
        ]);

        // Create PenempatanKamar (Critical for availability check)
        // We need to pick 2 units from the 5 created
        $units = KamarUnit::where('kamar_id', $kamar->id_kamar)->take(2)->get();
        foreach ($units as $unit) {
            PenempatanKamar::create([
                'detail_pemesanan_id' => $detail->id,
                'kamar_unit_id' => $unit->id,
                'tanggal_masuk' => $booking->tanggal_check_in,
                'tanggal_keluar' => $booking->tanggal_check_out,
                'status_penempatan' => 'assigned'
            ]);
        }

        // 3. Action: Check availability for Feb 2 (Inside the booking period)
        // 3. Action: Check availability for (Now + 2 days) -> Inside booking period
        $checkIn = Carbon::now()->addDays(2)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(4)->format('Y-m-d');
        $response = $this->getJson("/api/cek-ketersediaan?check_in={$checkIn}&check_out={$checkOut}");

        // 4. Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.0.id_kamar', $kamar->id_kamar)
            ->assertJsonPath('data.0.sisa_kamar', 3); // 5 Total - 2 Booked = 3 Available
    }

    /** @test */
    public function availability_check_ignores_cancelled_bookings()
    {
        // 1. Setup Kamar: Total 5 rooms
        $kamar = Kamar::factory()->create(['jumlah_total' => 5]);
        KamarUnit::factory()->count(5)->create(['kamar_id' => $kamar->id_kamar]);

        // 2. Create CANCELLED Booking for 2 rooms
        $booking = Pemesanan::factory()->create([
            'user_id' => $this->user->id,
            'tanggal_check_in' => Carbon::now()->addDays(1)->format('Y-m-d'),
            'tanggal_check_out' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'status_pemesanan' => 'batal' // Cancelled status
        ]);

        DetailPemesanan::factory()->create([
            'pemesanan_id' => $booking->id,
            'kamar_id' => $kamar->id_kamar,
            'jumlah_kamar' => 2
        ]);

        // 3. Action: Check availability
        // 3. Action: Check availability
        $checkIn = Carbon::now()->addDays(2)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(4)->format('Y-m-d');
        $response = $this->getJson("/api/cek-ketersediaan?check_in={$checkIn}&check_out={$checkOut}");

        // 4. Assert: Should still have 5 available (cancelled doesn't count)
        $response->assertStatus(200)
            ->assertJsonPath('data.0.sisa_kamar', 5);
    }
}
