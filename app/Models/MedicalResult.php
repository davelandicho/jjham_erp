<?php

namespace App\Models;

use App\Enums\MedicalResultStatus;
use App\Enums\MedicalSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalResult extends Model
{
    protected $fillable = [
        'application_id',
        'clinic',
        'exam_date',
        'result',
        'valid_until',
        'source',
        'certificate_path',
        'certificate_name',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'result' => MedicalResultStatus::class,
            'source' => MedicalSource::class,
            'exam_date' => 'date',
            'valid_until' => 'date',
            'verified_at' => 'datetime',
            'remarks' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MedicalResult $medical) {
            $medical->encoded_by ??= auth()->id();

            if (! $medical->exists || $medical->isDirty(['result', 'source', 'valid_until'])) {
                if ($medical->source->isFromClinic()) {
                    $medical->verified_by = auth()->id();
                    $medical->verified_at = now();
                } else {
                    $medical->verified_by = null;
                    $medical->verified_at = null;
                }
            }
        });

        static::deleting(function () {
            throw new \DomainException('Medical results can never be deleted.');
        });
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public const EXPIRY_WARNING_DAYS = 60;

    public function expiresSoon(): bool
    {
        return $this->valid_until !== null
            && $this->valid_until->lte(today()->addDays(self::EXPIRY_WARNING_DAYS));
    }

    public function verify(): void
    {
        $this->verified_by = auth()->id();
        $this->verified_at = now();
        $this->saveQuietly(); // skip the saving hook so it doesn't reset what we just set
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}