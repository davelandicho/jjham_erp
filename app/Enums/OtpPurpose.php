<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OtpPurpose: string implements HasLabel
{
    case Login = 'login';
    case PasswordReset = 'password_reset';
    case Registration = 'registration';
    case Signing = 'signing';

    public function getLabel(): string
    {
        return match ($this){
            self::Login => 'Login',
            self::PasswordReset => 'Password Reset',
            self::Registration => 'Registration',
            self::Signing => 'Document Signing',
        };
    }
}
