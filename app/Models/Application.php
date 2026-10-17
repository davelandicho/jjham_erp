<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ApplicationStatus;

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
            if ($application->isDirty('status') || ! $application->status_changed_at) {
                $application->status_changed_at = now();
            }
        });
    }
}
