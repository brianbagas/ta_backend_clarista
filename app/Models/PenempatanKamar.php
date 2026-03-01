<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class PenempatanKamar extends Model
{
    use HasFactory, SoftDeletes, HasUlids;

    protected $table = 'penempatan_kamars';

    protected $fillable = [
        'detail_pemesanan_id',
        'kamar_unit_id',
        'check_in_aktual',
        'check_out_aktual',
        'status_penempatan',
        'catatan',
        'dibatalkan_oleh',
        'dibatalkan_at',
    ];
    public function detailPemesanan()
    {
        return $this->belongsTo(DetailPemesanan::class, 'detail_pemesanan_id');
    }
    public function kamarUnit()
    {
        return $this->belongsTo(KamarUnit::class, 'kamar_unit_id');
    }

    public function unit()
    {
        return $this->kamarUnit();
    }
}
