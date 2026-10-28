<?php

namespace App\Models;

use App\Enums\ContractType;
use App\Enums\MwoStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $fillable = [
        'application_id',
        'type',
        'contract_number',
        'issued_on',
        'contract_path',
        'contract_name',
    ];

    public const SIGNED_LOCK = [
        'application_id', 'type', 'contract_number', 'issued_on', 'contract_path', 'contract_name',
        'signed_on', 'signed_path', 'signed_name', 'signed_by',
    ];

    public const MWO_FIELDS = [
        'mwo_status', 'mwo_submitted_on', 'mwo_verified_on',
        'mwo_proof_path', 'mwo_proof_name', 'mwo_return_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContractType::class,
            'mwo_status' => MwoStatus::class,
            'issued_on' => 'date',
            'signed_on' => 'date',
            'mwo_submitted_on' => 'date',
            'mwo_verified_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Contract $contract) {
            $contract->encoded_by ??= auth()->id();
        });

        static::updating(function (Contract $contract) {
            if ($contract->getOriginal('signed_on') && $contract->isDirty(self::SIGNED_LOCK)) {
                throw new \DomainException('A signed contract can no longer be changed. Upload a new contract instead.');
            }

            $mwo = $contract->getOriginal('mwo_status');
            $mwo = is_string($mwo) ? MwoStatus::from($mwo) : $mwo;

            if ($mwo && $mwo !== MwoStatus::Submitted && $contract->isDirty(self::MWO_FIELDS)) {
                throw new \DomainException('The MWO result is final. Upload a corrected contract as a new record.');
            }
        });

        static::deleting(function () {
            throw new \DomainException('Contracts can never be deleted.');
        });
    }

    public function isSigned(): bool
    {
        return $this->signed_on !== null;
    }

    public function isComplete(): bool
    {
        if (! $this->isSigned()) {
            return false;
        }

        return ! $this->type->needsMwoVerification()
            || $this->mwo_status === MwoStatus::Verified;
    }

    public function recordSignature(string $signedPath, string $signedName, $signedOn): void
    {
        if ($this->isSigned()) {
            throw new \DomainException('This contract is already signed.');
        }

        if (blank($signedPath) || blank($signedOn)) {
            throw new \DomainException('A signed copy and the signing date are required.');
        }

        $this->signed_path = $signedPath;
        $this->signed_name = $signedName;
        $this->signed_on = $signedOn;
        $this->signed_by = auth()->id();
        $this->save();
    }

    public function submitToMwo($submittedOn): void
    {
        if (! $this->type->needsMwoVerification()) {
            throw new \DomainException('Only the POEA-standard contract goes to the MWO.');
        }

        if (! $this->isSigned()) {
            throw new \DomainException('Sign the contract before submitting it to the MWO.');
        }

        if ($this->mwo_status !== null) {
            throw new \DomainException('This contract was already submitted to the MWO.');
        }

        $this->mwo_status = MwoStatus::Submitted;
        $this->mwo_submitted_on = $submittedOn;
        $this->save();
    }

    public function markMwoVerified($verifiedOn, string $proofPath, string $proofName): void
    {
        $this->ensureAwaitingMwo();

        if (blank($verifiedOn) || blank($proofPath)) {
            throw new \DomainException('The verification date and proof are required.');
        }

        $this->mwo_status = MwoStatus::Verified;
        $this->mwo_verified_on = $verifiedOn;
        $this->mwo_proof_path = $proofPath;
        $this->mwo_proof_name = $proofName;
        $this->save();
    }

    public function markMwoReturned(string $reason): void
    {
        $this->ensureAwaitingMwo();

        $reason = trim($reason);

        if (blank($reason)) {
            throw new \DomainException('A reason is required when the MWO returns a contract.');
        }

        $this->mwo_status = MwoStatus::Returned;
        $this->mwo_return_reason = $reason;
        $this->save();
    }

    private function ensureAwaitingMwo(): void
    {
        if ($this->mwo_status !== MwoStatus::Submitted) {
            throw new \DomainException('This contract is not waiting for an MWO result.');
        }
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