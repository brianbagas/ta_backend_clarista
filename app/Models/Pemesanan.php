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

    protected static function booted()
    {
        // Event 'creating' jalan SEBELUM data di-insert ke database
        static::creating(function ($pemesanan) {

            // Cek apakah kode_booking sudah ada? (misal dari input manual)
            // Jika kosong, baru kita generate.
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
        $prefix = 'CL'; // Kode prefix (CL = Clarista)

        do {
            // Generate string acak 6 karakter, huruf besar semua
            // Contoh hasil: CL-X7B9A2
            $randomString = Str::upper(Str::random(6));
            $code = $prefix . '-' . $randomString;

            // Cek di database apakah kode ini sudah ada?
        } while (static::where('kode_booking', $code)->exists());

        return $code;
    }

}
