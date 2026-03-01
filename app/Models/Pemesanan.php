<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Support\Str;
class Pemesanan extends Model
{
    use HasFactory, SoftDeletes, HasUlids;

    protected $fillable = [
        'kode_booking',
        'user_id',
        'tanggal_check_in',
        'tanggal_check_out',
        'total_bayar',
        'status_pemesanan',
        'promo_id',
        'expired_at',
        'catatan',
        'alasan_batal',
        'dibatalkan_oleh',
        'dibatalkan_at',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'dibatalkan_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function detailPemesanans()
    {
        return $this->hasMany(DetailPemesanan::class, 'pemesanan_id');
    }


    /**
     * Relasi: Satu pemesanan bisa memiliki satu Promo (opsional).
     */
    public function promo()
    {
        return $this->belongsTo(Promo::class);
    }

    public function pembayaran()
    {

        return $this->hasOne(Pembayaran::class, 'pemesanan_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class, 'pemesanan_id');
    }

    protected static function booted()
    {

        static::creating(function ($pemesanan) {

            if (empty($pemesanan->kode_booking)) {
                $pemesanan->kode_booking = static::generateUniqueCode();
            }

        });
    }

    /**
     * Fungsi khusus untuk generate kode unik
     */
    public static function generateUniqueCode()
    {
        $prefix = 'CL';

        do {

            $randomString = Str::upper(Str::random(6));
            $code = $prefix . '-' . $randomString;


        } while (static::where('kode_booking', $code)->exists());

        return $code;
    }

}
