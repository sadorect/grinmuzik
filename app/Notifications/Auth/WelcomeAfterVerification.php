<?php

namespace App\Notifications\Auth;

use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeAfterVerification extends Notification
{
    use BuildsBrandedMail;
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = $this->brandContext();
        $isArtist = method_exists($notifiable, 'isArtist') && $notifiable->isArtist();

        return $this->brandedMail([
            'subject' => 'You are verified - welcome to ' . $brand['siteName'],
            'preheader' => 'Your account is now confirmed and ready to use.',
            'recipientName' => $notifiable->name ?? null,
            'eyebrow' => 'Verification Complete',
            'title' => $isArtist
                ? 'You are verified. Time to release something great.'
                : 'You are verified. Time to dive in.',
            'intro' => $isArtist
                ? 'Your artist access is live. You can now manage your profile, open the dashboard, and start uploading tracks.'
                : 'Your account is fully active. You can now save tracks, build playlists, and keep your listening history in sync.',
            'messageLines' => [
                $isArtist
                    ? 'If your profile is still awaiting approval, you can keep preparing your catalog while the review is in progress.'
                    : 'Start with the latest releases, weekly standouts, or any artist you want to follow more closely.',
            ],
            'highlights' => [
                [
                    'label' => 'Verified email',
                    'value' => $notifiable->email,
                ],
                [
                    'label' => 'Best next step',
                    'value' => $isArtist ? 'Upload your next track' : 'Explore the home feed',
                ],
            ],
            'ctaLabel' => $isArtist ? 'Upload a Track' : 'Open Home',
            'ctaUrl' => $isArtist ? route('artist.upload-track') : route('home'),
            'secondaryCtaLabel' => $isArtist ? 'Artist Dashboard' : 'Browse Music',
            'secondaryCtaUrl' => $isArtist ? route('artist.dashboard') : route('browse'),
            'footerNote' => 'Thanks for joining ' . $brand['siteName'] . '.',
        ]);
    }
}
