<?php

namespace App\Enums;

enum PembayaranStatus: string
{
    case MenungguKonfirmasi = 'menunggu_konfirmasi';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::MenungguKonfirmasi => 'Menunggu Konfirmasi',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Ditolak',
        };
    }
}
