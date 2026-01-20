<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Promo extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nama_promo',
        'kode_promo',
        'deskripsi',
        'tipe_diskon',
        'nilai_diskon',
        'berlaku_mulai',
        'berlaku_selesai',
    ];

    protected $dates = ['deleted_at'];
}