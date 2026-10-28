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

class Application extends Model
{
    protected $fillable = [
        'applicant_id',
        'job_order_id',
        'status',
        'status_changed_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'status_changed_at' => 'datetime',
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

    public function ensureRequirementsMet(ApplicationStatus $to): void
    {
        $this->ensureMedicalAllows($to);
        $this->ensureEmployerAllows($to);
        $this->ensureOfferAllows($to);
    }
}
