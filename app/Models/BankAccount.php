<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nama_bank',
        'nomor_rekening',
        'atas_nama',
        'is_active',
        'logo_path'
    ];
}
