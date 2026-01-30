<?php

namespace Database\Factories;

use App\Models\Kamar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KamarImage>
 */
class KamarImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kamar_id' => Kamar::factory(), // Fallback create Kamar baru jika tidak di-supply
            'image_path' => 'dummy-' . fake()->numberBetween(1, 2) . '.jpg',
        ];
    }
}
