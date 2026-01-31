<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Promo;
use Carbon\Carbon;

class PromoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $promos = [
            [
                'nama_promo' => 'Promo Tahun Baru 2026',
                'kode_promo' => 'NEWYEAR2026',
                'deskripsi' => 'Diskon spesial untuk menyambut tahun baru 2026. Dapatkan potongan 15% untuk semua tipe kamar dengan minimal transaksi Rp 500.000',
                'tipe_diskon' => 'persen',
                'nilai_diskon' => 15.00,
                'kuota' => 50,
                'kuota_terpakai' => 0,
                'min_transaksi' => 500000.00,
                'is_active' => true,
                'berlaku_mulai' => Carbon::parse('2026-01-01'),
                'berlaku_selesai' => Carbon::parse('2026-02-28'),
            ],
            [
                'nama_promo' => 'Diskon Weekend Getaway',
                'kode_promo' => 'WEEKEND50K',
                'deskripsi' => 'Nikmati diskon Rp 50.000 untuk booking di akhir pekan. Berlaku untuk minimal transaksi Rp 300.000',
                'tipe_diskon' => 'nominal',
                'nilai_diskon' => 50000.00,
                'kuota' => 100,
                'kuota_terpakai' => 0,
                'min_transaksi' => 300000.00,
                'is_active' => true,
                'berlaku_mulai' => Carbon::parse('2026-01-15'),
                'berlaku_selesai' => Carbon::parse('2026-03-31'),
            ],
        ];

        foreach ($promos as $promoData) {
            $promo = Promo::create($promoData);
            $this->command->info("Created promo: {$promo->nama_promo} ({$promo->kode_promo})");
        }

        $this->command->info('Promo seeding completed successfully!');
    }
}
