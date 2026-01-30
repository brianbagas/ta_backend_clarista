<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $fillable = [
        'nama_bank',
        'nomor_rekening',
        'atas_nama',
        'is_active',
        'logo_path'
    ];
}
