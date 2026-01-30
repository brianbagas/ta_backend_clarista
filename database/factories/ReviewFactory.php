<?php

namespace Database\Factories;

use App\Models\Pemesanan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Idealnya pemesanan_id di-supply saat create(), tapi kita kasih fallback factory
            'pemesanan_id' => Pemesanan::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'komentar' => fake()->paragraph(),
            'status' => fake()->randomElement(['disetujui', 'menunggu_persetujuan']),
        ];
    }
}
