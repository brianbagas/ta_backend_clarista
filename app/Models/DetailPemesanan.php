<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetailPemesanan extends Model
{
    use SoftDeletes;
     use HasFactory;

    /**
     * Atribut yang bisa diisi secara massal.
     */
    protected $fillable = [
        'pemesanan_id',
        'kamar_id',
        'jumlah_kamar',
        'harga_per_malam',
    ];

    /**
     * Relasi: Satu detail pemesanan dimiliki oleh satu Pemesanan.
     */
    public function pemesanan()
    {
        return $this->belongsTo(Pemesanan::class, 'pemesanan_id');
    }

    /**
     * Relasi: Satu detail pemesanan merujuk pada satu Kamar.
     */
    public function kamar()
    {
        return $this->belongsTo(Kamar::class, 'kamar_id');
    }
public function penempatanKamars()
    {
        // Pastikan nama model 'PenempatanKamar' benar
        return $this->hasMany(penempatan_kamar::class, 'detail_pemesanan_id', 'id');
    }
}
