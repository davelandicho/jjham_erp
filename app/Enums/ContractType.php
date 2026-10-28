<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractType: string implements HasLabel
{
    case Mohre = 'mohre';
    case PoeaStandard = 'poea_standard';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mohre => 'MOHRE Contract',
            self::PoeaStandard => 'POEA-standard Contract',
        };
    }

    public function needsMwoVerification():bool
    {
        return $this === self::PoeaStandard;
    }
}
