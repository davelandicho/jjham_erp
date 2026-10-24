<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MedicalResultStatus: string implements HasLabel, HasColor
{
    case Pending = 'pending';
    case Fit = 'fit';
    case Unfit = 'unfit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Fit => 'Fit',
            self::Unfit => 'Unfit',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Fit => 'success',
            self::Unfit => 'danger',
        };
    }
}