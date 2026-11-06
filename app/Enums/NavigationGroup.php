<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

// The order of the cases = the order of the groups in the sidebar.
enum NavigationGroup implements HasLabel
{
    case Recruitment;
    case DocumentsMedical;
    case Processing;
    case Deployment;
    case Finance;
    case Settings;

    public function getLabel(): string
    {
        return match ($this) {
            self::Recruitment => 'Recruitment',
            self::DocumentsMedical => 'Documents & Medical',
            self::Processing => 'Processing',
            self::Deployment => 'Deployment',
            self::Finance => 'Finance',
            self::Settings => 'Settings',
        };
    }
}
