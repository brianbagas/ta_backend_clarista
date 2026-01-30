<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Pemesanan;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected $customer;
    protected $owner;
    protected $pemesanan;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::create(['id' => 1, 'role' => 'owner']);
        Role::create(['id' => 2, 'role' => 'customer']);

        // Create users
        $this->customer = User::factory()->customer()->create([
            'name' => 'Customer User',
            'email' => 'customer@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->owner = User::factory()->owner()->create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Create completed pemesanan
        $this->pemesanan = Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::yesterday()->subDays(2),
            'tanggal_check_out' => Carbon::yesterday(),
            'total_bayar' => 1400000,
            'status_pemesanan' => 'selesai',
            'expired_at' => Carbon::now()->addHour()
        ]);
    }

    /** @test */
    public function customer_can_create_review_for_completed_booking()
    {
        $token = $this->customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/review', [
                    'pemesanan_id' => $this->pemesanan->id,
                    'rating' => 5,
                    'komentar' => 'Pelayanan sangat memuaskan!'
                ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true
            ]);

        $this->assertDatabaseHas('reviews', [
            'pemesanan_id' => $this->pemesanan->id,
            'rating' => 5,
            'komentar' => 'Pelayanan sangat memuaskan!'
        ]);
    }

    /** @test */
    public function cannot_review_non_completed_booking()
    {
        $pendingBooking = Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::tomorrow(),
            'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
            'total_bayar' => 1400000,
            'status_pemesanan' => 'dikonfirmasi',
            'expired_at' => Carbon::now()->addHour()
        ]);

        $token = $this->customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/review', [
                    'pemesanan_id' => $pendingBooking->id,
                    'rating' => 5,
                    'komentar' => 'Great!'
                ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false
            ]);
    }

    /** @test */
    public function cannot_review_same_booking_twice()
    {
        Review::create([
            'pemesanan_id' => $this->pemesanan->id,
            'rating' => 5,
            'komentar' => 'First review',
            'status' => 'disetujui'
        ]);

        $token = $this->customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/review', [
                    'pemesanan_id' => $this->pemesanan->id,
                    'rating' => 4,
                    'komentar' => 'Second review'
                ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function rating_must_be_between_1_and_5()
    {
        $token = $this->customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/review', [
                    'pemesanan_id' => $this->pemesanan->id,
                    'rating' => 6, // Invalid
                    'komentar' => 'Great!'
                ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    /** @test */
    public function can_get_approved_reviews()
    {
        Review::create([
            'pemesanan_id' => $this->pemesanan->id,
            'rating' => 5,
            'komentar' => 'Approved review',
            'status' => 'disetujui'
        ]);

        Review::create([
            'pemesanan_id' => Pemesanan::factory()->create()->id,
            'rating' => 3,
            'komentar' => 'Hidden review',
            'status' => 'disembunyikan'
        ]);

        $response = $this->getJson('/api/review');

        $response->assertStatus(200);

        $reviews = $response->json('data');
        $this->assertCount(1, $reviews);
        $this->assertEquals('Approved review', $reviews[0]['komentar']);
    }

    /** @test */
    public function owner_can_approve_review()
    {
        $review = Review::create([
            'pemesanan_id' => $this->pemesanan->id,
            'rating' => 5,
            'komentar' => 'Pending review',
            'status' => 'menunggu_persetujuan'
        ]);

        $token = $this->owner->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/admin/review/{$review->id}", [
                    'status' => 'disetujui'
                ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => 'disetujui'
        ]);
    }

    /** @test */
    public function owner_can_hide_review()
    {
        $review = Review::create([
            'pemesanan_id' => $this->pemesanan->id,
            'rating' => 1,
            'komentar' => 'Inappropriate review',
            'status' => 'menunggu_persetujuan'
        ]);

        $token = $this->owner->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/admin/review/{$review->id}", [
                    'status' => 'disembunyikan'
                ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => 'disembunyikan'
        ]);
    }

    /** @test */
    public function customer_cannot_moderate_reviews()
    {
        $review = Review::create([
            'pemesanan_id' => $this->pemesanan->id,
            'rating' => 5,
            'komentar' => 'Review',
            'status' => 'menunggu_persetujuan'
        ]);

        $token = $this->customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/admin/review/{$review->id}", [
                    'status' => 'disetujui'
                ]);

        $response->assertStatus(403);
    }
}
