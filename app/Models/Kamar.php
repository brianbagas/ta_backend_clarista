<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
// app/Models/Kamar.php
class Kamar extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'id_kamar';
    protected $appends = ['thumbnail'];



    protected $fillable = [
        'tipe_kamar',
        'deskripsi',
        'harga',
        'status_ketersediaan',
        'jumlah_total',
    ];

    // 1. Definisikan Relasi ke Images
    public function images()
    {
        return $this->hasMany(KamarImage::class, 'kamar_id', 'id_kamar');
    }
    public function kamarUnits()
    {
        // Relasi ke KamarUnit (One to Many)
        return $this->hasMany(KamarUnit::class, 'kamar_id', 'id_kamar');
    }
    // 2. Buat Accessor untuk "Thumbnail"
    // Ini akan mengambil foto pertama dari galeri sebagai cover
    public function getThumbnailAttribute()
    {
        // Ambil image pertama
        $firstImage = $this->images->first();

        if ($firstImage) {
            // Panggil accessor 'url' yang sudah kita perbaiki di model KamarImage
            return $firstImage->url;
        }

        // Fallback jika tidak ada gambar
        return asset('images/default-room.jpg');
    }

    /**
     * Get occupied unit IDs for a given date range.
     */
    public function getOccupiedUnitIds($checkIn, $checkOut)
    {
        // Pastikan format Carbon
        $checkIn = \Carbon\Carbon::parse($checkIn);
        $checkOut = \Carbon\Carbon::parse($checkOut);

        return PenempatanKamar::whereHas('detailPemesanan.pemesanan', function ($q) use ($checkIn, $checkOut) {
            $q->where('status_pemesanan', '!=', 'batal')
                ->where(function ($query) use ($checkIn, $checkOut) {
                    $query->where('tanggal_check_in', '<', $checkOut)
                        ->where('tanggal_check_out', '>', $checkIn);
                });
        })
            ->whereNotIn('status_penempatan', ['cancelled', 'checked_out'])
            ->pluck('kamar_unit_id');
    }

    /**
     * Get specific available units for booking.
     * Optionally lock for update to prevent race conditions.
     */
    public function getAvailableUnits($checkIn, $checkOut, $qty, $lock = false)
    {
        $occupiedUnitIds = $this->getOccupiedUnitIds($checkIn, $checkOut);

        $query = KamarUnit::where('kamar_id', $this->id_kamar)
            ->where('status_unit', 'available')
            ->whereNotIn('id', $occupiedUnitIds)
            ->take($qty);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * Get total available stock count.
     */
    public function getAvailableStock($checkIn, $checkOut)
    {
        $occupiedUnitIds = $this->getOccupiedUnitIds($checkIn, $checkOut);

        return KamarUnit::where('kamar_id', $this->id_kamar)
            ->where('status_unit', 'available')
            ->whereNotIn('id', $occupiedUnitIds)
            ->count();
    }

}

