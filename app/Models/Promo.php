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
        'is_active',
        'kuota',
        'kuota_terpakai',
        'min_transaksi',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'berlaku_mulai' => 'date',
        'berlaku_selesai' => 'date',
    ];

    protected $dates = ['deleted_at'];
    public function pemesanans()
    {
        return $this->hasMany(Pemesanan::class);
    }
}