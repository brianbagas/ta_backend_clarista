<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\DetailPemesanan;
use App\Models\Pemesanan;
use App\Models\Kamar;

class DetailPemesananFactory extends Factory
{
    protected $model = DetailPemesanan::class;

    public function definition()
    {
        return [
            'pemesanan_id' => Pemesanan::factory(),
            'kamar_id' => Kamar::factory(),
            'jumlah_kamar' => 1,
            'harga_per_malam' => 100000,
        ];
    }
}
