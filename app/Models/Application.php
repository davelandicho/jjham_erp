<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
