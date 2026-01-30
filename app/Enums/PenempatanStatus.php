<?php

namespace App\Enums;

enum PenempatanStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cleaning = 'cleaning';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Assigned => 'Ditetapkan',
            self::CheckedIn => 'Check-in',
            self::CheckedOut => 'Check-out',
            self::Cleaning => 'Dibersihkan',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
