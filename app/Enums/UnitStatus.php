<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Unavailable => 'Tidak Tersedia',
            self::Maintenance => 'Maintenance',
        };
    }
}
