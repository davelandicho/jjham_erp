<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MedicalResultStatus: string implements HasLabel, HasColor
{
    case Pending = 'pending';
    case Fit = 'fit';
    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Fit => 'Fit',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Fit => 'success',
        };
    }
}