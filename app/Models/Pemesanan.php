<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pemesanan extends Model
{
    use HasFactory, SoftDeletes;

     protected $fillable = [
        'user_id',
        'tanggal_check_in',
        'tanggal_check_out',
        'total_bayar',
        'status_pemesanan',
        'promo_id', 
        'expired_at',
    ];
    protected $casts = [
    'expired_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function detailPemesanans()
    {
        return $this->hasMany(DetailPemesanan::class, 'pemesanan_id');
    }
    // app/Models/Pemesanan.php

/**
 * Relasi: Satu pemesanan bisa memiliki satu Promo (opsional).
 */
    public function promo()
    {
        return $this->belongsTo(Promo::class);
    }

        public function pembayaran()
    {
        // asumsikan satu pesanan hanya punya satu pembayaran
        return $this->hasOne(Pembayaran::class, 'pemesanan_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class, 'pemesanan_id');
    }

}
