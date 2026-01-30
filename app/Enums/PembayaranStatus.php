<?php

namespace App\Enums;

enum PembayaranStatus: string
{
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::MenungguVerifikasi => 'Menunggu Verifikasi',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Ditolak',
        };
    }
}
