<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Pembayaran extends Model
{
    use SoftDeletes;
    //
    protected $fillable = [
        'pemesanan_id', 
        'bukti_bayar_path', 
        'status_verifikasi'
    ];
}
