<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PenempatanKamar extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'penempatan_kamars';

    protected $fillable = [
        'detail_pemesanan_id',
        'kamar_unit_id',
        'status_penempatan',
        'check_in_aktual',
        'check_out_aktual',
    ];

    // Relasi: Penempatan ini milik satu Detail Pemesanan
    public function detailPemesanan()
    {
        return $this->belongsTo(DetailPemesanan::class, 'detail_pemesanan_id');
    }

    // Relasi: Penempatan ini merujuk ke satu Unit Fisik Kamar
    public function unit()
    {
        return $this->belongsTo(KamarUnit::class, 'kamar_unit_id');
    }
}
