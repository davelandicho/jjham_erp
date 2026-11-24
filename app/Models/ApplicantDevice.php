<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApplicantDevice extends Model
{
    public const DAYS_VALID = 30;
    public const COOKIE = 'portal_device';

    protected $fillable = ['applicant_id', 'user_agent', 'ip_address', 'last_used_at', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    // Creates a trusted device and returns the plain token (for the cookie).
    public static function issue(Applicant $applicant, Request $request): string
    {
        $token = Str::random(64);

        $device = new static([
            'applicant_id' => $applicant->id,
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(self::DAYS_VALID),
        ]);
        $device->token_hash = hash('sha256', $token);
        $device->save();

        return $token;
    }

    // Finds a still-valid device for this applicant from the cookie token.
    public static function findValid(Applicant $applicant, ?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return static::query()
            ->where('applicant_id', $applicant->id)
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();
    }
}
