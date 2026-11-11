<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Applicant extends Authenticatable
{
    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'birthdate', 'sex',
        'civil_status', 'nationality', 'address', 'mobile', 'whatsapp',
        'email', 'emergency_contact_name', 'emergency_contact_number',
        'passport_number', 'passport_issue_date',
        'passport_expiry_date', 'passport_place_of_issue',
        'height_cm', 'weight_kg', 'experience',
    ];

    protected $hidden = ['passport_number_hash', 'password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'passport_issue_date' => 'date',
            'passport_expiry_date' => 'date',
            'passport_number' => 'encrypted',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Applicant $applicant) {
            $applicant->passport_number_hash = $applicant->passport_number
                ? hash('sha256', strtoupper(trim($applicant->passport_number)))
                : null;
        });
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
