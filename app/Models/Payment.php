<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'application_id',
        'amount',
        'paid_on',
        'method',
        'reference_number',
        'proof_path',
        'proof_name',
        'remarks',
    ];

    public const VOID_FIELDS = ['voided_at', 'void_reason', 'voided_by', 'updated_at'];

    protected function casts():array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted():void
    {
        static::creating(function (Payment $payment) {
            $payment->received_by ??= auth()->id();

            if((float) $payment->amount <= 0) {
                throw new \DomainException('A payment amount must be greater than zero.');
            }
        });

        static::updating(function (Payment $payment) {
            if ($payment->getOriginal('voided_at')) {
                throw new \DomainException('A voided payment can no longer be changed.');
            }

            $changed = array_keys($payment->getDirty());

            if (array_diff($changed, self::VOID_FIELDS)) {
                throw new \DomainException('Payments cannot be edited. Void it and record a new payment instead.');
            }
        });

        static::deleting(function () {
            throw new \DomainException('Payments can never be deleted. Void it instead');
        });
    }

    public function isVoided(): bool
    {
        return $this->voided_at !==null;
    }

    public function void(string $reason): void
    {
        $reason = trim($reason);

        if (blank($reason)) {
            throw new \DomainException('A reason is required to void a payment.');
        }

        if ($this->isVoided()) {
            throw new \DomainException('This payment is already voided.');
        }

        $this->voided_at = now();
        $this->void_reason = $reason;
        $this->voided_by = auth()->id();
        $this->save();
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function reciever():BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
    
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
