<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'application_id',
        'amount',
        'reason',
    ];

    public const LOCKED_FIELDS = ['application_id', 'amount', 'reason', 'requested_by'];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'decided_at' => 'datetime',
            'refunded_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Refund $refund) {
            if (! $refund->application?->closure?->refund_due) {
                throw new \DomainException('A refund can only be requested on a closed case marked "refund due".');
            }

            if (blank(trim((string) $refund->reason))) {
                throw new \DomainException('A reason is required to request a refund.');
            }

            if ((float) $refund->amount <= 0) {
                throw new \DomainException('A refund amount must be greater than zero.');
            }

            $hasOpenRefund = Refund::where('application_id', $refund->application_id)
                ->whereIn('status', [RefundStatus::Requested, RefundStatus::Approved])
                ->exists();

            if ($hasOpenRefund) {
                throw new \DomainException('This case already has an open refund. Finish that one first.');
            }

            $refund->ensureWithinRefundable();

            $refund->status = RefundStatus::Requested;
            $refund->requested_by ??= auth()->id();
        });

        static::updating(function (Refund $refund) {
            $from = $refund->getOriginal('status');

            if ($from->isFinal()) {
                throw new \DomainException('A declined or refunded refund can no longer be changed.');
            }

            if ($refund->isDirty(self::LOCKED_FIELDS)) {
                throw new \DomainException('The refund amount and reason cannot be edited. Decline it and file a new one.');
            }

            if ($refund->isDirty('status') && ! $from->canMoveTo($refund->status)) {
                throw new \DomainException("A {$from->getLabel()} refund cannot become {$refund->status->getLabel()}.");
            }
        });

        static::deleting(function () {
            throw new \DomainException('Refunds can never be deleted.');
        });
    }

    public function approve(?string $remarks = null): void
    {
        $this->ensureAwaitingDecision();

        $this->status = RefundStatus::Approved;
        $this->decided_at = now();
        $this->decided_by = auth()->id();
        $this->decision_remarks = filled($remarks) ? trim($remarks) : null;
        $this->save();
    }

    public function decline(string $remarks): void
    {
        $this->ensureAwaitingDecision();

        if (blank(trim($remarks))) {
            throw new \DomainException('A reason is required to decline a refund.');
        }

        $this->status = RefundStatus::Declined;
        $this->decided_at = now();
        $this->decided_by = auth()->id();
        $this->decision_remarks = trim($remarks);
        $this->save();
    }

    public function markRefunded(
        $refundedOn,
        PaymentMethod $method,
        string $referenceNumber,
        ?string $proofPath = null,
        ?string $proofName = null,
    ): void {
        if ($this->status !== RefundStatus::Approved) {
            throw new \DomainException('Only an approved refund can be paid out.');
        }

        $this->refunded_on = $refundedOn;

        if ($this->refunded_on->isFuture()) {
            throw new \DomainException('The refund date cannot be in the future.');
        }

        if (blank(trim($referenceNumber))) {
            throw new \DomainException('A reference number is required to record a payout.');
        }

        $this->ensureWithinRefundable();

        $this->status = RefundStatus::Refunded;
        $this->method = $method;
        $this->reference_number = trim($referenceNumber);
        $this->proof_path = $proofPath;
        $this->proof_name = $proofName;
        $this->paid_by = auth()->id();
        $this->save();
    }

    public function ensureWithinRefundable(): void
    {
        $refundable = $this->application->refundableAmount();

        if ((float) $this->amount > $refundable) {
            throw new \DomainException('The refund cannot be more than the refundable amount of ' . number_format($refundable, 2) . '.');
        }
    }

    private function ensureAwaitingDecision(): void
    {
        if ($this->status !== RefundStatus::Requested) {
            throw new \DomainException('This refund has already been decided.');
        }
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}