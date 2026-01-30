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

    public function getAvailableStock($checkIn, $checkOut)
    {
        // Gunakan nama kolom: 'kamar_id' (bukan room_type_id)
        $bookedCount = DetailPemesanan::where('kamar_id', $this->id)

            // Asumsi nama relasi di model Detail ke Booking adalah 'pemesanan'
            ->whereHas('pemesanan', function ($query) use ($checkIn, $checkOut) {

                // Filter status & tanggal di tabel INDUK (Pemesanan/Booking)
                $query->where('status', '!=', 'batal')
                    ->where(function ($qDate) use ($checkIn, $checkOut) {
                    $qDate->where('check_in_date', '<', $checkOut)
                        ->where('check_out_date', '>', $checkIn);
                });
            })

            // PENTING: Gunakan 'jumlah_kamar' (sesuai gambar tabelmu)
            ->sum('jumlah_kamar');

        // Hitung Sisa
        $sisa = $this->total_rooms - $bookedCount;

        return max($sisa, 0);
    }

}

