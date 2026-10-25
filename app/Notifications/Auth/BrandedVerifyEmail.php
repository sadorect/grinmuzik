<?php

namespace App\Notifications\Auth;

use App\Models\EmailTemplate;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/** Laravel's signed verification link, sent in the branded, admin-editable layout. */
class BrandedVerifyEmail extends VerifyEmail
{
    use BuildsBrandedMail;

    public function toMail($notifiable): MailMessage
    {
        $copy = EmailTemplate::copy('email_verification', [
            'recipient_name' => $notifiable->name ?? null,
            'expires_minutes' => (string) config('auth.verification.expire', 60),
        ]);

        return $this->brandedMail([
            ...$copy->brandedPayload(),
            'recipientName' => $notifiable->name ?? null,
            'ctaUrl' => $this->verificationUrl($notifiable),
        ]);
    }
}
