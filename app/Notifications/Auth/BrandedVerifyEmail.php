<?php

namespace App\Notifications\Auth;

use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BrandedVerifyEmail extends BaseVerifyEmail
{
    use BuildsBrandedMail;
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $brand = $this->brandContext();
        $verificationUrl = $this->verificationUrl($notifiable);
        $isArtist = method_exists($notifiable, 'isArtist') && $notifiable->isArtist();

        return $this->brandedMail([
            'subject' => 'Welcome to ' . $brand['siteName'] . ' - verify your email',
            'preheader' => 'Confirm your email to unlock your ' . ($isArtist ? 'artist' : 'listening') . ' experience.',
            'recipientName' => $notifiable->name ?? null,
            'eyebrow' => $isArtist ? 'Artist Access' : 'Welcome Aboard',
            'title' => $isArtist
                ? 'Your artist account is almost ready.'
                : 'Your account is almost ready.',
            'intro' => $isArtist
                ? 'Verify your email to open the artist dashboard, publish updates, and start uploading music with confidence.'
                : 'Verify your email to save favorites, build playlists, and move through ' . $brand['siteName'] . ' without friction.',
            'messageLines' => [
                'This verification link is signed and time-limited for your security.',
                'Once you confirm your address, we will send a second welcome note with the quickest next step for your account.',
            ],
            'highlights' => [
                [
                    'label' => 'Email',
                    'value' => $notifiable->email,
                ],
                [
                    'label' => 'Access unlocks',
                    'value' => $isArtist ? 'Uploads, dashboard, profile tools' : 'Likes, playlists, listening history',
                ],
                [
                    'label' => 'Verification window',
                    'value' => config('auth.verification.expire', 60) . ' minutes',
                ],
            ],
            'ctaLabel' => 'Verify Email',
            'ctaUrl' => $verificationUrl,
            'secondaryCtaLabel' => $isArtist ? 'Artist Guide' : 'Browse Music',
            'secondaryCtaUrl' => $isArtist ? route('artist-guide') : route('browse'),
            'footerNote' => 'If you did not create this account, you can ignore this message.',
        ]);
    }
}
