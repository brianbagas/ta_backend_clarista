<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Promo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class PromoTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_validate_active_promo()
    {
        $promo = Promo::create([
            'nama_promo' => 'Diskon 50rb',
            'kode_promo' => 'DISKON50',
            'tipe_diskon' => 'nominal',
            'nilai_diskon' => 50000,
            'berlaku_mulai' => Carbon::yesterday(),
            'berlaku_selesai' => Carbon::tomorrow(),
            'is_active' => true,
            'kuota' => 10,
            'kuota_terpakai' => 0,
            'min_transaksi' => 500000
        ]);

        $response = $this->postJson('/api/cek-promo', [
            'kode_promo' => 'DISKON50',
            'total_transaksi' => 1000000
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'kode_promo' => 'DISKON50',
                    'nilai_potongan' => 50000
                ]
            ]);
    }

    /** @test */
    public function cannot_use_expired_promo()
    {
        Promo::create([
            'nama_promo' => 'Promo Lama',
            'kode_promo' => 'EXPIRED',
            'tipe_diskon' => 'nominal',
            'nilai_diskon' => 50000,
            'berlaku_mulai' => Carbon::now()->subDays(10),
            'berlaku_selesai' => Carbon::yesterday(),
            'is_active' => true,
            'kuota' => 10,
            'kuota_terpakai' => 0
        ]);

        $response = $this->postJson('/api/cek-promo', [
            'kode_promo' => 'EXPIRED',
            'total_transaksi' => 1000000
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false
            ]);
    }

    /** @test */
    public function cannot_use_inactive_promo()
    {
        Promo::create([
            'nama_promo' => 'Promo Nonaktif',
            'kode_promo' => 'INACTIVE',
            'tipe_diskon' => 'nominal',
            'nilai_diskon' => 50000,
            'berlaku_mulai' => Carbon::yesterday(),
            'berlaku_selesai' => Carbon::tomorrow(),
            'is_active' => false,
            'kuota' => 10,
            'kuota_terpakai' => 0
        ]);

        $response = $this->postJson('/api/cek-promo', [
            'kode_promo' => 'INACTIVE',
            'total_transaksi' => 1000000
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function cannot_use_promo_with_insufficient_transaction()
    {
        Promo::create([
            'nama_promo' => 'Diskon 50rb',
            'kode_promo' => 'DISKON50',
            'tipe_diskon' => 'nominal',
            'nilai_diskon' => 50000,
            'berlaku_mulai' => Carbon::yesterday(),
            'berlaku_selesai' => Carbon::tomorrow(),
            'is_active' => true,
            'kuota' => 10,
            'kuota_terpakai' => 0,
            'min_transaksi' => 1000000
        ]);

        $response = $this->postJson('/api/cek-promo', [
            'kode_promo' => 'DISKON50',
            'total_transaksi' => 500000 // Below minimum
        ]);

        $response->assertStatus(400);
    }

    /** @test */
    public function cannot_use_promo_with_full_quota()
    {
        Promo::create([
            'nama_promo' => 'Diskon 50rb',
            'kode_promo' => 'DISKON50',
            'tipe_diskon' => 'nominal',
            'nilai_diskon' => 50000,
            'berlaku_mulai' => Carbon::yesterday(),
            'berlaku_selesai' => Carbon::tomorrow(),
            'is_active' => true,
            'kuota' => 10,
            'kuota_terpakai' => 10 // Full
        ]);

        $response = $this->postJson('/api/cek-promo', [
            'kode_promo' => 'DISKON50',
            'total_transaksi' => 1000000
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Kuota promo ini telah habis.'
            ]);
    }

    /** @test */
    public function percentage_promo_calculates_correctly()
    {
        Promo::create([
            'nama_promo' => 'Diskon 15%',
            'kode_promo' => 'PERSEN15',
            'tipe_diskon' => 'persen',
            'nilai_diskon' => 15,
            'berlaku_mulai' => Carbon::yesterday(),
            'berlaku_selesai' => Carbon::tomorrow(),
            'is_active' => true
        ]);

        $response = $this->postJson('/api/cek-promo', [
            'kode_promo' => 'PERSEN15',
            'total_transaksi' => 1000000
        ]);

        $expectedDiscount = 1000000 * 0.15; // 150000

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'nilai_potongan' => $expectedDiscount
                ]
            ]);
    }

    /** @test */
    public function promo_with_unlimited_quota_always_available()
    {
        Promo::create([
            'nama_promo' => 'Diskon Unlimited',
            'kode_promo' => 'UNLIMITED',
            'tipe_diskon' => 'nominal',
            'nilai_diskon' => 50000,
            'berlaku_mulai' => Carbon::yesterday(),
            'berlaku_selesai' => Carbon::tomorrow(),
            'is_active' => true,
            'kuota' => null, // Unlimited
            'kuota_terpakai' => 100
        ]);

        $response = $this->postJson('/api/cek-promo', [
            'kode_promo' => 'UNLIMITED',
            'total_transaksi' => 1000000
        ]);

        $response->assertStatus(200);
    }
}
