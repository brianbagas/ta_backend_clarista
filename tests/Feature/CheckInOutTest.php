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

class CheckInOutTest extends TestCase
{
    use RefreshDatabase;

    protected $owner;
    protected $customer;
    protected $pemesanan;
    protected $detailPemesanan;
    protected $penempatanKamar;
    protected $kamarUnit;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::create(['id' => 1, 'role' => 'owner']);
        Role::create(['id' => 2, 'role' => 'customer']);

        // Create users
        $this->owner = User::factory()->owner()->create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->customer = User::factory()->customer()->create([
            'name' => 'Customer User',
            'email' => 'customer@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Create kamar
        $kamar = Kamar::create([
            'tipe_kamar' => 'Deluxe Room',
            'harga' => 350000,
            'deskripsi' => 'Kamar nyaman',
            'jumlah_total' => 5,
            'status_ketersediaan' => true
        ]);

        $this->kamarUnit = KamarUnit::create([
            'kamar_id' => $kamar->id_kamar,
            'nomor_unit' => '101',
            'status_unit' => 'available'
        ]);

        // Create confirmed pemesanan
        $this->pemesanan = Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::today(),
            'tanggal_check_out' => Carbon::today()->addDays(2),
            'total_bayar' => 700000,
            'status_pemesanan' => 'dikonfirmasi',
            'expired_at' => Carbon::now()->addHour()
        ]);

        $this->detailPemesanan = DetailPemesanan::create([
            'pemesanan_id' => $this->pemesanan->id,
            'kamar_id' => $kamar->id_kamar,
            'jumlah_kamar' => 1,
            'harga_per_malam' => 350000
        ]);

        $this->penempatanKamar = PenempatanKamar::create([
            'detail_pemesanan_id' => $this->detailPemesanan->id,
            'kamar_unit_id' => $this->kamarUnit->id,
            'status_penempatan' => 'pending'
        ]);
    }

    /** @test */
    public function owner_can_check_in_guest()
    {
        $token = $this->owner->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/admin/check-in', [
                    'detail_pemesanan_id' => $this->detailPemesanan->id, // Kirim ID Detail
                    'kamar_unit_id' => $this->kamarUnit->id,           // Kirim ID Unit
                ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        // Assert that the EXISTING record is updated
        $this->assertDatabaseHas('penempatan_kamars', [
            'id' => $this->penempatanKamar->id,
            'status_penempatan' => 'assigned'
        ]);

        $this->assertNotNull(
            PenempatanKamar::find($this->penempatanKamar->id)->check_in_aktual
        );
    }

    /** @test */
    public function owner_can_check_out_guest()
    {
        // First check-in
        $this->penempatanKamar->update([
            'status_penempatan' => 'assigned',
            'check_in_aktual' => Carbon::now()
        ]);

        $token = $this->owner->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/admin/check-out/{$this->penempatanKamar->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('penempatan_kamars', [
            'id' => $this->penempatanKamar->id,
            'status_penempatan' => 'checked_out'
        ]);

        $this->assertNotNull(
            PenempatanKamar::find($this->penempatanKamar->id)->check_out_aktual
        );

        // Unit should be in kotor status
        $this->assertDatabaseHas('kamar_units', [
            'id' => $this->kamarUnit->id,
            'status_unit' => 'kotor'
        ]);
    }

    /** @test */
    public function booking_status_changes_to_selesai_when_all_units_checked_out()
    {
        // Check-in first
        $this->penempatanKamar->update([
            'status_penempatan' => 'assigned',
            'check_in_aktual' => Carbon::now()
        ]);

        $token = $this->owner->createToken('test-token')->plainTextToken;

        // Check-out
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/admin/check-out/{$this->penempatanKamar->id}");

        // Pemesanan should be selesai
        $this->assertDatabaseHas('pemesanans', [
            'id' => $this->pemesanan->id,
            'status_pemesanan' => 'selesai'
        ]);
    }

    /** @test */
    public function cannot_check_in_twice()
    {
        $this->penempatanKamar->update([
            'status_penempatan' => 'assigned',
            'check_in_aktual' => Carbon::now()
        ]);

        $token = $this->owner->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/admin/check-in', [
                    'penempatan_id' => $this->penempatanKamar->id,
                    'detail_pemesanan_id' => $this->detailPemesanan->id,
                    'kamar_unit_id' => $this->kamarUnit->id
                ]);

        $response->assertStatus(400);
    }

    /** @test */
    public function cannot_check_out_before_check_in()
    {
        // Ensure status is NOT assigned (e.g. pending)
        $this->penempatanKamar->update([
            'status_penempatan' => 'pending'
        ]);

        $token = $this->owner->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/admin/check-out/{$this->penempatanKamar->id}");

        $response->assertStatus(400);
    }

    /** @test */
    public function customer_cannot_check_in_guests()
    {
        $token = $this->customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/admin/check-in', [
                    'penempatan_id' => $this->penempatanKamar->id
                ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function unit_status_can_be_changed_to_available_after_maintenance()
    {
        // Set unit to maintenance
        $this->kamarUnit->update(['status_unit' => 'maintenance']);

        $token = $this->owner->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/admin/kamar-units/{$this->kamarUnit->id}", [
                    'status_unit' => 'available'
                ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('kamar_units', [
            'id' => $this->kamarUnit->id,
            'status_unit' => 'available'
        ]);
    }
}
