<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
Use Illuminate\Support\Str;

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

    protected $hidden = ['passport_number_hash', 'password', 'remember_token', 'portal_setup_code_hash'];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'passport_issue_date' => 'date',
            'passport_expiry_date' => 'date',
            'passport_number' => 'encrypted',
            'password' => 'hashed',
            'portal_setup_code_expires_at' => 'datetime',
            'portal_activated_at' => 'datetime'
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

    public function issuePortalSetupCode(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .=$alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $this->portal_setup_code_hash = Hash::make($code);
        $this->portal_setup_code_expires_at = now()->addHours(72);
        $this->save();

        return substr($code, 0, 4) . '-' . substr($code, 4);
    }

     public function hasValidPortalSetupCode(string $code): bool
    {
        return $this->portal_setup_code_hash !== null
            && $this->portal_setup_code_expires_at?->isFuture()
            && Hash::check($code, $this->portal_setup_code_hash);
    }

    public function completePortalSetup(string $password): void
    {
        $this->password = $password;
        $this->portal_setup_code_hash = null;
        $this->portal_setup_code_expires_at = null;
        $this->portal_activated_at ??= now();
        $this->setRememberToken(Str::random(60));
        $this->save();
    }
}
