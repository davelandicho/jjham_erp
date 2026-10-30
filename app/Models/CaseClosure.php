<?php

namespace App\Models;

use App\Enums\ClosureReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseClosure extends Model
{
    protected $fillable = [
        'application_id',
        'reason',
        'closed_on',
        'details',
        'refund_due',
        'evidence_path',
        'evidence_name',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ClosureReason::class,
            'closed_on' => 'date',
            'refund_due' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CaseClosure $closure) {
            if (blank(trim((string) $closure->details))) {
                throw new \DomainException('Closure details are required.');
            }

            if ($closure->closed_on->isFuture()) {
                throw new \DomainException('The closure date cannot be in the future.');
            }

            $closure->encoded_by ??= auth()->id();
        });

        static::updating(function () {
            throw new \DomainException('A case closure is permanent and cannot be edited.');
        });

        static::deleting(function () {
            throw new \DomainException('Case closures can never be deleted.');
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }
}