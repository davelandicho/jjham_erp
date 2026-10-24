<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MedicalSource: string implements HasLabel
{
    case ClinicEmail = 'clinic_email';
    case ClinicPortal = 'clinic_portal';
    case Applicant = 'applicant';

    public function getLabel(): string
    {
        return match ($this) {
            self::ClinicEmail => 'Clinic email',
            self::ClinicPortal => 'Clinic portal',
            self::Applicant => 'Brought by applicant',
        };
    }

    public function isFromClinic(): bool
    {
        return $this !== self::Applicant;
    }
}