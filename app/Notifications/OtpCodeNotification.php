<?php

namespace App\Notifications;

use App\Enums\OtpPurpose;
use App\Models\ApplicantOtp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
    
        public string $code,
        public OtpPurpose $purpose,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your {$this->purpose->getLabel()} code: {$this->code}")
            ->greeting('Hello!')
            ->line("Use this code to continue your {$this->purpose->getLabel()}:")
            ->line("**{$this->code}**")
            ->line('This code expires in ' . ApplicantOtp::MINUTES_VALID . ' minutes and can be used once.')
            ->line('If you did not request this, you can ignore this email. Never share this code with anyone, including agency staff.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
