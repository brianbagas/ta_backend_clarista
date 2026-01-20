<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomestayContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'alamat',
        'telepon',
        'email',
        'link_gmaps',
        'hero_title',
        'hero_subtitle',
        'hero_image_path',
        'nama_bank', 'nomor_rekening', 'atas_nama_rekening'
    ];
}