<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class Pembayaran extends Model
{
    use SoftDeletes, HasUlids;
    //
    protected $fillable = [
        'pemesanan_id',
        'bukti_bayar_path',
        'jumlah_bayar',
        'bank_tujuan',
        'nama_pengirim',
        'tanggal_bayar',
        'status_verifikasi'
    ];
}
