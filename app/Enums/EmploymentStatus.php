<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmploymentStatus: string implements HasColor, HasLabel
{
    case Working = 'working';
    case Resigned = 'resigned';
    case Awol = 'awol';
    case ContractEnded = 'contract_ended';
    case Repatriated = 'repatriated';
    case Terminated = 'terminated';

    public function getLabel(): string
    {
        return match ($this) {
            self::Working => 'Working',
            self::Resigned => 'Resigned',
            self::Awol => 'AWOL',
            self::ContractEnded => 'Contract Ended',
            self::Repatriated => 'Repatriated',
            self::Terminated => 'Terminated',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Working => 'success',
            self::Resigned, self::ContractEnded => 'gray',
            self::Repatriated => 'info',
            self::Awol, self::Terminated => 'danger',
        };
    }
}