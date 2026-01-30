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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ExpiredBookingTest extends TestCase
{
    use RefreshDatabase;

    protected $customer;
    protected $kamar;
    protected $promo;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::create(['id' => 1, 'role' => 'owner']);
        Role::create(['id' => 2, 'role' => 'customer']);

        // Create customer
        $this->customer = User::factory()->customer()->create([
            'name' => 'Customer User',
            'email' => 'customer@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Create kamar
        $this->kamar = Kamar::create([
            'tipe_kamar' => 'Deluxe Room',
            'harga' => 350000,
            'deskripsi' => 'Kamar nyaman',
            'jumlah_total' => 5,
            'status_ketersediaan' => true
        ]);

        for ($i = 1; $i <= 5; $i++) {
            KamarUnit::create([
                'kamar_id' => $this->kamar->id_kamar,
                'nomor_unit' => '10' . $i,
                'status_unit' => 'available'
            ]);
        }

        // Create promo
        $this->promo = Promo::create([
            'nama_promo' => 'Diskon 50rb',
            'kode_promo' => 'DISKON50',
            'tipe_diskon' => 'nominal',
            'nilai_diskon' => 50000,
            'berlaku_mulai' => Carbon::yesterday(),
            'berlaku_selesai' => Carbon::tomorrow()->addDays(10),
            'is_active' => true,
            'kuota' => 10,
            'kuota_terpakai' => 0
        ]);
    }

    /** @test */
    public function expired_booking_is_auto_cancelled()
    {
        // Create expired booking
        $pemesanan = Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::tomorrow(),
            'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
            'total_bayar' => 700000,
            'status_pemesanan' => 'menunggu_pembayaran',
            'expired_at' => Carbon::now()->subMinutes(10) // Expired 10 minutes ago
        ]);

        $detail = DetailPemesanan::create([
            'pemesanan_id' => $pemesanan->id,
            'kamar_id' => $this->kamar->id_kamar,
            'jumlah_kamar' => 1,
            'harga_per_malam' => 350000
        ]);

        $unit = KamarUnit::where('kamar_id', $this->kamar->id_kamar)->first();

        $penempatan = PenempatanKamar::create([
            'detail_pemesanan_id' => $detail->id,
            'kamar_unit_id' => $unit->id,
            'status_penempatan' => 'pending'
        ]);

        // Run command
        Artisan::call('booking:handle-expired');

        // Check booking is cancelled
        $this->assertDatabaseHas('pemesanans', [
            'id' => $pemesanan->id,
            'status_pemesanan' => 'batal',
            'dibatalkan_oleh' => 'system'
        ]);

        // Check penempatan is cancelled
        $this->assertDatabaseHas('penempatan_kamars', [
            'id' => $penempatan->id,
            'status_penempatan' => 'cancelled'
        ]);
    }

    /** @test */
    public function expired_booking_with_promo_releases_quota()
    {
        // Use promo
        $this->promo->increment('kuota_terpakai');

        // Create expired booking with promo
        $pemesanan = Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::tomorrow(),
            'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
            'total_bayar' => 650000,
            'promo_id' => $this->promo->id,
            'status_pemesanan' => 'menunggu_pembayaran',
            'expired_at' => Carbon::now()->subMinutes(10)
        ]);

        $detail = DetailPemesanan::create([
            'pemesanan_id' => $pemesanan->id,
            'kamar_id' => $this->kamar->id_kamar,
            'jumlah_kamar' => 1,
            'harga_per_malam' => 350000
        ]);

        $unit = KamarUnit::where('kamar_id', $this->kamar->id_kamar)->first();

        PenempatanKamar::create([
            'detail_pemesanan_id' => $detail->id,
            'kamar_unit_id' => $unit->id,
            'status_penempatan' => 'pending'
        ]);

        // Run command
        Artisan::call('booking:handle-expired');

        // Check promo quota is released
        $this->assertDatabaseHas('promos', [
            'id' => $this->promo->id,
            'kuota_terpakai' => 0 // Back to 0
        ]);
    }

    /** @test */
    public function non_expired_booking_is_not_cancelled()
    {
        // Create non-expired booking
        $pemesanan = Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::tomorrow(),
            'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
            'total_bayar' => 700000,
            'status_pemesanan' => 'menunggu_pembayaran',
            'expired_at' => Carbon::now()->addMinutes(30) // Still valid
        ]);

        // Run command
        Artisan::call('booking:handle-expired');

        // Check booking is NOT cancelled
        $this->assertDatabaseHas('pemesanans', [
            'id' => $pemesanan->id,
            'status_pemesanan' => 'menunggu_pembayaran'
        ]);
    }

    /** @test */
    public function confirmed_booking_is_not_cancelled_even_if_expired()
    {
        // Create confirmed booking with past expired_at
        $pemesanan = Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::tomorrow(),
            'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
            'total_bayar' => 700000,
            'status_pemesanan' => 'dikonfirmasi',
            'expired_at' => Carbon::now()->subMinutes(10)
        ]);

        // Run command
        Artisan::call('booking:handle-expired');

        // Check booking is NOT cancelled
        $this->assertDatabaseHas('pemesanans', [
            'id' => $pemesanan->id,
            'status_pemesanan' => 'dikonfirmasi'
        ]);
    }

    /** @test */
    public function multiple_expired_bookings_are_all_cancelled()
    {
        // Create multiple expired bookings
        for ($i = 0; $i < 3; $i++) {
            $pemesanan = Pemesanan::create([
                'user_id' => $this->customer->id,
                'tanggal_check_in' => Carbon::tomorrow(),
                'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
                'total_bayar' => 700000,
                'status_pemesanan' => 'menunggu_pembayaran',
                'expired_at' => Carbon::now()->subMinutes(10 + $i)
            ]);

            $detail = DetailPemesanan::create([
                'pemesanan_id' => $pemesanan->id,
                'kamar_id' => $this->kamar->id_kamar,
                'jumlah_kamar' => 1,
                'harga_per_malam' => 350000
            ]);

            $unit = KamarUnit::where('kamar_id', $this->kamar->id_kamar)->skip($i)->first();

            PenempatanKamar::create([
                'detail_pemesanan_id' => $detail->id,
                'kamar_unit_id' => $unit->id,
                'status_penempatan' => 'pending'
            ]);
        }

        // Run command
        Artisan::call('booking:handle-expired');

        // Check all are cancelled
        $cancelledCount = Pemesanan::where('status_pemesanan', 'batal')
            ->where('dibatalkan_oleh', 'system')
            ->count();

        $this->assertEquals(3, $cancelledCount);
    }

    /** @test */
    public function cancelled_booking_releases_room_units()
    {
        // Create expired booking
        $pemesanan = Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::tomorrow(),
            'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
            'total_bayar' => 1400000,
            'status_pemesanan' => 'menunggu_pembayaran',
            'expired_at' => Carbon::now()->subMinutes(10)
        ]);

        $detail = DetailPemesanan::create([
            'pemesanan_id' => $pemesanan->id,
            'kamar_id' => $this->kamar->id_kamar,
            'jumlah_kamar' => 2,
            'harga_per_malam' => 350000
        ]);

        $units = KamarUnit::where('kamar_id', $this->kamar->id_kamar)->take(2)->get();

        foreach ($units as $unit) {
            PenempatanKamar::create([
                'detail_pemesanan_id' => $detail->id,
                'kamar_unit_id' => $unit->id,
                'status_penempatan' => 'pending'
            ]);
        }

        // Run command
        Artisan::call('booking:handle-expired');

        // Check all penempatan are cancelled
        $cancelledPenempatan = PenempatanKamar::whereIn('kamar_unit_id', $units->pluck('id'))
            ->where('status_penempatan', 'cancelled')
            ->count();

        $this->assertEquals(2, $cancelledPenempatan);

        // Units should be available for new bookings
        $availableUnits = KamarUnit::where('kamar_id', $this->kamar->id_kamar)
            ->whereDoesntHave('penempatanKamars', function ($q) {
                $q->whereIn('status_penempatan', ['pending', 'assigned']);
            })
            ->count();

        $this->assertEquals(5, $availableUnits);
    }
}
