<?php

namespace App\Models;

use App\Enums\VisaStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Enums\ApplicationStatus;

class Visa extends Model
{
    protected $fillable = [
        'application_id',
        'applied_on',
    ];

    protected function casts(): array
    {
        return [
            'status' => VisaStatus::class,
            'applied_on' => 'date',
            'released_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Visa $visa) {
            $visa->encoded_by ??= auth()->id();
            $visa->status = VisaStatus::Applied;
        });

        static::updating(function (Visa $visa) {
            $original = $visa->getOriginal('status');
            $original = $original instanceof VisaStatus ? $original : VisaStatus::from($original);

            if ($original->isFinal()) {
                throw new \DomainException('A released or denied visa is final. Add a new visa record instead');
            }
        });

        static::deleting(function () {
            throw new \DomainException('Visa records can never be deleted.');
        });
    }

    public static function recordReleased(Application $application, array $data): self
    {
        if (in_array($application->status, [ApplicationStatus::Closed, ApplicationStatus::Withdrawn], true)) {
            throw new \DomainException('This Application is closed or withdrawn. A visa cannot be recorded on it.');
        }

        if ($application->visas()->first()?->status === VisaStatus::Applied) {
            throw new \DomainException('This application already has a visa in progress. Press Release on that record instead.');
        }

        return DB::transaction(function () use ($application, $data) {
            $visa = $application->visas()->create([
                'applied_on' => $data['applied_on'] ?? null,
            ]);

            $visa->release(
                $data['visa_number'],
                $data['released_on'],
                $data['expires_on'],
                $data[visa_path],
                $data['visa_name'] ?? basename($data['visa_path']),
            );

            return $visa;
        });
    }

    public function release(string $visaNumber, $releasedOn, $expiresOn, string $visaPath, string $visaName): void
    {
        if (blank($visaNumber) || blank($releasedOn) || blank($expiresOn) || blank($visaPath)) {
            throw new \DomainException('The visa number, release date, expiry date and visa copy are all required.');
        }

        if (Carbon::parse($expiresOn)->lte(Carbon::parse($releasedOn))) {
            throw new \DomainException('The expiry date must be after the release date.');
        }

        $this->status = VisaStatus::Released;
        $this->visa_number = trim($visaNumber);
        $this->released_on = $releasedOn;
        $this->expires_on = $expiresOn;
        $this->visa_path = $visaPath;
        $this->visa_name = $visaName;
        $this->save();
    }

    public function deny(string $reason): void
    {
        $reason = trim($reason);

        if (blank($reason)) {
            throw new \DomainException('A reason is required when a visa is denied.');
        }

        $this->status = VisaStatus::Denied;
        $this->denied_reason = $reason;
        $this->save();
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->lt(today());
    }

    public function isUsable(): bool
    {
        return $this->status === VisaStatus::Released && ! $this->isExpired();
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
