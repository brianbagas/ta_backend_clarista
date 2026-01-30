<?php

namespace Tests\Feature;

use App\Models\Kamar;
use App\Models\KamarUnit;
use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['role' => 'owner']);
        Role::create(['role' => 'customer']);

        $this->customer = User::factory()->customer()->create();
        $this->actingAs($this->customer); // Default acting as customer
    }

    /** @test */
    public function customer_can_create_booking_successfully_happy_path()
    {
        // 1. Setup Kamar with sufficient units
        $kamar = Kamar::factory()->create(['jumlah_total' => 2, 'harga' => 100000]);
        // Create Available Units
        KamarUnit::factory()->create(['kamar_id' => $kamar->id_kamar, 'status_unit' => 'available']);
        KamarUnit::factory()->create(['kamar_id' => $kamar->id_kamar, 'status_unit' => 'available']);

        $checkIn = Carbon::tomorrow()->format('Y-m-d');
        $checkOut = Carbon::tomorrow()->addDays(2)->format('Y-m-d');

        // 2. Action: Create Booking
        $response = $this->postJson('/api/pemesanan', [
            'tanggal_check_in' => $checkIn,
            'tanggal_check_out' => $checkOut,
            'kamars' => [
                [
                    'kamar_id' => $kamar->id_kamar,
                    'jumlah_kamar' => 1
                ]
            ]
        ]);

        // 3. Assert
        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => ['id', 'kode_booking']]);

        // Verify Database
        $this->assertDatabaseHas('pemesanans', [
            'user_id' => $this->customer->id,
            'total_bayar' => 200000, // 100k * 1 room * 2 nights
        ]);

        $this->assertDatabaseHas('detail_pemesanans', [
            'kamar_id' => $kamar->id_kamar,
            'jumlah_kamar' => 1
        ]);

        // Ensure PenempatanKamar created (unit assigned)
        $this->assertDatabaseCount('penempatan_kamars', 1);
    }

    /** @test */
    public function booking_fails_when_stock_insufficient_unhappy_path()
    {
        // 1. Setup Kamar with ONLY 1 Unit
        $kamar = Kamar::factory()->create(['jumlah_total' => 1]);
        KamarUnit::factory()->create(['kamar_id' => $kamar->id_kamar]);

        $checkIn = Carbon::tomorrow()->format('Y-m-d');
        $checkOut = Carbon::tomorrow()->addDays(2)->format('Y-m-d');

        // 2. Action: Try to book 2 rooms
        $response = $this->postJson('/api/pemesanan', [
            'tanggal_check_in' => $checkIn,
            'tanggal_check_out' => $checkOut,
            'kamars' => [
                [
                    'kamar_id' => $kamar->id_kamar,
                    'jumlah_kamar' => 2 // Requesting more than available
                ]
            ]
        ]);

        // 3. Assert
        $response->assertStatus(422); // Unprocessable Entity
    }

    /** @test */
    public function booking_validation_dates_logic()
    {
        $kamar = Kamar::factory()->create();

        // Scenario A: Check-out before Check-in
        $response = $this->postJson('/api/pemesanan', [
            'tanggal_check_in' => '2026-02-10',
            'tanggal_check_out' => '2026-02-09', // Error
            'kamars' => [['kamar_id' => $kamar->id_kamar, 'jumlah_kamar' => 1]]
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tanggal_check_out']);

        // Scenario B: Check-in in the past
        $response = $this->postJson('/api/pemesanan', [
            'tanggal_check_in' => '2020-01-01', // Past date
            'tanggal_check_out' => '2020-01-03',
            'kamars' => [['kamar_id' => $kamar->id_kamar, 'jumlah_kamar' => 1]]
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tanggal_check_in']);
    }
}
