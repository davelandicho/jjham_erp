<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExitClearanceStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case Issued = 'issued';
    case Returned = 'returned';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'RFP Submitted',
            self::Issued => 'Clearance Issued',
            self::Returned => 'Returned by DMW',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Issued => 'success',
            self::Returned => 'danger',
        };
    }

    public function isFinal():bool
    {
        return $this !== self::Submitted;
    }
}