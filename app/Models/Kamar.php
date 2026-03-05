<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

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


    public function images()
    {
        return $this->hasMany(KamarImage::class, 'kamar_id', 'id_kamar');
    }
    public function kamarUnits()
    {

        return $this->hasMany(KamarUnit::class, 'kamar_id', 'id_kamar');
    }

    public function getThumbnailAttribute()
    {

        $firstImage = $this->images->first();

        if ($firstImage) {

            return $firstImage->url;
        }


        return asset('images/default-room.jpg');
    }

    /**
     * Get occupied unit IDs for a given date range.
     */
    public function getOccupiedUnitIds($checkIn, $checkOut, $lock = false)
    {

        $checkIn = Carbon::parse($checkIn);
        $checkOut = Carbon::parse($checkOut);

        $query = PenempatanKamar::whereHas('detailPemesanan.pemesanan', function ($q) use ($checkIn, $checkOut) {
            $q->where('status_pemesanan', '!=', 'batal')
                ->where(function ($query) use ($checkIn, $checkOut) {
                    $query->where('tanggal_check_in', '<', $checkOut)
                        ->where('tanggal_check_out', '>', $checkIn);
                });
        })
            ->whereNotIn('status_penempatan', ['cancelled', 'checked_out']);
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->pluck('kamar_unit_id');

    }
    public function getAvailableUnits($checkIn, $checkOut, $qty, $lock = false)
    {
        $occupiedUnitIds = $this->getOccupiedUnitIds($checkIn, $checkOut, $lock);
        $query = KamarUnit::where('kamar_id', $this->id_kamar)
            ->whereIn('status_unit', ['available', 'kotor'])
            ->whereNotIn('id', $occupiedUnitIds)
            ->take($qty);

        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->get();
    }



}

