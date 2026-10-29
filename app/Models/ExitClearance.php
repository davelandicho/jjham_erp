<?php

namespace App\Models;

use App\Enums\ExitClearanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ExitClearance extends Model
{
    protected $fillable = [
        'application_id',
        'rfp_reference',
        'submitted_on',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExitClearanceStatus::class,
            'submitted_on' => 'date',
            'issued_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExitClearance $clearance) {
            $clearance->encoded_by ??= auth()->id();
            $clearance->status = ExitClearanceStatus::Submitted;
        });

        static::updating(function (ExitClearance $clearance) {
            $original = $clearance->getOriginal('status');
            $original = $original instanceof ExitClearanceStatus ? $original : ExitClearanceStatus::from($original);

            if ($original->isFinal()) {
                throw new \DomainException('An issued or returned exit clearance is final. Submit a new RFP record instead.');
            }
        });

        static::deleting(function () {
            throw new \DomainException('Exit Clearance records can never be deleted');
        });
    }

    public function issue(string $clearanceNumber, $issuedOn, $expiresOn, string $clearancePath, string $clearanceName): void
    {
        if (blank($clearanceNumber) || blank($issuedOn) || blank($expiresOn) || blank($clearancePath)) {
            throw new \DomainException('The clearance number, issue date, expiry date and clearance copy are all required.');
        }

        if (Carbon::parse($expiresOn)->lte(Carbon::parse($issuedOn))) {
            throw new \DomainException('The expiry date must be after the issue date.');
        }

        $this->status = ExitClearanceStatus::Issued;
        $this->clearance_number = trim($clearanceNumber);
        $this->issued_on = $issuedOn;
        $this->expires_on = $expiresOn;
        $this->clearance_path = $clearancePath;
        $this->clearance_name = $clearanceName;
        $this->save();
    }

    public function markReturned(string $reason): void
    {
        $reason = trim($reason);

        if (blank($reason)) {
            throw new \DomainException('A reason is required when the DMW returns an RFP.');
        }

        $this->status = ExitClearanceStatus::Returned;
        $this->returned_reason = $reason;
        $this->save();
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->lt(today());
    }

    public function isUsable(): bool
    {
        return $this->status === ExitClearanceStatus::Issued && ! $this->isExpired();
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
