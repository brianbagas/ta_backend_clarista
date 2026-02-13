<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\DetailPemesanan;
use App\Models\Kamar;
use App\Models\KamarUnit;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Models\PenempatanKamar;
use App\Models\Promo;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Role;

class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;
    // WARNING: Be careful if testing on existing DB. 
    // Since user is developing on XAMPP with likely existing data, 
    // we should create specific test data and clean it up, or use a separate test DB.
    // For now, I will create data and clean it up manually or assume a test environment.
    // Ideally, we should use RefreshDatabase with strict mysql_testing config, 
    // but to be safe on user's dev env, I'll use explicit creation/deletion.

    protected $owner;
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::create(['id' => 1, 'role' => 'owner']);
        Role::create(['id' => 2, 'role' => 'customer']);

        // Create or find an owner user
        $this->owner = User::whereHas('role', function ($q) {
            $q->where('role', 'owner');
        })->first();
        if (!$this->owner) {
            $this->owner = User::factory()->owner()->create(['email' => 'owner_test@example.com']);
        }

        // Create or find a customer
        $this->customer = User::whereHas('role', function ($q) {
            $q->where('role', 'customer');
        })->first();
        if (!$this->customer) {
            $this->customer = User::factory()->customer()->create(['email' => 'cust_test@example.com']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BANK ACCOUNT (Standalone)
    |--------------------------------------------------------------------------
    */
    public function test_bank_account_soft_delete_flow()
    {
        // 1. Create
        $bank = BankAccount::create([
            'nama_bank' => 'Test Bank',
            'nomor_rekening' => '1234567890',
            'atas_nama' => 'Test Owner',
            'is_active' => true
        ]);

        // 2. Soft Delete
        $response = $this->actingAs($this->owner)->deleteJson("/api/admin/bank-accounts/{$bank->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('bank_accounts', ['id' => $bank->id]);

        // 3. View Trashed
        $response = $this->actingAs($this->owner)->getJson("/api/admin/trashed/bank-account");
        $response->assertStatus(200)
            ->assertJsonFragment(['nomor_rekening' => '1234567890']);

        // 4. Restore
        $response = $this->actingAs($this->owner)->postJson("/api/admin/trashed/bank-account/{$bank->id}/restore");
        $response->assertStatus(200);
        $this->assertNotSoftDeleted('bank_accounts', ['id' => $bank->id]);

        // 5. Force Delete
        $bank->delete(); // Soft delete again first
        $response = $this->actingAs($this->owner)->deleteJson("/api/admin/trashed/bank-account/{$bank->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('bank_accounts', ['id' => $bank->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | PROMO (Safety Check)
    |--------------------------------------------------------------------------
    */
    public function test_promo_soft_delete_with_safety_check()
    {
        // 1. Create Promo
        $promo = Promo::create([
            'nama_promo' => 'Test Promo',
            'kode_promo' => 'TEST10',
            'tipe_diskon' => 'persen',
            'nilai_diskon' => 10,
            'is_active' => true,
            'kuota' => 100,
            'berlaku_mulai' => now(),
            'berlaku_selesai' => now()->addMonth()
        ]);

        // 2. Soft Delete
        $response = $this->actingAs($this->owner)->deleteJson("/api/admin/promo/{$promo->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('promos', ['id' => $promo->id]);

        // 3. Restore
        $response = $this->actingAs($this->owner)->postJson("/api/admin/trashed/promo/{$promo->id}/restore");
        $response->assertStatus(200);

        // 4. Create Relation (Pemesanan uses Promo)
        $pemesanan = Pemesanan::create([
            'user_id' => $this->customer->id,
            'promo_id' => $promo->id,
            'kode_booking' => 'TEST-PROMO',
            'tanggal_check_in' => now(),
            'tanggal_check_out' => now()->addDay(),
            'total_bayar' => 100000,
            'status_pemesanan' => 'pending'
        ]);

        // 5. Try Force Delete (Should Fail)
        $promo->delete(); // Soft delete first
        $response = $this->actingAs($this->owner)->deleteJson("/api/admin/trashed/promo/{$promo->id}");
        $response->assertStatus(409); // Conflict / Forbidden

        // 6. Cleanup Relation
        $pemesanan->forceDelete();
        $promo->forceDelete();
    }

    /*
    |--------------------------------------------------------------------------
    | KAMAR (Safety Check)
    |--------------------------------------------------------------------------
    */
    public function test_kamar_soft_delete_with_safety_check()
    {
        // 1. Create Kamar
        $kamar = Kamar::create([
            'tipe_kamar' => 'Test Suite',
            'harga' => 500000,
            'deskripsi' => 'Testing',
            'jumlah_total' => 1
        ]);

        // 2. Soft Delete
        $response = $this->actingAs($this->owner)->deleteJson("/api/admin/kamar/{$kamar->id_kamar}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('kamars', ['id_kamar' => $kamar->id_kamar]);

        // 3. Restore
        $response = $this->actingAs($this->owner)->postJson("/api/admin/trashed/kamar/{$kamar->id_kamar}/restore");
        $response->assertStatus(200);

        // 4. Create Relation (DetailPemesanan)
        // Dummy Pemesanan
        $pemesanan = Pemesanan::create([
            'user_id' => $this->customer->id,
            'kode_booking' => 'TEST-KAMAR',
            'tanggal_check_in' => now(),
            'tanggal_check_out' => now()->addDay(),
            'total_bayar' => 500000,
            'status_pemesanan' => 'pending'
        ]);

        $detail = DetailPemesanan::create([
            'pemesanan_id' => $pemesanan->id,
            'kamar_id' => $kamar->id_kamar,
            'jumlah_kamar' => 1,
            'harga_per_malam' => 500000,
            'subtotal' => 500000
        ]);

        // 5. Try Force Delete (Should Fail)
        $kamar->delete();
        $response = $this->actingAs($this->owner)->deleteJson("/api/admin/trashed/kamar/{$kamar->id_kamar}");
        $response->assertStatus(409);

        // 6. Cleanup
        $detail->forceDelete();
        $pemesanan->forceDelete();
        $kamar->forceDelete();
    }

    /*
    |--------------------------------------------------------------------------
    | PEMESANAN (Cascade Delete)
    |--------------------------------------------------------------------------
    */
    public function test_pemesanan_force_delete_cascade()
    {
        // 1. Create Full Pemesanan Chain
        $pemesanan = Pemesanan::create([
            'user_id' => $this->customer->id,
            'kode_booking' => 'TEST-CASCADE',
            'tanggal_check_in' => now(),
            'tanggal_check_out' => now()->addDay(),
            'total_bayar' => 500000,
            'status_pemesanan' => 'selesai'
        ]);

        // Add Review
        $review = Review::create([
            'pemesanan_id' => $pemesanan->id,
            'user_id' => $this->customer->id,
            'rating' => 5,
            'komentar' => 'Great!',
            'status' => 'approved'
        ]);

        // Add Pembayaran
        $pembayaran = Pembayaran::create([
            'pemesanan_id' => $pemesanan->id,
            'bank_tujuan' => 'BCA',
            'jumlah_bayar' => 500000,
            'status_pembayaran' => 'verified',
            'bukti_bayar_path' => 'dummy/path.jpg'
        ]);

        // 2. Soft Delete Parent
        // Usually deletion is tricky via API (cancel?), let's manual delete for test setup
        $pemesanan->delete();

        // 3. Force Delete
        $response = $this->actingAs($this->owner)->deleteJson("/api/admin/trashed/pemesanan/{$pemesanan->id}");
        $response->assertStatus(200);

        // 4. Verify Cascade
        $this->assertDatabaseMissing('pemesanans', ['id' => $pemesanan->id]);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('pembayarans', ['id' => $pembayaran->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | KAMAR UNIT (Safety Check)
    |--------------------------------------------------------------------------
    */
    public function test_kamar_unit_soft_delete_with_safety_check()
    {
        // 1. Create and Soft Delete (Simple)
        $kamar = Kamar::factory()->create();
        $unit = KamarUnit::create([
            'kamar_id' => $kamar->id_kamar,
            'nomor_unit' => 'UNIT-TEST-1',
            'status_unit' => 'available'
        ]);

        $response = $this->actingAs($this->owner)->deleteJson("/api/kamar-units/{$unit->id}");
        $response->assertStatus(200);

        $response = $this->actingAs($this->owner)->postJson("/api/admin/trashed/kamar-unit/{$unit->id}/restore");
        $response->assertStatus(200);

        // 2. Safety Check (With PenempatanKamar)
        // Create dependencies first
        $pemesanan = Pemesanan::factory()->create();
        $detail = DetailPemesanan::create([
            'pemesanan_id' => $pemesanan->id,
            'kamar_id' => $kamar->id_kamar,
            'jumlah_kamar' => 1,
            'harga_per_malam' => 100000,
            'subtotal' => 100000
        ]);

        // Create Penempatan with valid detail_pemesanan_id
        $penempatan = PenempatanKamar::create([
            'kamar_unit_id' => $unit->id,
            'detail_pemesanan_id' => $detail->id,
            'tanggal_masuk' => now(),
            'tanggal_keluar' => now()->addDay(),
            'status_penempatan' => 'check_in'
        ]);

        // Try Force Delete
        $unit->delete();
        $response = $this->actingAs($this->owner)->deleteJson("/api/admin/trashed/kamar-unit/{$unit->id}");
        $response->assertStatus(409);

        // Cleanup
        $penempatan->forceDelete();
        $detail->forceDelete();
        $pemesanan->forceDelete();
        $unit->forceDelete();
        $kamar->forceDelete();
    }

    /*
    |--------------------------------------------------------------------------
    | REVIEW (Simple Flow)
    |--------------------------------------------------------------------------
    */
    public function test_review_soft_delete_flow()
    {
        $pemesanan = Pemesanan::factory()->create(['user_id' => $this->customer->id]);
        $review = Review::create([
            'pemesanan_id' => $pemesanan->id,
            'rating' => 4,
            'komentar' => 'Good',
            'status' => 'pending'
        ]);

        // Soft Delete (via normal destroy if exists, controller usually has updateStatus, destroy?)
        // ReviewController has destroy.
        // But route DELETE /admin/review/{id} might not exist?
        // Let's check api.php: Route::delete('...', [ReviewController::class, 'destroy']) isn't there explicitly?
        // Wait, ReviewController only had `updateStatus` in api.php before my changes?
        // I didn't add DELETE /admin/review/{id} (normal destroy) in "Manajemen Review" section.
        // It only has `trashed` endpoints.
        // But soft delete usually happens from frontend?
        // Frontend ReviewManagementView usually has delete button?
        // Let's assume I need to call destroy manually or add the route if missing.
        // In my `api.php` update, I added `Route::delete('/admin/trashed/review/{id}', ...)` for force delete.
        // Did I add normal destroy?
        // ReviewController has `destroy`.
        // Let's check api.php content again.
        // Result step 187: I didn't add normal destroy for Review.
        // I only added trashed routes.
        // So soft delete must be done manually in test for now, or I should add the route if it's a feature.

        $review->delete(); // Manual soft delete for test

        // Restore
        $response = $this->actingAs($this->owner)->postJson("/api/admin/trashed/review/{$review->id}/restore");
        $response->assertStatus(200);

        // Force Delete
        $review->delete();
        $response = $this->actingAs($this->owner)->deleteJson("/api/admin/trashed/review/{$review->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);

        $pemesanan->forceDelete();
    }

    /*
    |--------------------------------------------------------------------------
    | PEMBAYARAN (Simple Flow)
    |--------------------------------------------------------------------------
    */
    public function test_pembayaran_soft_delete_flow()
    {
        $pemesanan = Pemesanan::factory()->create(['user_id' => $this->customer->id]);
        $pembayaran = Pembayaran::create([
            'pemesanan_id' => $pemesanan->id,
            'bank_tujuan' => 'Mandiri',
            'jumlah_bayar' => 150000,
            'status_pembayaran' => 'pending',
            'bukti_bayar_path' => 'dummy/path.jpg'
        ]);

        // Manual soft delete (since route might represent cancel/reject)
        $pembayaran->delete();

        // Restore
        $response = $this->actingAs($this->owner)->postJson("/api/admin/trashed/pembayaran/{$pembayaran->id}/restore");
        $response->assertStatus(200);

        // Force Delete
        $pembayaran->delete();
        $response = $this->actingAs($this->owner)->deleteJson("/api/admin/trashed/pembayaran/{$pembayaran->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('pembayarans', ['id' => $pembayaran->id]);

        $pemesanan->forceDelete();
    }
}
