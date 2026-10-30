<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\FlightStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Flight extends Model
{
    protected $fillable = [
        'application_id',
        'ticket_number',
        'airline',
        'flight_number',
        'origin',
        'destination',
        'departs_at',
        'arrives_at',
        'ticket_path',
        'ticket_name',
    ];

    private const TICKET_FIELDS = [
        'ticket_number', 'airline', 'flight_number', 'origin', 'destination',
        'departs_at', 'arrive_at', 'ticket_path', 'ticket_name',
    ];

    protected function casts(): array
    {
        return [
            'status' => FlightStatus::class,
            'departs_at' => 'datetime',
            'arrives_at' => 'datetime',
            'departed_at' => 'datetime',
            'arrived_at' => 'datetime',
        ];
    }

    protected static function booted(): void 
    {
        static::creating(function (Flight $flight) {
            $current = Flight::where('application_id', $flight->application_id)->latest('id')->first();

            if ($current && $current->status !== FlightStatus::Cancelled) {
                throw new \DomainException('This application already has an active flight. Cancel it first if the ticket is rebooked.');
            }

            $flight->encoded_by ??= auth()->id();
            $flight->status = FlightStatus::Booked;
        });

        static::saving(function (Flight $flight) {
            $flight->origin = strtoupper(trim($flight->origin));
            $flight->destination = strtoupper(trim($flight->destination));
            $flight->flight_number = strtoupper(trim($flight->flight_number));

            if ($flight->arrives_at && $flight->departs_at && $flight->arrives_at->lte($flight->departs_at)) {
                throw new \DomainException('The scheduled arrival must be after the scheduled departure.');
            }
        });

        static::updating(function (Flight $flight) {
            $original = $flight->getOriginal('status');
            $original = $original instanceof FlightStatus ? $original : FlightStatus::from($original);

            if ($original->isFinal()) {
                throw new \DomainException('An arrived or cancelled flight is final. Add a new flight record instead.');
            }

            if ($original === FlightStatus::Departed && $flight->isDirty(self::TICKET_FIELDS)) {
                throw new \DomainException('Ticket details cannot be changed after departure.');
            }
        });

        static::deleting(function() {
            throw new \DomainException('Flight records can never be deleted.');
        });
    }

    public function markDeparted($departedAt): void
    {
        if ($this->status !== FlightStatus::Booked) {
            throw new \DomainException('Only a booked flight can be marked as departed.');
        }

        if ($this->application->status !== ApplicationStatus::ReadyForDeployment) {
            throw new \DomainException('The application must be "Readt for Deployment" before departure is confirmed.');
        }

        if (blank($departedAt) || Carbon::parse($departedAt)->isFuture()) {
            throw new \DomainException('Enter the actual departure time (it cannot be in the future).');
        }

        $this->status = FlightStatus::Departed;
        $this->departed_at = $departedAt;
        $this->save();
    }

    public function confirmArrival($arrivedAt): void
    {
        if ($this->status !== FlightStatus::Departed) {
            throw new \DomainException('Only a departed flight can be confirmed as arrived.');
        }

        if (blank($arrivedAt) || Carbon::parse($arrivedAt)->lte($this->departed_at)) {
            throw new \DomainException('The touchdown time must be after the departure time.');
        }

        if (Carbon::parse($arrivedAt)->isFuture()) {
            throw new \DomainException('Enter the actual touchdown time (it cannot be from the future).');
        }

        $this->status = FlightStatus::Arrived;
        $this->arrived_at = $arrivedAt;
        $this->save();
    }

    public function cancel(string $reason): void
    {
        $reason = trim($reason);

        if ($this->status !== FlightStatus::Booked) {
            throw new \DomainException('Only a booked flight can be cancelled.');
        }

        if (blank($reason)) {
            throw new \DomainException('A reason is required when cancelling or rebooking a flight.');
        }

        $this->status = FlightStatus::Cancelled;
        $this->cancelled_reason = $reason;
        $this->save();
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
