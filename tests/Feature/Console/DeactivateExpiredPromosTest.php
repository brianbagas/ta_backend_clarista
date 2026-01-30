<?php

namespace Tests\Feature\Console;

use App\Models\Promo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeactivateExpiredPromosTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_deactivates_expired_promos()
    {
        // 1. Promo yang sudah expired kemarin, tapi is_active masih true
        $expiredPromo = Promo::create([
            'nama_promo' => 'Expired Promo',
            'kode_promo' => 'EXPIRED123',
            'tipe_diskon' => 'persen',
            'nilai_diskon' => 10,
            'berlaku_mulai' => now()->subDays(10),
            'berlaku_selesai' => now()->subDays(1),
            'is_active' => true,
        ]);

        // 2. Promo yang masih berlaku besok, is_active true
        $activePromo = Promo::create([
            'nama_promo' => 'Active Promo',
            'kode_promo' => 'ACTIVE123',
            'tipe_diskon' => 'persen',
            'nilai_diskon' => 10,
            'berlaku_mulai' => now()->subDays(1),
            'berlaku_selesai' => now()->addDays(1),
            'is_active' => true,
        ]);

        // 3. Jalankan command
        $this->artisan('app:deactivate-expired-promos')
            ->expectsOutput('Successfully deactivated 1 expired promo(s).')
            ->assertExitCode(0);

        // 4. Verifikasi database
        $this->assertDatabaseHas('promos', [
            'id' => $expiredPromo->id,
            'is_active' => false, // Harusnya jadi false
        ]);

        $this->assertDatabaseHas('promos', [
            'id' => $activePromo->id,
            'is_active' => true, // Harusnya tetap true
        ]);
    }
}
