<?php

namespace Tests\Feature;

use App\Models\Pemesanan;
use App\Models\User;
use App\Models\Promo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CancellationTest extends TestCase
{
    // use RefreshDatabase; // Use this if you have a test DB set up, otherwise be careful.
    // Given existing project structure, I'll rely on current DB or transactions if possible.
    // Ideally, we use RefreshDatabase trait. Let's assume standard Laravel testing setup.
    use RefreshDatabase;

    public function test_user_can_cancel_unpaid_reservation()
    {
        // 1. Create User
        $user = User::factory()->create();

        // 2. Create Reservation (Status: menunggu_pembayaran)
        $pemesanan = Pemesanan::create([
            'user_id' => $user->id,
            'status_pemesanan' => 'menunggu_pembayaran',
            'kode_booking' => 'TEST-CANCEL-01',
            'tanggal_check_in' => now()->addDays(1),
            'tanggal_check_out' => now()->addDays(2),
            'total_bayar' => 500000,
            'expired_at' => now()->addHour(),
        ]);

        // 3. Act: Call Cancel Endpoint
        $response = $this->actingAs($user)
            ->postJson("/api/pemesanan/{$pemesanan->id}/cancel");

        // 4. Assert response and database
        $response->assertStatus(200)
            ->assertJson(['message' => 'Pemesanan berhasil dibatalkan.']);

        $this->assertDatabaseHas('pemesanans', [
            'id' => $pemesanan->id,
            'status_pemesanan' => 'batal',
        ]);
    }

    public function test_user_cannot_cancel_others_reservation()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $pemesanan = Pemesanan::create([
            'user_id' => $owner->id,
            'status_pemesanan' => 'menunggu_pembayaran',
            'kode_booking' => 'TEST-CANCEL-02',
            'tanggal_check_in' => now()->addDays(1),
            'tanggal_check_out' => now()->addDays(2),
            'total_bayar' => 500000,
            'expired_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($otherUser)
            ->postJson("/api/pemesanan/{$pemesanan->id}/cancel");

        $response->assertStatus(403);
    }

    public function test_user_cannot_cancel_processed_reservation()
    {
        $user = User::factory()->create();

        $pemesanan = Pemesanan::create([
            'user_id' => $user->id,
            'status_pemesanan' => 'confirmed', // Already paid/confirmed
            'kode_booking' => 'TEST-CANCEL-03',
            'tanggal_check_in' => now()->addDays(1),
            'tanggal_check_out' => now()->addDays(2),
            'total_bayar' => 500000,
            'expired_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/pemesanan/{$pemesanan->id}/cancel");

        $response->assertStatus(400);
    }
}
