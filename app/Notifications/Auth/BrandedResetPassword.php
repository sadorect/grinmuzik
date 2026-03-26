<?php

namespace App\Notifications\Auth;

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
        $brand = $this->brandContext();
        $isArtist = method_exists($notifiable, 'isArtist') && $notifiable->isArtist();

        return $this->brandedMail([
            'subject' => $isArtist
                ? 'Reset your artist account password'
                : 'Reset your password',
            'preheader' => 'A secure password reset link is ready for your account.',
            'recipientName' => $notifiable->name ?? null,
            'eyebrow' => $isArtist ? 'Artist Security' : 'Account Security',
            'title' => $isArtist
                ? 'Reset access to your artist account.'
                : 'Reset access to your account.',
            'intro' => $isArtist
                ? 'Use the secure link below to choose a new password for your artist tools, uploads, and dashboard access.'
                : 'Use the secure link below to choose a new password and get back into your account.',
            'messageLines' => [
                'If you did not request a password reset, no further action is needed.',
                'For safety, this link expires automatically and should only be used on a device you trust.',
            ],
            'highlights' => [
                [
                    'label' => 'Email',
                    'value' => $notifiable->email,
                ],
                [
                    'label' => 'Reset window',
                    'value' => config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60) . ' minutes',
                ],
                [
                    'label' => 'Account area',
                    'value' => $isArtist ? 'Artist dashboard and uploads' : 'Playlists, favorites, and account settings',
                ],
            ],
            'ctaLabel' => 'Choose New Password',
            'ctaUrl' => $this->resetUrl($notifiable),
            'secondaryCtaLabel' => 'Contact Support',
            'secondaryCtaUrl' => route('contact'),
            'footerNote' => 'This security email was sent from ' . $brand['siteName'] . '.',
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
