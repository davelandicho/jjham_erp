<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OfferStatus: string implements HasLabel, HasColor
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Declined = 'declined';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending signature',
            self::Signed => 'Signed',
            self::Declined =>'Declined',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Signed => 'success',
            self::Declined => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}