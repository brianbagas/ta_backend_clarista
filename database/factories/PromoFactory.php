<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Promo>
 */
class PromoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tipe = fake()->randomElement(['persen', 'nominal']);
        $nilai = ($tipe === 'persen') ? fake()->numberBetween(10, 50) : fake()->numberBetween(10000, 50000);

        return [
            'nama_promo' => 'Promo ' . fake()->words(2, true),
            'kode_promo' => strtoupper(fake()->unique()->lexify('PROMO-????') . fake()->numberBetween(10, 99)),
            'deskripsi' => fake()->sentence(),
            'tipe_diskon' => $tipe,
            'nilai_diskon' => $nilai,
            'berlaku_mulai' => now(),
            'berlaku_selesai' => now()->addDays(fake()->numberBetween(7, 30)),
            'is_active' => true,
        ];
    }
}
