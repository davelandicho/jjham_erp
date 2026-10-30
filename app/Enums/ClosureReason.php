<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ClosureReason: string implements HasLabel
{
    case ApplicantWithdrew = 'applicant_withdrew';
    case EmployerCancelled = 'employer_cancelled';
    case OfferDeclined = 'offer_declined';
    case VisaDenied = 'visa_denied';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::ApplicantWithdrew => 'Applicant Withdrew',
            self::EmployerCancelled => 'Employer Cancelled',
            self::OfferDeclined => 'Offer Declined',
            self::VisaDenied => 'Visa Denied',
            self::Other => 'Other',
        };
    }

    public function applicationStatus(): ApplicationStatus
    {
        return $this === self::ApplicantWithdrew
            ? ApplicationStatus::Withdrawn
            : ApplicationStatus::Closed;
    }
}