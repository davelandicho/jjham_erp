<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MwoStatus: string implements HasColor, HasLabel
{
    case Submitted ='submitted';
    case Verified = 'verified';
    case Returned = 'returned';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted to MWO',
            self::Verified => 'MWO verified',
            self::Returned => 'Returned by MWO',
        };
    }

    public function getColor():string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Verified => 'success',
            self::Returned => 'danger',
        };
    }
}
