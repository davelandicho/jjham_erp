<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\EmploymentStatus;
use App\Enums\ReportSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmploymentStatusLog extends Model
{
    protected $fillable = [
        'application_id',
        'status',
        'effective_on',
        'source',
        'remarks',
        'notified',
        'evidence_path',
        'evidence_name',
        'corrects_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmploymentStatus::class,
            'source' => ReportSource::class,
            'effective_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmploymentStatusLog $log) {
            $application = $log->application;

            if ($application?->status !== ApplicationStatus::Arrived) {
                throw new \DomainException('Employment status can only be logged after the application is "Arrived".');
            }

            if ($log->effective_on->isFuture()) {
                throw new \DomainException('The effective date cannot be in the future.');
            }

            $arrivedOn = $application->flights()->first()?->arrived_at?->copy()->startOfDay();

            if ($arrivedOn && $log->effective_on->lt($arrivedOn)) {
                throw new \DomainException('The effective date cannot be before the worker arrived (' . $arrivedOn->format('M j, Y') . ').');
            }

            $current = $application->employmentLogs()->first();

            if ($log->corrects_id) {
                if (! $current || $current->id !== (int) $log->corrects_id) {
                    throw new \DomainException('Only the current (latest) entry can be corrected.');
                }

                if (blank($log->remarks)) {
                    throw new \DomainException('Explain in the remarks why the earlier entry was wrong.');
                }

                if ($current->status === $log->status && $current->source === $log->source && $current->effective_on->equalTo($log->effective_on)) {
                    throw new \DomainException('A correction must change the status, effective date or source.');
                }
                
            } elseif ($current && $current->status === $log->status) {
                throw new \DomainException("The worker is already recorded as {$log->status->getLabel()}.");
            }


            $log->encoded_by ??= auth()->id();
        });

        static::updating(function () {
            throw new \DomainException('Employment status entries cannot be edited. Add a correction instead.');
        });

        static::deleting(function () {
            throw new \DomainException('Employment status entries can never be deleted.');
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function corrects(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatusLog::class, 'corrects_id');
    }

    public function correctedBy(): HasOne
    {
        return $this->hasOne(EmploymentStatusLog::class, 'corrects_id');
    }

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }
}