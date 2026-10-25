<?php

namespace App\Notifications\Auth;

use App\Models\EmailTemplate;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BrandedResetPassword extends Notification
{
    use BuildsBrandedMail;
    use Queueable;

    public function __construct(
        public string $token,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isArtist = method_exists($notifiable, 'isArtist') && $notifiable->isArtist();
        $expires = (string) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        $copy = EmailTemplate::copy($isArtist ? 'password_reset_request_artist' : 'password_reset_request', [
            'recipient_name' => $notifiable->name ?? null,
            'email' => $notifiable->email,
            'expires_minutes' => $expires,
        ]);

        return $this->brandedMail([
            ...$copy->brandedPayload(),
            'recipientName' => $notifiable->name ?? null,
            'highlights' => [
                ['label' => 'Email', 'value' => $notifiable->email],
                ['label' => 'Reset window', 'value' => $expires . ' minutes'],
                ['label' => 'Account area', 'value' => $isArtist ? 'Artist dashboard and uploads' : 'Playlists, favorites, and account settings'],
            ],
            'ctaUrl' => $this->resetUrl($notifiable),
            'secondaryCtaLabel' => 'Contact Support',
            'secondaryCtaUrl' => route('contact'),
        ]);
    }

    protected function resetUrl(object $notifiable): string
    {
        if (! $notifiable instanceof CanResetPassword) {
            return route('password.request');
        }

        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
