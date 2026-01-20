<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Review extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
    'pemesanan_id', 
    'user_id',
    'rating', 
    'komentar'];

      public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi: Satu review dimiliki oleh satu Pemesanan.
     */
    public function pemesanan()
    {
        return $this->belongsTo(Pemesanan::class);
    }
}


