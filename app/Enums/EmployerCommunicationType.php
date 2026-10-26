<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmployerCommunicationType: string implements HasLabel, HasColor
{
    case PacketSent = 'packet_sent';
    case Acknowledgement = 'acknowledgement';
    case OfferLetter = 'offer_letter';
    case Cancellation = 'cancellation';
    case Ticket = 'ticket';
    case Other ='other';

    public function getLabel(): string
    {
        return match ($this) {
            self::PacketSent => 'Packet sent to employer',
            self::Acknowledgement => 'Employer acknowledged',
            self::OfferLetter => 'Offer letter received',
            self::Cancellation => 'Employer cancelled',
            self::Ticket => 'Ticket received',
            self::Other => 'Other',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PacketSent => 'gray',
            self::Acknowledgement, self::OfferLetter => 'info',
            self::Cancellation => 'danger',
            self::Ticket => 'success',
            self::Other => 'gray',
        };
    }

    public function isFromEmployer(): bool
    {
        return $this !== self::PacketSent;
    }

    public function requiresEvidence():bool {
        return in_array($this, [
            self::Acknowledgement,
            self::OfferLetter,
            self::Cancellation,
            self::Ticket,
        ], true);
    }
}
