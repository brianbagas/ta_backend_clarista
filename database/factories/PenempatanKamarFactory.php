<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\PenempatanKamar;
use App\Models\DetailPemesanan;
use App\Models\KamarUnit;

class PenempatanKamarFactory extends Factory
{
    protected $model = PenempatanKamar::class;

    public function definition()
    {
        return [
            'detail_pemesanan_id' => DetailPemesanan::factory(),
            'kamar_unit_id' => KamarUnit::factory(),
            'status_penempatan' => 'assigned',
            'check_in_aktual' => null,
            'check_out_aktual' => null,
        ];
    }
}
