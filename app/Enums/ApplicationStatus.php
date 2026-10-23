<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ApplicationStatus: string implements HasLabel, HasColor
{
    case Registered = 'registered';
    case DocumentsIncomplete = 'documents_incomplete';
    case ForMedical = 'for_medical';
    case MedicalFit = 'medical_fit';
    case MedicalUnfit = 'medical_unfit';
    case SentToEmployer = 'sent_to_employer';
    case AwaitingOffer = 'awaiting_offer';
    case OfferForSignature = 'offer_for_signature';
    case ContractProcessing = 'contract_processing';
    case PaymentDue = 'payment_due';
    case VisaProcessing = 'visa_processing';
    case VisaReleased = 'visa_released';
    case RfpSubmitted = 'rfp_submitted';
    case ExitClearanceIssued = 'exit_clearance_issued';
    case ReadyForDeployment = 'ready_for_deployment';
    case Departed = 'departed';
    case Arrived = 'arrived';
    case OnHold = 'on_hold';
    case Withdrawn = 'withdrawn';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Registered => 'Registered',
            self::DocumentsIncomplete => 'Documents Incomplete',
            self::ForMedical => 'For Medical',
            self::MedicalFit => 'Medical Fit',
            self::MedicalUnfit => 'Medical Unfit',
            self::SentToEmployer => 'Sent to Employer',
            self::AwaitingOffer => 'Awaiting Offer',
            self::OfferForSignature => 'Offer for Signature',
            self::ContractProcessing => 'Contract Processing',
            self::PaymentDue => 'Payment Due',
            self::VisaProcessing => 'Visa Processing',
            self::VisaReleased => 'Visa Released',
            self::RfpSubmitted => 'RFP Submitted',
            self::ExitClearanceIssued => 'Exit Clearance Issued',
            self::ReadyForDeployment => 'Ready for Deployment',
            self::Departed => 'Departed',
            self::Arrived => 'Arrived',
            self::OnHold => 'On Hold',
            self::Withdrawn => 'Withdrawn',
            self::Closed => 'Closed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Registered, self::DocumentsIncomplete => 'gray',
            self::ForMedical, self::MedicalFit, self::MedicalUnfit => 'warning',
            self::SentToEmployer, self::AwaitingOffer, self::OfferForSignature,
            self::ContractProcessing => 'info',
            self::PaymentDue, self::OnHold => 'danger',
            self::VisaProcessing, self::VisaReleased, self::RfpSubmitted,
            self::ExitClearanceIssued => 'primary',
            self::ReadyForDeployment, self::Departed, self::Arrived => 'success',
            self::Withdrawn, self::Closed => 'gray',
        };
    }

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Registered => [self::DocumentsIncomplete, self::ForMedical],
            self::DocumentsIncomplete => [self::Registered, self::ForMedical],
            self::ForMedical => [self::MedicalFit, self::MedicalUnfit],
            self::MedicalUnfit => [self::ForMedical, self::Closed],
            self::MedicalFit => [self::SentToEmployer],
            self::SentToEmployer => [self::AwaitingOffer],
            self::AwaitingOffer => [self::OfferForSignature, self::Closed],
            self::OfferForSignature => [self::ContractProcessing, self::Closed],
            self::ContractProcessing => [self::PaymentDue, self::VisaProcessing],
            self::PaymentDue => [self::VisaProcessing],
            self::VisaProcessing => [self::VisaReleased, self::Closed],
            self::VisaReleased => [self::RfpSubmitted],
            self::RfpSubmitted => [self::ExitClearanceIssued],
            self::ExitClearanceIssued => [self::ReadyForDeployment],
            self::ReadyForDeployment => [self::Departed],
            self::Departed => [self::Arrived],
            self::Arrived => [],
            self::OnHold => [self::Registered, self::ForMedical, self::SentToEmployer,
                self::AwaitingOffer, self::ContractProcessing, self::VisaProcessing],
            self::Withdrawn, self::Closed => [],
        };
    }

    public function canMoveTo(self $next): bool
    {
        if ($next === $this) {
            return true;
        }

        // The PDF: Withdrawn and On Hold can be reached from any open status.
        if (in_array($next, [self::Withdrawn, self::OnHold], true)) {
            return ! in_array($this, [self::Withdrawn, self::Closed, self::Arrived], true);
        }

        return in_array($next, $this->allowedNext(), true);
    }
}