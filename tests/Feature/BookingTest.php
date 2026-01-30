<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Kamar;
use App\Models\KamarUnit;
use App\Models\Pemesanan;
use App\Models\DetailPemesanan;
use App\Models\PenempatanKamar;
use App\Models\Promo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected $customer;
    protected $owner;
    protected $kamar;

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

        // Create kamar
        $this->kamar = Kamar::create([
            'tipe_kamar' => 'Deluxe Room',
            'harga' => 350000,
            'deskripsi' => 'Kamar nyaman dengan AC',
            'jumlah_total' => 5,
            'status_ketersediaan' => true,
        ]);

        // Create kamar units
        for ($i = 1; $i <= 5; $i++) {
            KamarUnit::create([
                'kamar_id' => $this->kamar->id_kamar,
                'nomor_unit' => '10' . $i,
                'status_unit' => 'available'
            ]);
        }
    }

    /** @test */
    public function customer_can_check_room_availability()
    {
        $response = $this->getJson('/api/cek-ketersediaan?' . http_build_query([
            'check_in' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out' => Carbon::tomorrow()->addDays(2)->format('Y-m-d')
        ]));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_kamar',
                        'tipe_kamar',
                        'harga',
                        'total_fisik',
                        'sisa_kamar',
                        'is_available'
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Response data should not be empty');
        $this->assertEquals(5, $data[0]['sisa_kamar']);
        $this->assertTrue($data[0]['is_available']);
    }

    /** @test */
    public function customer_can_create_booking()
    {
        $token = $this->customer->createToken('test-token')->plainTextToken;

        $checkIn = Carbon::tomorrow()->format('Y-m-d');
        $checkOut = Carbon::tomorrow()->addDays(2)->format('Y-m-d');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/pemesanan', [
                    'tanggal_check_in' => $checkIn,
                    'tanggal_check_out' => $checkOut,
                    'kamars' => [
                        [
                            'kamar_id' => $this->kamar->id_kamar,
                            'jumlah_kamar' => 2
                        ]
                    ]
                ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'tanggal_check_in',
                    'tanggal_check_out',
                    'total_bayar',
                    'status_pemesanan',
                    'expired_at'
                ]
            ]);

        $this->assertDatabaseHas('pemesanans', [
            'user_id' => $this->customer->id,
            'status_pemesanan' => 'menunggu_pembayaran'
        ]);

        $this->assertDatabaseHas('detail_pemesanans', [
            'kamar_id' => $this->kamar->id_kamar,
            'jumlah_kamar' => 2
        ]);

        // Check penempatan kamar created
        $this->assertEquals(2, PenempatanKamar::count());
    }

    /** @test */
    public function booking_calculates_total_correctly()
    {
        $token = $this->customer->createToken('test-token')->plainTextToken;

        $checkIn = Carbon::tomorrow()->format('Y-m-d');
        $checkOut = Carbon::tomorrow()->addDays(2)->format('Y-m-d'); // 2 nights

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/pemesanan', [
                    'tanggal_check_in' => $checkIn,
                    'tanggal_check_out' => $checkOut,
                    'kamars' => [
                        [
                            'kamar_id' => $this->kamar->id_kamar,
                            'jumlah_kamar' => 2
                        ]
                    ]
                ]);

        $expectedTotal = 350000 * 2 * 2; // harga * nights * jumlah_kamar

        $response->assertJson([
            'data' => [
                'total_bayar' => $expectedTotal
            ]
        ]);
    }

    /** @test */
    public function booking_with_promo_applies_discount()
    {
        $promo = Promo::create([
            'nama_promo' => 'Diskon 50rb',
            'kode_promo' => 'DISKON50',
            'tipe_diskon' => 'nominal',
            'nilai_diskon' => 50000,
            'berlaku_mulai' => Carbon::yesterday(),
            'berlaku_selesai' => Carbon::tomorrow()->addDays(10),
            'is_active' => true,
            'kuota' => 10,
            'kuota_terpakai' => 0,
            'min_transaksi' => 500000
        ]);

        $token = $this->customer->createToken('test-token')->plainTextToken;

        $checkIn = Carbon::tomorrow()->format('Y-m-d');
        $checkOut = Carbon::tomorrow()->addDays(2)->format('Y-m-d');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/pemesanan', [
                    'tanggal_check_in' => $checkIn,
                    'tanggal_check_out' => $checkOut,
                    'kamars' => [
                        [
                            'kamar_id' => $this->kamar->id_kamar,
                            'jumlah_kamar' => 2
                        ]
                    ],
                    'kode_promo' => 'DISKON50'
                ]);

        $subtotal = 350000 * 2 * 2;
        $expectedTotal = $subtotal - 50000;

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'total_bayar' => $expectedTotal
                ]
            ]);

        // Check promo quota increased
        $this->assertDatabaseHas('promos', [
            'id' => $promo->id,
            'kuota_terpakai' => 1
        ]);
    }

    /** @test */
    public function cannot_book_if_rooms_not_available()
    {
        // Create existing booking that uses all rooms
        $existingBooking = Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::tomorrow(),
            'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
            'total_bayar' => 1000000,
            'status_pemesanan' => 'dikonfirmasi',
            'expired_at' => Carbon::now()->addHour()
        ]);

        $detail = DetailPemesanan::create([
            'pemesanan_id' => $existingBooking->id,
            'kamar_id' => $this->kamar->id_kamar,
            'jumlah_kamar' => 5,
            'harga_per_malam' => 350000
        ]);

        // Create penempatan for all units
        $units = KamarUnit::where('kamar_id', $this->kamar->id_kamar)->get();
        foreach ($units as $unit) {
            PenempatanKamar::create([
                'detail_pemesanan_id' => $detail->id,
                'kamar_unit_id' => $unit->id,
                'status_penempatan' => 'assigned'
            ]);
        }

        // Try to book
        $token = $this->customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/pemesanan', [
                    'tanggal_check_in' => Carbon::tomorrow()->format('Y-m-d'),
                    'tanggal_check_out' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
                    'kamars' => [
                        [
                            'kamar_id' => $this->kamar->id_kamar,
                            'jumlah_kamar' => 1
                        ]
                    ]
                ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false
            ]);
    }

    /** @test */
    public function customer_can_cancel_their_booking()
    {
        $booking = Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::tomorrow(),
            'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
            'total_bayar' => 700000,
            'status_pemesanan' => 'menunggu_pembayaran',
            'expired_at' => Carbon::now()->addHour()
        ]);

        $token = $this->customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/pemesanan/{$booking->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status_pemesanan' => 'batal',
                    'dibatalkan_oleh' => 'customer'
                ]
            ]);

        $this->assertDatabaseHas('pemesanans', [
            'id' => $booking->id,
            'status_pemesanan' => 'batal'
        ]);
    }

    /** @test */
    public function customer_cannot_cancel_confirmed_booking()
    {
        $booking = Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::tomorrow(),
            'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
            'total_bayar' => 700000,
            'status_pemesanan' => 'dikonfirmasi',
            'expired_at' => Carbon::now()->addHour()
        ]);

        $token = $this->customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/pemesanan/{$booking->id}/cancel");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false
            ]);
    }

    /** @test */
    public function customer_can_only_view_their_own_bookings()
    {
        $otherCustomer = User::factory()->customer()->create([
            'name' => 'Other Customer',
            'email' => 'other@example.com',
            'password' => Hash::make('password123'),
        ]);

        Pemesanan::create([
            'user_id' => $otherCustomer->id,
            'tanggal_check_in' => Carbon::tomorrow(),
            'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
            'total_bayar' => 700000,
            'status_pemesanan' => 'menunggu_pembayaran',
            'expired_at' => Carbon::now()->addHour()
        ]);

        $token = $this->customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/pemesanan');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    /** @test */
    public function owner_can_view_all_bookings()
    {
        Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::tomorrow(),
            'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
            'total_bayar' => 700000,
            'status_pemesanan' => 'menunggu_pembayaran',
            'expired_at' => Carbon::now()->addHour()
        ]);

        $token = $this->owner->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/pemesanan');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    /** @test */
    public function owner_can_cancel_booking_with_reason()
    {
        $booking = Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::tomorrow(),
            'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
            'total_bayar' => 700000,
            'status_pemesanan' => 'dikonfirmasi',
            'expired_at' => Carbon::now()->addHour()
        ]);

        $token = $this->owner->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/admin/pemesanan/{$booking->id}/cancel", [
                    'alasan' => 'Kamar sedang maintenance mendadak'
                ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $this->assertDatabaseHas('pemesanans', [
            'id' => $booking->id,
            'status_pemesanan' => 'batal',
            'dibatalkan_oleh' => 'owner',
            'alasan_batal' => 'Kamar sedang maintenance mendadak'
        ]);
    }
}
