<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class KamarUnit extends Model
{
    use SoftDeletes;
    use HasFactory;


    protected $table = 'kamar_units';

    protected $fillable = [
        'kamar_id',
        'nomor_unit',
        'status_unit',
    ];

    public function kamar()
    {
        return $this->belongsTo(Kamar::class, 'kamar_id', 'id_kamar');
    }
    public function penempatankamars()
    {
        return $this->hasMany(PenempatanKamar::class, 'kamar_unit_id');
    }
}
