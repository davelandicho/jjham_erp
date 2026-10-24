<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentStatus: string implements HasLabel, HasColor
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected ='rejected';
    case Expired = 'expired';

    public function getLabel(): string 
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
        };
    }

    public function getColor(): string 
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Verified => 'success',
            self::Rejected => 'danger',
            self::Expired => 'gray',
        };
    }
}