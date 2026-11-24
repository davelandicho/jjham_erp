<?php

namespace App\Models;

use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use App\Notifications\OtpCodeNotification;
use Illuminate\Support\Facades\Notification;

class ApplicantOtp extends Model
{
    public const MAX_ATTEMPTS = 5;
    public const MINUTES_VALID = 10;

    protected $fillable = ['applicant_id', 'purpose', 'sent_to', 'expires_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public static function issue(?Applicant $applicant, OtpPurpose $purpose, string $sentTo): array
    {
        // Cancel any earlier unused code for the same purpose
        static::query()
            ->where('sent_to', $sentTo)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = new static([
            'applicant_id' => $applicant?->id,
            'purpose' => $purpose,
            'sent_to' => $sentTo,
            'expires_at' => now()->addMinutes(self::MINUTES_VALID),
        ]);
        $otp->code_hash = Hash::make($code);
        $otp->save();

        return [$otp, $code];
    }

    public static function send(?Applicant $applicant, OtpPurpose $purpose, string $sentTo): self
    {
        [$otp, $code] = static::issue($applicant, $purpose, $sentTo);

        Notification::route('mail', $sentTo)
            ->notify(new OtpCodeNotification($code, $purpose));

        return $otp;
    }

    public function isUsable(): bool
    {
        return $this->used_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < self::MAX_ATTEMPTS;
    }

    public function verify(string $code): bool
    {
        if (! $this->isUsable()) {
            return false;
        }

        if (! Hash::check($code, $this->code_hash)) {
            $this->increment('attempts');
            return false;
        }

        $this->used_at = now();
        $this->save();

        return true;
    }
}