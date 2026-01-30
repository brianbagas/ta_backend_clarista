<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Kamar;
use App\Models\Pemesanan;
use App\Models\Pembayaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected $customer;
    protected $owner;
    protected $pemesanan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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

        // Create pemesanan
        $this->pemesanan = Pemesanan::create([
            'user_id' => $this->customer->id,
            'tanggal_check_in' => Carbon::tomorrow(),
            'tanggal_check_out' => Carbon::tomorrow()->addDays(2),
            'total_bayar' => 1400000,
            'status_pemesanan' => 'menunggu_pembayaran',
            'expired_at' => Carbon::now()->addHour()
        ]);
    }

    /** @test */
    public function customer_can_upload_payment_proof()
    {
        $token = $this->customer->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->image('bukti_bayar.jpg');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/pemesanan/{$this->pemesanan->id}/pembayaran", [
                    'bukti_bayar' => $file,
                    'jumlah_bayar' => 1400000,
                    'bank_tujuan' => 'BCA',
                    'nama_pengirim' => 'Customer User',
                    'tanggal_bayar' => Carbon::today()->format('Y-m-d')
                ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $this->assertDatabaseHas('pembayarans', [
            'pemesanan_id' => $this->pemesanan->id,
            'jumlah_bayar' => 1400000,
            'bank_tujuan' => 'BCA'
        ]);

        $this->assertDatabaseHas('pemesanans', [
            'id' => $this->pemesanan->id,
            'status_pemesanan' => 'menunggu_konfirmasi'
        ]);

        Storage::disk('public')->assertExists('bukti_pembayaran/' . basename($file->hashName()));
    }

    /** @test */
    public function payment_amount_must_match_total()
    {
        $token = $this->customer->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->image('bukti_bayar.jpg');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/pemesanan/{$this->pemesanan->id}/pembayaran", [
                    'bukti_bayar' => $file,
                    'jumlah_bayar' => 1000000, // Wrong amount
                    'bank_tujuan' => 'BCA',
                    'nama_pengirim' => 'Customer User',
                    'tanggal_bayar' => Carbon::today()->format('Y-m-d')
                ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false
            ]);
    }

    /** @test */
    public function payment_proof_must_be_image()
    {
        $token = $this->customer->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/pemesanan/{$this->pemesanan->id}/pembayaran", [
                    'bukti_bayar' => $file,
                    'jumlah_bayar' => 1400000,
                    'bank_tujuan' => 'BCA',
                    'nama_pengirim' => 'Customer User',
                    'tanggal_bayar' => Carbon::today()->format('Y-m-d')
                ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bukti_bayar']);
    }

    /** @test */
    public function cannot_upload_payment_for_confirmed_booking()
    {
        $this->pemesanan->update(['status_pemesanan' => 'dikonfirmasi']);

        $token = $this->customer->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->image('bukti_bayar.jpg');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/pemesanan/{$this->pemesanan->id}/pembayaran", [
                    'bukti_bayar' => $file,
                    'jumlah_bayar' => 1400000,
                    'bank_tujuan' => 'BCA',
                    'nama_pengirim' => 'Customer User',
                    'tanggal_bayar' => Carbon::today()->format('Y-m-d')
                ]);

        $response->assertStatus(400);
    }

    /** @test */
    public function owner_can_view_pending_payments()
    {
        // Create payment
        Pembayaran::create([
            'pemesanan_id' => $this->pemesanan->id,
            'bukti_bayar_path' => 'bukti_pembayaran/test.jpg',
            'jumlah_bayar' => 1400000,
            'bank_tujuan' => 'BCA',
            'nama_pengirim' => 'Customer User',
            'tanggal_bayar' => Carbon::today()
        ]);

        $this->pemesanan->update(['status_pemesanan' => 'menunggu_konfirmasi']);

        $token = $this->owner->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/pembayaran/verifikasi');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'status_pemesanan',
                        'total_bayar',
                        'user',
                        'pembayaran'
                    ]
                ]
            ]);
    }

    /** @test */
    public function owner_can_verify_payment()
    {
        Pembayaran::create([
            'pemesanan_id' => $this->pemesanan->id,
            'bukti_bayar_path' => 'bukti_pembayaran/test.jpg',
            'jumlah_bayar' => 1400000,
            'bank_tujuan' => 'BCA',
            'nama_pengirim' => 'Customer User',
            'tanggal_bayar' => Carbon::today()
        ]);

        $this->pemesanan->update(['status_pemesanan' => 'menunggu_konfirmasi']);

        $token = $this->owner->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/admin/pembayaran/verifikasi/{$this->pemesanan->id}", [
                    'status' => 'dikonfirmasi'
                ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $this->assertDatabaseHas('pemesanans', [
            'id' => $this->pemesanan->id,
            'status_pemesanan' => 'dikonfirmasi'
        ]);
    }

    /** @test */
    public function owner_can_reject_payment()
    {
        Pembayaran::create([
            'pemesanan_id' => $this->pemesanan->id,
            'bukti_bayar_path' => 'bukti_pembayaran/test.jpg',
            'jumlah_bayar' => 1400000,
            'bank_tujuan' => 'BCA',
            'nama_pengirim' => 'Customer User',
            'tanggal_bayar' => Carbon::today()
        ]);

        $this->pemesanan->update(['status_pemesanan' => 'menunggu_konfirmasi']);

        $token = $this->owner->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/admin/pembayaran/verifikasi/{$this->pemesanan->id}", [
                    'status' => 'batal'
                ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('pemesanans', [
            'id' => $this->pemesanan->id,
            'status_pemesanan' => 'batal'
        ]);
    }

    /** @test */
    public function customer_cannot_upload_payment_for_other_users_booking()
    {
        $otherCustomer = User::factory()->customer()->create([
            'name' => 'Other Customer',
            'email' => 'other@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $otherCustomer->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->image('bukti_bayar.jpg');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/pemesanan/{$this->pemesanan->id}/pembayaran", [
                    'bukti_bayar' => $file,
                    'jumlah_bayar' => 1400000,
                    'bank_tujuan' => 'BCA',
                    'nama_pengirim' => 'Other Customer',
                    'tanggal_bayar' => Carbon::today()->format('Y-m-d')
                ]);

        $response->assertStatus(403);
    }
}
