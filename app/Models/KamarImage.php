<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class KamarImage extends Model
{
    use HasFactory, SoftDeletes;
    protected $appends = ['url']; // Menambahkan field 'url' ke JSON
    protected $fillable = ['kamar_id', 'image_path'];

    // Relasi ke Kamar
    public function kamar()
    {
        return $this->belongsTo(Kamar::class, 'kamar_id', 'id_kamar');
    }


    public function getUrlAttribute()
    {
        // 1. Jika path sudah berupa URL lengkap (misal dari unsplash), kembalikan langsung
        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
            return $this->image_path;
        }

        // 2. Bersihkan path dari 'public/' atau 'storage/' yang mungkin tersimpan ganda
        // Tujuannya agar kita punya raw path bersih: "kamars/foto.jpg"
        $cleanPath = str_replace(['public/', 'storage/'], '', $this->image_path);

        // 3. Kembalikan URL lengkap ke folder storage
        return asset('storage/' . $cleanPath);
    }


}


