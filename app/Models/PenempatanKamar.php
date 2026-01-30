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
        'status_penempatan',
        'check_in_aktual',
        'check_out_aktual',
        'status_penempatan', // 👈 WAJIB ADA (Penyebab error CheckInOut)
        'catatan',           // 👈 Tambahkan ini juga
        'dibatalkan_oleh',
    ];

    // Relasi: Penempatan ini milik satu Detail Pemesanan
    public function detailPemesanan()
    {
        return $this->belongsTo(DetailPemesanan::class, 'detail_pemesanan_id');
    }

    // Relasi: Penempatan ini merujuk ke satu Unit Fisik Kamar
    public function kamarUnit()
    {
        return $this->belongsTo(KamarUnit::class, 'kamar_unit_id');
    }

    // Alias untuk backward compatibility
    public function unit()
    {
        return $this->kamarUnit();
    }
}
