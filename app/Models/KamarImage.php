<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class KamarImage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['kamar_id', 'image_path'];

    // Relasi ke Kamar
    public function kamar()
    {
        return $this->belongsTo(Kamar::class, 'kamar_id', 'id_kamar');
    }
}