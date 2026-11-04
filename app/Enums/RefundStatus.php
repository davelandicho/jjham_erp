<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RefundStatus: string implements HasColor, HasLabel
{
    case Requested ='requested';
    case Approved = 'approved';
    case Declined = 'declined';
    case Refunded = 'refunded';

    public function getLabel(): string 
    {
        return match ($this) {
            self::Requested => 'Refund Requested',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
            self::Refunded => 'Refunded',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Approved => 'info',
            self::Declined => 'danger',
            self::Refunded => 'success',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Declined, self::Refunded], true);
    }

    public function allowedNext(): array
    {
        return match ($this) {
            self::Requested => [self::Approved, self::Declined],
            self::Approved => [self::Refunded],
            self::Declined, self::Refunded => [],
        };
    }

    public function canMoveTo(self $next): bool 
    {
        return in_array($next, $this->allowedNext(), true);
    }
}