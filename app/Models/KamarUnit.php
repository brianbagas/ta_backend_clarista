<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class KamarUnit extends Model
{
    use SoftDeletes;
    use HasFactory;

    // Pastikan nama tabel benar
    protected $table = 'kamar_units';

    protected $fillable = [
        'kamar_id',
        'nomor_unit',
        'status_unit',
    ];

    public function kamar()
    {
        // Menggunakan id_kamar karena primary key di tabel kamars adalah id_kamar
        return $this->belongsTo(Kamar::class, 'kamar_id', 'id_kamar');
    }
    public function penempatankamars()
    {
        // Relasi ke model PenempatanKamar
        return $this->hasMany(PenempatanKamar::class, 'kamar_unit_id');
    }
}
