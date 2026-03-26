<?php

namespace App\Support;

use App\Models\ArtistProfile;
use App\Models\Donation;
use App\Models\NotificationEvent;
use App\Models\Track;
use App\Models\User;
use App\Notifications\Artist\ArtistActivityNotification;

class ArtistActivityNotifier
{
    public function sendAccountUpdated(User $user, bool $emailChanged = false): void
    {
        $this->send(
            $user,
            new ArtistActivityNotification(
                subject: 'Your artist account was updated',
                eyebrow: 'Account Activity',
                title: 'Your account settings were just changed.',
                intro: 'We are sending this confirmation so you always know when details tied to your artist account have been updated.',
                messageLines: array_values(array_filter([
                    $emailChanged
                        ? 'Your email address changed during this update, and the new address is now active on your account.'
                        : 'Your account details were saved successfully.',
                ])),
                highlights: array_values(array_filter([
                    ['label' => 'Name', 'value' => $user->name],
                    ['label' => 'Email', 'value' => $user->email],
                    $emailChanged ? ['label' => 'Email status', 'value' => 'Updated'] : null,
                ])),
                ctaLabel: 'Review Account',
                ctaUrl: route('profile'),
                secondaryCtaLabel: 'Artist Dashboard',
                secondaryCtaUrl: $user->artistProfile ? route('artist.dashboard') : route('profile'),
                preheader: 'A recent account-settings change was saved on your artist account.',
            ),
        );
    }

    public function sendPasswordChanged(User $user, string $source = 'settings'): void
    {
        $changedViaReset = $source === 'reset';

        $this->send(
            $user,
            new ArtistActivityNotification(
                subject: $changedViaReset ? 'Your artist password was reset' : 'Your artist password was changed',
                eyebrow: 'Security Update',
                title: $changedViaReset
                    ? 'Your password reset is complete.'
                    : 'Your password was changed successfully.',
                intro: 'This is a security confirmation for your artist account.',
                messageLines: [
                    $changedViaReset
                        ? 'If you did not complete this reset, change your password again immediately and review your account activity.'
                        : 'If you did not make this change, reset your password right away and review your sign-in details.',
                ],
                highlights: [
                    ['label' => 'Account', 'value' => $user->email],
                    ['label' => 'Changed via', 'value' => $changedViaReset ? 'Password reset link' : 'Account settings'],
                ],
                ctaLabel: 'Open Account Settings',
                ctaUrl: route('profile'),
                secondaryCtaLabel: 'Reset Again',
                secondaryCtaUrl: route('password.request'),
                preheader: 'A password change was recorded on your artist account.',
            ),
        );
    }

    public function sendArtistProfileCreated(ArtistProfile $profile): void
    {
        $profile->loadMissing('user', 'genres');

        $this->send(
            $profile->user,
            new ArtistActivityNotification(
                subject: 'Your artist profile has been created',
                eyebrow: 'Artist Setup',
                title: 'Your public artist profile is now in place.',
                intro: 'You can keep refining your presence while the profile review process moves forward.',
                messageLines: [
                    'Once approved, listeners will be able to discover your page, follow your profile, and explore your releases.',
                ],
                highlights: array_values(array_filter([
                    ['label' => 'Stage name', 'value' => $profile->display_name],
                    ['label' => 'Status', 'value' => $profile->is_approved ? 'Approved' : 'Pending review'],
                    $profile->genres->isNotEmpty()
                        ? ['label' => 'Genres', 'value' => $profile->genres->pluck('name')->take(3)->implode(', ')]
                        : null,
                ])),
                ctaLabel: 'Open Artist Dashboard',
                ctaUrl: route('artist.dashboard'),
                secondaryCtaLabel: 'Edit Profile',
                secondaryCtaUrl: route('artist.edit-profile'),
                preheader: 'Your artist profile is live inside the dashboard and ready for updates.',
            ),
        );
    }

    public function sendArtistProfileUpdated(ArtistProfile $profile): void
    {
        $profile->loadMissing('user', 'genres');

        $this->send(
            $profile->user,
            new ArtistActivityNotification(
                subject: 'Your artist profile was updated',
                eyebrow: 'Artist Profile',
                title: 'Your public artist details were just refreshed.',
                intro: 'We are confirming this update so you always have a record of what changed on your artist presence.',
                messageLines: [
                    'Listeners will see the latest saved profile information wherever your artist page appears.',
                ],
                highlights: array_values(array_filter([
                    ['label' => 'Stage name', 'value' => $profile->display_name],
                    $profile->genres->isNotEmpty()
                        ? ['label' => 'Genres', 'value' => $profile->genres->pluck('name')->take(3)->implode(', ')]
                        : null,
                    ['label' => 'Profile status', 'value' => $profile->is_approved ? 'Approved' : 'Pending review'],
                ])),
                ctaLabel: 'View Public Page',
                ctaUrl: route('artist.page', $profile),
                secondaryCtaLabel: 'Edit Again',
                secondaryCtaUrl: route('artist.edit-profile'),
                preheader: 'Your artist profile changes have been saved.',
            ),
        );
    }

    public function sendTrackUploaded(Track $track): void
    {
        $track->loadMissing('artistProfile.user', 'genre', 'album');

        $this->send(
            $track->artistProfile?->user,
            new ArtistActivityNotification(
                subject: 'Track uploaded: ' . $track->title,
                eyebrow: 'Catalog Update',
                title: 'Your track has been added to your catalog.',
                intro: 'Your latest upload is now saved inside your artist workspace.',
                messageLines: [
                    $track->is_published
                        ? 'Because this release is published, it can start appearing across discovery surfaces right away.'
                        : 'This release is currently saved as a draft until you choose to publish it.',
                ],
                highlights: array_values(array_filter([
                    ['label' => 'Track', 'value' => $track->title],
                    ['label' => 'Visibility', 'value' => $track->is_published ? 'Published' : 'Draft'],
                    $track->genre ? ['label' => 'Genre', 'value' => $track->genre->name] : null,
                    $track->album ? ['label' => 'Release', 'value' => $track->album->title] : null,
                ])),
                ctaLabel: 'Manage Track',
                ctaUrl: route('artist.edit-track', $track),
                secondaryCtaLabel: 'View My Music',
                secondaryCtaUrl: route('artist.tracks'),
                preheader: 'A new track has been uploaded to your artist account.',
            ),
        );
    }

    public function sendTrackUpdated(Track $track): void
    {
        $track->loadMissing('artistProfile.user', 'genre');

        $this->send(
            $track->artistProfile?->user,
            new ArtistActivityNotification(
                subject: 'Track updated: ' . $track->title,
                eyebrow: 'Catalog Update',
                title: 'Your track changes have been saved.',
                intro: 'The latest edits to this release are now reflected across your artist tools.',
                messageLines: [
                    'This includes updates like title, cover art, visibility, pricing, or audio replacement.',
                ],
                highlights: array_values(array_filter([
                    ['label' => 'Track', 'value' => $track->title],
                    ['label' => 'Visibility', 'value' => $track->is_published ? 'Published' : 'Draft'],
                    $track->genre ? ['label' => 'Genre', 'value' => $track->genre->name] : null,
                ])),
                ctaLabel: 'Review Track',
                ctaUrl: route('artist.edit-track', $track),
                secondaryCtaLabel: 'Artist Dashboard',
                secondaryCtaUrl: route('artist.dashboard'),
                preheader: 'An existing track on your artist account was updated.',
            ),
        );
    }

    public function sendDonationReceived(Donation $donation): void
    {
        $donation->loadMissing('artist.user', 'track');

        $artistUser = $donation->artist?->user;

        $this->send(
            $artistUser,
            new ArtistActivityNotification(
                subject: 'New support received for your music',
                eyebrow: 'Support Update',
                title: 'A listener just supported your catalog.',
                intro: 'A completed donation was recorded on your artist account.',
                messageLines: array_values(array_filter([
                    $donation->track
                        ? 'The support was attached to "' . $donation->track->title . '".'
                        : null,
                    filled($donation->message)
                        ? 'Listener note: "' . trim($donation->message) . '"'
                        : null,
                ])),
                highlights: array_values(array_filter([
                    ['label' => 'Amount', 'value' => '$' . number_format((float) $donation->amount, 2)],
                    ['label' => 'Type', 'value' => ucfirst($donation->type)],
                    $donation->track ? ['label' => 'Track', 'value' => $donation->track->title] : null,
                ])),
                ctaLabel: 'Open Artist Dashboard',
                ctaUrl: route('artist.dashboard'),
                secondaryCtaLabel: 'View My Music',
                secondaryCtaUrl: route('artist.tracks'),
                preheader: 'A completed donation has been recorded for your artist account.',
            ),
        );
    }

    public function sendTrackPlayMilestoneIfReached(Track $track): void
    {
        $this->sendTrackMetricMilestoneIfReached($track, 'plays', (int) $track->play_count, [100, 500, 1000, 5000, 10000]);
    }

    public function sendTrackDownloadMilestoneIfReached(Track $track): void
    {
        $this->sendTrackMetricMilestoneIfReached($track, 'downloads', (int) $track->downloads_count, [10, 25, 50, 100, 500, 1000]);
    }

    public function sendTrackLikeMilestoneIfReached(Track $track): void
    {
        $likes = (int) $track->likedByUsers()->count();

        $this->sendTrackMetricMilestoneIfReached($track, 'likes', $likes, [10, 25, 50, 100, 500]);
    }

    public function sendFollowerMilestoneIfReached(ArtistProfile $profile): void
    {
        $profile->loadMissing('user');

        $threshold = $this->highestThreshold((int) $profile->followers_count, [10, 25, 50, 100, 500, 1000]);

        if (! $threshold || ! $profile->user) {
            return;
        }

        $this->sendOnce(
            $profile->user,
            'artist_follower_milestone',
            'artist:' . $profile->id . ':followers:' . $threshold,
            new ArtistActivityNotification(
                subject: 'Milestone reached: ' . $threshold . ' followers',
                eyebrow: 'Audience Milestone',
                title: 'You just crossed ' . number_format($threshold) . ' followers.',
                intro: 'Your audience keeps growing and this is a strong signal that listeners are coming back for more.',
                messageLines: [
                    'Keep your catalog moving and your public profile current to turn this momentum into the next milestone.',
                ],
                highlights: [
                    ['label' => 'Artist', 'value' => $profile->display_name],
                    ['label' => 'Followers', 'value' => number_format((int) $profile->followers_count)],
                ],
                ctaLabel: 'View Public Page',
                ctaUrl: route('artist.page', $profile),
                secondaryCtaLabel: 'Artist Dashboard',
                secondaryCtaUrl: route('artist.dashboard'),
                preheader: 'Your artist profile just crossed a new follower milestone.',
            ),
        );
    }

    public function sendDonationMilestoneIfReached(ArtistProfile $profile): void
    {
        $profile->loadMissing('user');

        $amount = (float) $profile->total_donations;
        $threshold = $this->highestThreshold($amount, [10, 50, 100, 250, 500, 1000]);

        if (! $threshold || ! $profile->user) {
            return;
        }

        $this->sendOnce(
            $profile->user,
            'artist_donation_milestone',
            'artist:' . $profile->id . ':donations:' . $threshold,
            new ArtistActivityNotification(
                subject: 'Milestone reached: $' . number_format($threshold, 0) . ' in support',
                eyebrow: 'Support Milestone',
                title: 'Your supporters have pushed you past $' . number_format($threshold, 0) . '.',
                intro: 'That is a meaningful signal that your music is connecting strongly enough for listeners to contribute directly.',
                messageLines: [
                    'Keep an eye on the dashboard for which tracks are converting that support most consistently.',
                ],
                highlights: [
                    ['label' => 'Artist', 'value' => $profile->display_name],
                    ['label' => 'Total support', 'value' => '$' . number_format($amount, 2)],
                ],
                ctaLabel: 'Open Artist Dashboard',
                ctaUrl: route('artist.dashboard'),
                secondaryCtaLabel: 'My Music',
                secondaryCtaUrl: route('artist.tracks'),
                preheader: 'Your artist account just crossed a new direct-support milestone.',
            ),
        );
    }

    protected function sendTrackMetricMilestoneIfReached(Track $track, string $metric, int $value, array $thresholds): void
    {
        $track->loadMissing('artistProfile.user');

        $threshold = $this->highestThreshold($value, $thresholds);
        $artistUser = $track->artistProfile?->user;

        if (! $threshold || ! $artistUser) {
            return;
        }

        $label = ucfirst($metric);

        $this->sendOnce(
            $artistUser,
            'track_' . $metric . '_milestone',
            'track:' . $track->id . ':' . $metric . ':' . $threshold,
            new ArtistActivityNotification(
                subject: 'Milestone reached: ' . number_format($threshold) . ' ' . $metric . ' on ' . $track->title,
                eyebrow: 'Track Milestone',
                title: 'Your track just crossed ' . number_format($threshold) . ' ' . $metric . '.',
                intro: '"' . $track->title . '" is building momentum inside your catalog.',
                messageLines: [
                    'Keep promoting the release and updating the rest of your catalog to turn this attention into the next spike.',
                ],
                highlights: [
                    ['label' => 'Track', 'value' => $track->title],
                    ['label' => $label, 'value' => number_format($value)],
                ],
                ctaLabel: 'Manage Track',
                ctaUrl: route('artist.edit-track', $track),
                secondaryCtaLabel: 'Artist Dashboard',
                secondaryCtaUrl: route('artist.dashboard'),
                preheader: 'A track in your catalog just crossed a new ' . $metric . ' milestone.',
            ),
        );
    }

    protected function send(?User $user, ArtistActivityNotification $notification): void
    {
        if (! $user || ! $user->isArtist() || blank($user->email)) {
            return;
        }

        $user->notify($notification);
    }

    protected function sendOnce(?User $user, string $type, string $eventKey, ArtistActivityNotification $notification): void
    {
        if (! $user || ! $user->isArtist() || blank($user->email)) {
            return;
        }

        $event = NotificationEvent::query()->firstOrCreate(
            ['event_key' => $eventKey],
            [
                'user_id' => $user->id,
                'type' => $type,
                'sent_at' => now(),
            ],
        );

        if (! $event->wasRecentlyCreated) {
            return;
        }

        $user->notify($notification);
    }

    protected function highestThreshold(int|float $value, array $thresholds): int|float|null
    {
        $eligible = collect($thresholds)
            ->filter(fn (int|float $threshold): bool => $value >= $threshold)
            ->values();

        return $eligible->isEmpty() ? null : $eligible->last();
    }
}
