<?php

namespace App\Models;

use App\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; 

class Offer extends Model
{
    protected $fillable = [
        'application_id',
        'released_on',
        'position',
        'salary',
        'currency',
        'signing_deadline',
        'offer_path',
        'offer_name',
    ];

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'released_on' => 'date',
            'signing_deadline' => 'date',
            'signed_on' => 'date',
            'salary' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Offer $offer) {
            $offer->encoded_by ??= auth()->id();
            $offer->status = OfferStatus::Pending;
        });

        static::updating(function (Offer $offer) {
            $original = $offer->getOriginal('status');
            $original = $original instanceof OfferStatus ? $original : OfferStatus::from($original);

            if ($original->isFinal()) {
                throw new \DomainException('A signed or declined offer can no longer be changed.');
            }
        });

        static::deleting(function () {
            throw new \DomainException('Offers can never be deleted.');
        });
    }

    public function recordSignature(string $signedPath, string $signedName, $signedOn): void
    {
        if (blank($signedPath) || blank($signedOn)) {
            throw new \DomainException('A signed copy and the signing date are required.');
        }

        $this->status = OfferStatus::Signed;
        $this->signed_path = $signedPath;
        $this->signed_name = $signedName;
        $this->signed_on = $signedOn;
        $this->signed_by = auth()->id();
        $this->save();
    }

    public function decline(string $reason): void
    {
        if (blank($reason)) {
            throw new \DomainException('A reason is required when an offer is declined.');
        }

        $this->status = OfferStatus::Declined;
        $this->declined_reason = $reason;
        $this->save();
    }

    public function isOverdue(): bool
    {
        return $this->status === OfferStatus::Pending
            && $this->signing_deadline?->isPast();
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
