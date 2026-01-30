<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\KamarUnit;
use App\Models\Kamar;

class KamarUnitFactory extends Factory
{
    protected $model = KamarUnit::class;

    public function definition()
    {
        return [
            'kamar_id' => Kamar::factory(),
            'nomor_unit' => $this->faker->unique()->numberBetween(100, 999),
            'status_unit' => 'available', // available, maintenance, etc.
        ];
    }
}
