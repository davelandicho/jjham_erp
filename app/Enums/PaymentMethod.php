<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash ='cash';
    case BankTransfer = 'bank_transfer';
    case EWallet = 'e_wallet';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::EWallet => 'E-wallet (Gcash / Maya)',
        };
    }
}