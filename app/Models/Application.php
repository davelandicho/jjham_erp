<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\DocumentStatus;
use App\Enums\MedicalResultStatus;
use App\Enums\EmployerCommunicationType;
use App\Enums\OfferStatus;
use App\Enums\ContractType;
use App\Enums\ExitClearanceStatus;
use App\Enums\FlightStatus;

class Application extends Model
{
    protected $fillable = [
        'applicant_id',
        'job_order_id',
        'status',
        'status_changed_at',
        'remarks',
        'fee',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'status_changed_at' => 'datetime',
            'fee' => 'decimal:2',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Application $application) {
            if ($application->exists && $application->isDirty('status')) {
                $from = $application->getOriginal('status');
                $from = $from instanceof ApplicationStatus ? $from : ApplicationStatus::from($from);

                if (! $from->canMoveTo($application->status)) {
                    throw new \DomainException(
                        "Cannot move from {$from->getLabel()} to {$application->status->getLabel()}."
                    );
                }
            }
            
            if ($application->isDirty('status')) {
                $application->ensureRequirementsMet($application->status);
            }

            if ($application->isDirty('status') || ! $application->status_changed_at) {
                $application->status_changed_at = now();
            }
        });

        static::saved(function (Application $application) {
            if ($application->wasRecentlyCreated || $application->wasChanged('status')) {
                $application->histories()->create([
                    'from_status' => $application->wasRecentlyCreated
                        ? null
                        : $application->getOriginal('status'),
                    'to_status' => $application->status,
                    'changed_by' => auth()->id(),
                ]);
            }
        });
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StatusHistory::class)->latest('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->latest('id');
    }

    public function medicalResults(): HasMany
    {
        return $this->hasMany(MedicalResult::class)->latest('id');
    }

    public function employerCommunications(): HasMany
    {
        return $this->hasMany(EmployerCommunication::class)->latest('id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class)->latest('id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function visas(): HasMany
    {
        return $this->hasMany(Visa::class)->latest('id');
    }

    public function exitClearances(): HasMany
    {
        return $this->hasMany(ExitClearance::class)->latest('id');
    }

    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class)->latest('id');
    }

    public function EmploymentLogs(): HasMany
    {
        return $this->hasMany(EmploymentStatusLog::class)->latest('id');
    }

    public function amountPaid(): float
    {
        return round((float) $this->payments()->whereNull('voided_at')->sum('amount'), 2);
    }

    public function balance():float
    {
        return max(0, round((float) $this->fee - $this->amountPaid(), 2));
    }

    public function isFullyPaid():bool
    {
        return $this->balance() <= 0;
    }

    public function documentChecklist(): array
    {
        $latest = $this->documents()->get()->unique('document_type_id')->keyBy('document_type_id');

        return $this->jobOrder->documentTypes()
            ->orderBy('document_types.name')
            ->get()
            ->map(function (DocumentType $type) use ($latest) {
                $doc = $latest->get($type->id);

                return [
                    'name' => $type->name,
                    'status' => $doc?->expiry_date?->isPast() ? DocumentStatus::Expired : $doc?->status,
                    'version' => $doc?->version,
                    'expiry_date' => $doc?->expiry_date,
                    'note' => $doc?->rejection_reason,
                ];
            })
            ->all();
    }

    public function checklistSummary(): string
    {
        $items = collect($this->documentChecklist());
        $verified = $items->filter(fn (array $item) => $item['status'] === DocumentStatus::Verified)->count();

        return "{$verified} of {$items->count()} verified";
    }

    public function ensureMedicalAllows(ApplicationStatus $to): void
    {
        $needed = match ($to) {
            ApplicationStatus::MedicalFit => MedicalResultStatus::Fit,
            default => null,
        };

        if ($needed === null) {
            return;
        }

        $latest = $this->medicalResults()->first();

        if (! $latest || $latest->result !== $needed || ! $latest->isVerified()) {
            throw new \DomainException(
                "Cannot move to {$to->getLabel()}: the latest medical result must be a verified {$needed->getLabel()}."
            );
        }
    }

    public function ensureEmployerAllows(ApplicationStatus $to): void
    {
        $needed = match ($to) {
            ApplicationStatus::SentToEmployer => EmployerCommunicationType::PacketSent,
            ApplicationStatus::AwaitingOffer => EmployerCommunicationType::Acknowledgement,
            ApplicationStatus::OfferForSignature => EmployerCommunicationType::OfferLetter,
            default => null,
        };

        if ($needed === null) {
            return;
        }

        if (! $this->employerCommunications()->where('type', $needed->value)->exists()) {
            throw new \DomainException(
                "Cannot move to {$to->getLabel()}: log a \"{$needed->getLabel()}\" entry in the employer log first."
            );
        }
    }

    public function ensureOfferAllows(ApplicationStatus $to): void
    {
        if ($to !== ApplicationStatus::ContractProcessing) {
            return;
        }

        if ($this->offers()->first()?->status !== OfferStatus::Signed) {
            throw new \DomainException (
                "Cannot move to {$to->getLabel()}: the latest offer must be Signed."
            );
        }
    }

    public function ensureContractsAllow(ApplicationStatus $to): void
    {
        if (! in_array($to, [ApplicationStatus::PaymentDue, ApplicationStatus::VisaProcessing], true)) {
            return;
        }

        foreach (ContractType::cases() as $type) {
            $latest = $this->contracts()->where('type', $type->value)->first();

            if (! $latest || ! $latest->isComplete()) {
                $needed = $type->needsMwoVerification() ? 'signed and MWO-verified' : 'signed';

                throw new \DomainException(
                    "Cannot move to {$to->getLabel()}: the latest {$type->getLabel()} must be {$needed}."
                );
            }
        }
    }

    public function ensurePaymentAllows(ApplicationStatus $to): void
    {
        if ($to === ApplicationStatus::PaymentDue && (float) $this->fee <= 0) {
            throw new \DomainException("Cannot move to {$to->getLabel()}: this application has no fee. Move it straight to Visa Processing.");
        }

        if ($to === ApplicationStatus::VisaProcessing && ! $this->isFullyPaid()) {
            $balance = number_format($this->balance(), 2);

            throw new \DomainException(
                "Cannot move to {$to->getLabel()}: ₱{$balance} of the fee is still unpaid."
            );
        }
    }

    public function ensureVisaAllows(ApplicationStatus $to): void
    {
        if ($to !== ApplicationStatus::VisaReleased) {
            return;
        }

        $latest = $this->visas()->first();

        if (! $latest || ! $latest->isUsable()) {
            $problem = match (true) {
                ! $latest => 'no visa has been recorded yet',
                $latest->isExpired() => 'the latest visa has expired',
                default => "the latest visa is still {$latest->status->getLabel()}",
            };

            throw new \DomainException("Cannot move to {$to->getLabel()}: {$problem}.");
        }
    }

    public function ensureExitClearanceAllows(ApplicationStatus $to): void
    {
        $gated = [
            ApplicationStatus::RfpSubmitted,
            ApplicationStatus::ExitClearanceIssued,
            ApplicationStatus::ReadyForDeployment,
        ];

        if (! in_array($to, $gated, true)) {
            return;
        }

        $latest = $this->exitClearances()->first();

        if ($to === ApplicationStatus::RfpSubmitted) {
            if (! $latest || $latest->status === ExitClearanceStatus::Returned) {
                throw new \DomainException("Cannot move to {$to->getLabel()}: record the RFP submission first.");
            }

            return;
        }

        if (! $latest || ! $latest->isUsable()) {
            $problem = match (true) {
                ! $latest => 'no exit clearance has been recorded yet',
                $latest->isExpired() => 'the latest exit clearance has expired',
                default => "the latest exit clearance is \"{$latest->status->getLabel()}\"",
            };

            throw new \DomainException("Cannot move to {$to->getLabel()}: {$problem}.");
        }

        if ($to === ApplicationStatus::ReadyForDeployment && ! $this->visas()->first()?->isUsable()) {
            throw new \DomainException("Cannot move to {$to->getLabel()}: the latest visa is missing or has expired.");
        }
    }

    public function ensureFlightAllows(ApplicationStatus $to): void 
    {
        $gated = [
            ApplicationStatus::ReadyForDeployment,
            ApplicationStatus::Departed,
            ApplicationStatus::Arrived,
        ];

        if (! in_array($to, $gated, true)) {
            return;
        }

        $flight = $this->flights()->first();

        if (! $flight || $flight->status === FlightStatus::Cancelled) {
            throw new \DomainException("Cannot move to {$to->getLabel()}: no active flight has been recorded yet.");
        }

        if ($to === ApplicationStatus::ReadyForDeployment) {
            $departureDay = $flight->departs_at->copy()->startOfDay();
            $clearance = $this->exitClearances()->first();
            $visa = $this->visas()->first();

            if ($clearance?->expires_on && $clearance->expires_on->lt($departureDay)) {
                throw new \DomainException("Cannot move to {$to->getLabel()}: the exit clearance expires before the flight date.");
            }

            if ($visa?->expires_on && $visa->expires_on->lt($departureDay)) {
                throw new \DomainException("Cannot moce to {$TO->getLabel()}: the visa expires before the flight date.");
            }

            return;
        }

        $needed = $to ===ApplicationStatus::Departed
            ? [FlightStatus::Departed, FlightStatus::Arrived]
            : [FlightStatus::Arrived];

        if (! in_array($flight->status, $needed, true)) {
            throw new \DomainException("Cannot move to {$to->getLabel()}: the flight is still \"{$flight->status->getLabel()}\".");
        }
    }

    public function ensureRequirementsMet(ApplicationStatus $to): void
    {
        $this->ensureMedicalAllows($to);
        $this->ensureEmployerAllows($to);
        $this->ensureOfferAllows($to);
        $this->ensureContractsAllow($to);
        $this->ensurePaymentAllows($to);
        $this->ensureVisaAllows($to);
        $this->ensureExitClearanceAllows($to);
        $this->ensureFlightAllows($to);
    }
}
