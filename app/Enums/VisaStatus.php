<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VisaStatus: string implements HasColor, HasLabel
{
    case Applied = 'applied';
    case Released = 'released';
    case Denied = 'denied';

    public function getLabel(): string
    {
        return match ($this) {
            self::Applied => 'Applied',
            self::Released => 'Released',
            self::Denied => 'Denied',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Applied => 'warning',
            self::Released => 'success',
            self::Denied => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Applied;
    }
}