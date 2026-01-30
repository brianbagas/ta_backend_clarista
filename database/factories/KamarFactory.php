<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Kamar>
 */
class KamarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jumlah = fake()->numberBetween(1, 10);
        return [
            'tipe_kamar' => 'Kamar ' . fake()->words(2, true),
            'deskripsi' => fake()->paragraph(),
            'harga' => fake()->numberBetween(300000, 1000000),
            'status_ketersediaan' => $jumlah > 0,
            'jumlah_total' => $jumlah,
        ];
    }
}
