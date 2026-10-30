<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FlightStatus: string implements HasColor, HasLabel
{
    case Booked = 'booked';
    case Departed = 'departed';
    case Arrived = 'arrived';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Booked => 'Ticket Booked',
            self::Departed => 'Departed',
            self::Arrived => 'Arrived',
            self::Cancelled => 'Cancelled / Rebooked',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Booked => 'warning',
            self::Departed => 'info',
            self::Arrived => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Arrived, self::Cancelled], true);
    }
}