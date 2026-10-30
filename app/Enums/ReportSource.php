<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportSource: string implements HasLabel
{
    case Employer = 'employer';
    case Mwo = 'mwo';
    case Family ='family';
    case Worker = 'worker';
    case Agency = 'agency';

    public function getLabel(): string
    {
        return match ($this) {
            self::Employer => 'Employer',
            self::Mwo => 'MWO',
            self::Family => 'Family',
            self::Worker => 'Worker',
            self::Agency => 'Agency Staff',
        };
    }
}