<?php

namespace App\Models;

use App\Enums\CommunicationChannel;
use App\Enums\EmployerCommunicationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployerCommunication extends Model
{
    protected $fillable = [
        'application_id',
        'type',
        'channel',
        'communicated_on',
        'contact_name',
        'contact_number',
        'evidence_path',
        'evidence_name',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => EmployerCommunicationType::class,
            'channel' => CommunicationChannel::class,
            'communicated_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmployerCommunication $entry) {
            $entry->encoded_by ??= auth()->id();

            if ($entry->type->requiresEvidence() && blank($entry->evidence_path)) {
                throw new \DomainException("{$entry->type->getLabel()} needs a screenshot or file as evidence.");
            }
        });

        static::updating(function () {
            throw new \DomainException('Employer log entries cannot be edited. Add a new entry instead.');
        });

        static::deleting(function () {
            throw new \DomainException('Employer log entries can never be deleted.');
        });
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