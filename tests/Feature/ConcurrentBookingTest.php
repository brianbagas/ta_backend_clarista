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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * Test untuk membuktikan apakah sistem booking bisa menangani
 * 2 pemesanan yang terjadi bersamaan (race condition / concurrent booking).
 */
class ConcurrentBookingTest extends TestCase
{
    use RefreshDatabase;

    protected $customer1;
    protected $customer2;
    protected $kamar;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::create(['id' => 1, 'role' => 'owner']);
        Role::create(['id' => 2, 'role' => 'customer']);

        // Create 2 customers
        $this->customer1 = User::factory()->customer()->create([
            'name' => 'Customer Satu',
            'email' => 'customer1@test.com',
            'password' => Hash::make('password123'),
        ]);

        $this->customer2 = User::factory()->customer()->create([
            'name' => 'Customer Dua',
            'email' => 'customer2@test.com',
            'password' => Hash::make('password123'),
        ]);

        // Create kamar dengan hanya 2 unit (supaya mudah habis)
        $this->kamar = Kamar::create([
            'tipe_kamar' => 'Standard Room',
            'harga' => 300000,
            'deskripsi' => 'Kamar standard untuk test concurrency',
            'jumlah_total' => 2,
            'status_ketersediaan' => true,
        ]);

        // Create 2 kamar units
        KamarUnit::create([
            'kamar_id' => $this->kamar->id_kamar,
            'nomor_unit' => '201',
            'status_unit' => 'available'
        ]);
        KamarUnit::create([
            'kamar_id' => $this->kamar->id_kamar,
            'nomor_unit' => '202',
            'status_unit' => 'available'
        ]);
    }

    /**
     * @test
     * Skenario: 2 customer memesan SEMUA kamar (2 unit) di tanggal yang sama.
     * Yang pertama harus berhasil (201), yang kedua harus ditolak (422).
     *
     * Test ini mensimulasikan race condition secara sequential:
     * Customer 1 pesan dulu -> berhasil
     * Customer 2 pesan di tanggal sama -> harus gagal karena unit sudah terpakai
     */
    public function test_sequential_booking_same_rooms_second_should_fail()
    {
        $token1 = $this->customer1->createToken('test-token-1')->plainTextToken;
        $token2 = $this->customer2->createToken('test-token-2')->plainTextToken;

        $checkIn = Carbon::tomorrow()->format('Y-m-d');
        $checkOut = Carbon::tomorrow()->addDays(2)->format('Y-m-d');

        $payload = [
            'tanggal_check_in' => $checkIn,
            'tanggal_check_out' => $checkOut,
            'kamars' => [
                [
                    'kamar_id' => $this->kamar->id_kamar,
                    'jumlah_kamar' => 2 // Pesan SEMUA unit
                ]
            ]
        ];

        // ====== Customer 1 pesan duluan ======
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->postJson('/api/pemesanan', $payload);

        $response1->assertStatus(201)
            ->assertJson(['success' => true]);

        // Pastikan 2 penempatan kamar terbuat
        $this->assertEquals(2, PenempatanKamar::count(), 'Harus ada 2 penempatan kamar dari booking pertama');

        // ====== Customer 2 pesan di tanggal yang sama ======
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
        ])->postJson('/api/pemesanan', $payload);

        // HARUS DITOLAK karena semua unit sudah terpakai
        $response2->assertStatus(422)
            ->assertJson(['success' => false]);

        // Pastikan hanya ada 1 pemesanan di database
        $this->assertEquals(1, Pemesanan::count(), 'Hanya boleh ada 1 pemesanan yang berhasil');

        // Pastikan penempatan kamar tetap 2 (dari booking pertama saja)
        $this->assertEquals(2, PenempatanKamar::count(), 'Penempatan kamar harus tetap 2');
    }

    /**
     * @test
     * Skenario: 2 customer masing-masing pesan 1 unit dari total 2 unit.
     * Kedua booking harus berhasil karena masih ada stok.
     */
    public function test_two_bookings_within_capacity_both_should_succeed()
    {
        $token1 = $this->customer1->createToken('test-token-1')->plainTextToken;
        $token2 = $this->customer2->createToken('test-token-2')->plainTextToken;

        $checkIn = Carbon::tomorrow()->format('Y-m-d');
        $checkOut = Carbon::tomorrow()->addDays(2)->format('Y-m-d');

        $payloadSingle = [
            'tanggal_check_in' => $checkIn,
            'tanggal_check_out' => $checkOut,
            'kamars' => [
                [
                    'kamar_id' => $this->kamar->id_kamar,
                    'jumlah_kamar' => 1 // Pesan 1 unit saja
                ]
            ]
        ];

        // Customer 1 pesan 1 unit
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->postJson('/api/pemesanan', $payloadSingle);

        $response1->assertStatus(201)
            ->assertJson(['success' => true]);

        // Customer 2 pesan 1 unit (masih tersisa 1)
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
        ])->postJson('/api/pemesanan', $payloadSingle);

        $response2->assertStatus(201)
            ->assertJson(['success' => true]);

        // Harus ada 2 pemesanan
        $this->assertEquals(2, Pemesanan::count(), 'Harus ada 2 pemesanan karena stok cukup');

        // Masing-masing 1 penempatan, total 2
        $this->assertEquals(2, PenempatanKamar::count(), 'Harus ada 2 penempatan kamar');

        // Pastikan unit yang dipakai berbeda
        $usedUnits = PenempatanKamar::pluck('kamar_unit_id')->toArray();
        $this->assertCount(2, array_unique($usedUnits), 'Unit yang dipakai harus berbeda');
    }

    /**
     * @test
     * Skenario: 2 customer pesan 1 unit, tapi total hanya 2 dan sudah ada
     * 1 booking sebelumnya yang mengambil 1 unit. Customer kedua harus berhasil,
     * customer ketiga harus gagal.
     */
    public function test_third_booking_exceeds_capacity_should_fail()
    {
        $customer3 = User::factory()->customer()->create([
            'name' => 'Customer Tiga',
            'email' => 'customer3@test.com',
            'password' => Hash::make('password123'),
        ]);

        $token1 = $this->customer1->createToken('t1')->plainTextToken;
        $token2 = $this->customer2->createToken('t2')->plainTextToken;
        $token3 = $customer3->createToken('t3')->plainTextToken;

        $checkIn = Carbon::tomorrow()->format('Y-m-d');
        $checkOut = Carbon::tomorrow()->addDays(2)->format('Y-m-d');

        $payload = [
            'tanggal_check_in' => $checkIn,
            'tanggal_check_out' => $checkOut,
            'kamars' => [
                [
                    'kamar_id' => $this->kamar->id_kamar,
                    'jumlah_kamar' => 1
                ]
            ]
        ];

        // Customer 1 -> berhasil (sisa 1 unit)
        $r1 = $this->withHeaders(['Authorization' => 'Bearer ' . $token1])
            ->postJson('/api/pemesanan', $payload);
        $r1->assertStatus(201);

        // Customer 2 -> berhasil (sisa 0 unit)
        $r2 = $this->withHeaders(['Authorization' => 'Bearer ' . $token2])
            ->postJson('/api/pemesanan', $payload);
        $r2->assertStatus(201);

        // Customer 3 -> HARUS GAGAL (stok habis)
        $r3 = $this->withHeaders(['Authorization' => 'Bearer ' . $token3])
            ->postJson('/api/pemesanan', $payload);
        $r3->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertEquals(2, Pemesanan::count(), 'Hanya 2 pemesanan yang boleh berhasil');
    }

    /**
     * @test
     * Skenario: Customer 1 pesan 2 unit, lalu batal.
     * Setelah dibatalkan, Customer 2 harus bisa pesan 2 unit lagi.
     * Ini memastikan unit yang dibatalkan benar-benar "dilepas".
     */
    public function test_cancelled_booking_releases_units_for_new_booking()
    {
        $token1 = $this->customer1->createToken('t1')->plainTextToken;
        $token2 = $this->customer2->createToken('t2')->plainTextToken;

        $checkIn = Carbon::tomorrow()->format('Y-m-d');
        $checkOut = Carbon::tomorrow()->addDays(2)->format('Y-m-d');

        $payload = [
            'tanggal_check_in' => $checkIn,
            'tanggal_check_out' => $checkOut,
            'kamars' => [
                [
                    'kamar_id' => $this->kamar->id_kamar,
                    'jumlah_kamar' => 2
                ]
            ]
        ];

        // Customer 1 pesan semua
        $r1 = $this->withHeaders(['Authorization' => 'Bearer ' . $token1])
            ->postJson('/api/pemesanan', $payload);
        $r1->assertStatus(201);

        $bookingId = $r1->json('data.id');

        // Customer 2 coba pesan -> harus gagal
        $r2 = $this->withHeaders(['Authorization' => 'Bearer ' . $token2])
            ->postJson('/api/pemesanan', $payload);
        $r2->assertStatus(422);

        // Customer 1 batalkan pesanannya
        $cancelResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token1])
            ->postJson("/api/pemesanan/{$bookingId}/cancel");
        $cancelResponse->assertStatus(200);

        // Sekarang Customer 2 coba lagi -> HARUS BERHASIL karena unit sudah dilepas
        $r3 = $this->withHeaders(['Authorization' => 'Bearer ' . $token2])
            ->postJson('/api/pemesanan', $payload);
        $r3->assertStatus(201)
            ->assertJson(['success' => true]);

        // Pastikan ada 2 pemesanan: 1 batal, 1 aktif
        $this->assertEquals(2, Pemesanan::count());
        $this->assertEquals(1, Pemesanan::where('status_pemesanan', 'batal')->count());
        $this->assertEquals(1, Pemesanan::where('status_pemesanan', 'menunggu_pembayaran')->count());
    }

    /**
     * @test
     * Skenario: Booking di tanggal yang BERBEDA tidak saling mengganggu.
     * Customer 1 pesan semua unit tgl 10-12.
     * Customer 2 pesan semua unit tgl 13-15.
     * Keduanya harus berhasil.
     */
    public function test_bookings_on_different_dates_do_not_conflict()
    {
        $token1 = $this->customer1->createToken('t1')->plainTextToken;
        $token2 = $this->customer2->createToken('t2')->plainTextToken;

        // Customer 1: besok s/d lusa
        $r1 = $this->withHeaders(['Authorization' => 'Bearer ' . $token1])
            ->postJson('/api/pemesanan', [
                'tanggal_check_in' => Carbon::tomorrow()->format('Y-m-d'),
                'tanggal_check_out' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
                'kamars' => [
                    [
                        'kamar_id' => $this->kamar->id_kamar,
                        'jumlah_kamar' => 2
                    ]
                ]
            ]);
        $r1->assertStatus(201);

        // Customer 2: 5 hari dari sekarang (tidak overlap)
        $r2 = $this->withHeaders(['Authorization' => 'Bearer ' . $token2])
            ->postJson('/api/pemesanan', [
                'tanggal_check_in' => Carbon::tomorrow()->addDays(5)->format('Y-m-d'),
                'tanggal_check_out' => Carbon::tomorrow()->addDays(7)->format('Y-m-d'),
                'kamars' => [
                    [
                        'kamar_id' => $this->kamar->id_kamar,
                        'jumlah_kamar' => 2
                    ]
                ]
            ]);
        $r2->assertStatus(201);

        $this->assertEquals(2, Pemesanan::count(), 'Kedua pemesanan harus berhasil karena tanggal tidak overlap');
    }
}
