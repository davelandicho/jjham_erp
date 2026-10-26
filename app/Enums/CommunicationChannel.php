<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CommunicationChannel: string implements HasLabel
{
    case WhatsApp = 'whatsapp';
    case Viber = 'viber';
    case Email = 'email';
    case Phone = 'phone';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Viber => 'Viber',
            self::Email => 'Email',
            self::Phone => 'Phone',
            self::Other => 'Other'
        };
    }
}
