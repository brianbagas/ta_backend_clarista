<?php

namespace App\Enums;

enum PemesananStatus: string
{
    case MenungguPembayaran = 'menunggu_pembayaran';
    case MenungguKonfirmasi = 'menunggu_konfirmasi';
    case Dikonfirmasi = 'dikonfirmasi';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public function label(): string
    {
        return match ($this) {
            self::MenungguPembayaran => 'Menunggu Pembayaran',
            self::MenungguKonfirmasi => 'Menunggu Konfirmasi',
            self::Dikonfirmasi => 'Dikonfirmasi',
            self::Selesai => 'Selesai',
            self::Batal => 'Dibatalkan',
        };
    }
}
