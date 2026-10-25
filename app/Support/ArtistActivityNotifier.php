<?php

namespace App\Support;

use App\Models\Album;
use App\Models\ArtistProfile;
use App\Models\Donation;
use App\Models\EmailTemplate;
use App\Models\NotificationEvent;
use App\Models\Track;
use App\Models\User;
use App\Notifications\Artist\ArtistActivityNotification;
use Throwable;

class ArtistActivityNotifier
{
    public function sendAccountUpdated(User $user, bool $emailChanged = false): void
    {
        $this->send($user, $this->activity($emailChanged ? 'account_email_changed' : 'account_updated', $user, [
            'email' => $user->email,
        ],
            highlights: array_values(array_filter([
                ['label' => 'Name', 'value' => $user->name],
                ['label' => 'Email', 'value' => $user->email],
                $emailChanged ? ['label' => 'Email status', 'value' => 'Updated'] : null,
            ])),
            ctaUrl: route('profile'),
            secondaryCtaLabel: 'Artist Dashboard',
            secondaryCtaUrl: $user->artistProfile ? route('artist.dashboard') : route('profile'),
        ));
    }

    public function sendPasswordChanged(User $user, string $source = 'settings'): void
    {
        $changedViaReset = $source === 'reset';

        $this->send($user, $this->activity($changedViaReset ? 'password_reset_completed' : 'password_changed', $user, [
            'email' => $user->email,
        ],
            highlights: [
                ['label' => 'Account', 'value' => $user->email],
                ['label' => 'Changed via', 'value' => $changedViaReset ? 'Password reset link' : 'Account settings'],
            ],
            ctaUrl: route('profile'),
            secondaryCtaLabel: 'Reset Again',
            secondaryCtaUrl: route('password.request'),
        ));
    }

    public function sendArtistProfileCreated(ArtistProfile $profile): void
    {
        $profile->loadMissing('user', 'genres');

        $this->send($profile->user, $this->activity('artist_profile_created', $profile->user, [
            'stage_name' => $profile->display_name,
        ],
            highlights: array_values(array_filter([
                ['label' => 'Stage name', 'value' => $profile->display_name],
                ['label' => 'Status', 'value' => $this->profileStatus($profile)],
                $profile->genres->isNotEmpty()
                    ? ['label' => 'Genres', 'value' => $profile->genres->pluck('name')->take(3)->implode(', ')]
                    : null,
            ])),
            ctaUrl: route('artist.dashboard'),
            secondaryCtaLabel: 'Edit Profile',
            secondaryCtaUrl: route('artist.edit-profile'),
        ));
    }

    public function sendArtistProfileUpdated(ArtistProfile $profile): void
    {
        $profile->loadMissing('user', 'genres');

        $this->send($profile->user, $this->activity('artist_profile_updated', $profile->user, [
            'stage_name' => $profile->display_name,
        ],
            highlights: array_values(array_filter([
                ['label' => 'Stage name', 'value' => $profile->display_name],
                $profile->genres->isNotEmpty()
                    ? ['label' => 'Genres', 'value' => $profile->genres->pluck('name')->take(3)->implode(', ')]
                    : null,
                ['label' => 'Profile status', 'value' => $this->profileStatus($profile)],
            ])),
            ctaUrl: route('artist.page', $profile),
            secondaryCtaLabel: 'Edit Again',
            secondaryCtaUrl: route('artist.edit-profile'),
        ));
    }

    public function sendTrackUploaded(Track $track): void
    {
        $track->loadMissing('artistProfile.user', 'genre', 'album');

        $this->send($track->artistProfile?->user, $this->activity('track_uploaded', $track->artistProfile?->user, [
            'track_title' => $track->title,
            'visibility_message' => $this->visibilityLine($track),
        ],
            highlights: array_values(array_filter([
                ['label' => 'Track', 'value' => $track->title],
                ['label' => 'Visibility', 'value' => $this->visibilityLabel($track)],
                $track->genre ? ['label' => 'Genre', 'value' => $track->genre->name] : null,
                $track->album ? ['label' => 'Release', 'value' => $track->album->title] : null,
            ])),
            ctaUrl: route('artist.edit-track', $track),
            secondaryCtaLabel: 'View My Music',
            secondaryCtaUrl: route('artist.tracks'),
        ));
    }

    public function sendTrackConversionCompleted(Track $track): void
    {
        $track->loadMissing('artistProfile.user', 'genre', 'album');

        $this->send($track->artistProfile?->user, $this->activity('track_conversion_completed', $track->artistProfile?->user, [
            'track_title' => $track->title,
            'visibility_message' => $this->visibilityLine($track),
        ],
            highlights: array_values(array_filter([
                ['label' => 'Track', 'value' => $track->title],
                ['label' => 'Visibility', 'value' => $this->visibilityLabel($track)],
                $track->genre ? ['label' => 'Genre', 'value' => $track->genre->name] : null,
            ])),
            ctaUrl: route('artist.edit-track', $track),
            secondaryCtaLabel: 'View My Music',
            secondaryCtaUrl: route('artist.tracks'),
        ));
    }

    public function sendTrackConversionFailed(User $user, string $title, string $stage, ?string $limit = null): void
    {
        $reason = $stage === 'audio'
            ? 'We could not compress this audio file below the ' . ($limit ?? 'current') . ' limit, even at our lowest quality setting.'
            : 'Something went wrong while processing this upload.';

        $this->send($user, $this->activity('track_conversion_failed', $user, [
            'track_title' => $title,
            'failure_reason' => $reason,
            'limit' => $limit,
        ],
            highlights: array_values(array_filter([
                ['label' => 'Track', 'value' => $title],
                $limit ? ['label' => 'Limit', 'value' => $limit] : null,
            ])),
            ctaUrl: route('artist.upload-track'),
            secondaryCtaLabel: 'View My Music',
            secondaryCtaUrl: route('artist.tracks'),
        ));
    }

    public function sendTrackUpdated(Track $track): void
    {
        $track->loadMissing('artistProfile.user', 'genre');

        $this->send($track->artistProfile?->user, $this->activity('track_updated', $track->artistProfile?->user, [
            'track_title' => $track->title,
        ],
            highlights: array_values(array_filter([
                ['label' => 'Track', 'value' => $track->title],
                ['label' => 'Visibility', 'value' => $this->visibilityLabel($track)],
                $track->genre ? ['label' => 'Genre', 'value' => $track->genre->name] : null,
            ])),
            ctaUrl: route('artist.edit-track', $track),
            secondaryCtaLabel: 'Artist Dashboard',
            secondaryCtaUrl: route('artist.dashboard'),
        ));
    }

    public function sendDonationReceived(Donation $donation): void
    {
        $donation->loadMissing('artist.user', 'track');
        $artistUser = $donation->artist?->user;
        $amount = '$' . number_format((float) $donation->amount, 2);

        $this->send($artistUser, $this->activity('donation_received', $artistUser, [
            'amount' => $amount,
            'track_title' => $donation->track?->title,
            'listener_note' => filled($donation->message) ? trim($donation->message) : null,
        ],
            highlights: array_values(array_filter([
                ['label' => 'Amount', 'value' => $amount],
                ['label' => 'Type', 'value' => ucfirst($donation->type)],
                $donation->track ? ['label' => 'Track', 'value' => $donation->track->title] : null,
            ])),
            ctaUrl: route('artist.dashboard'),
            secondaryCtaLabel: 'View My Music',
            secondaryCtaUrl: route('artist.tracks'),
        ));
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
            $this->activity('follower_milestone', $profile->user, [
                'stage_name' => $profile->display_name,
                'threshold' => number_format($threshold),
            ],
                highlights: [
                    ['label' => 'Artist', 'value' => $profile->display_name],
                    ['label' => 'Followers', 'value' => number_format((int) $profile->followers_count)],
                ],
                ctaUrl: route('artist.page', $profile),
                secondaryCtaLabel: 'Artist Dashboard',
                secondaryCtaUrl: route('artist.dashboard'),
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
            $this->activity('donation_milestone', $profile->user, [
                'stage_name' => $profile->display_name,
                'threshold' => '$' . number_format($threshold, 0),
            ],
                highlights: [
                    ['label' => 'Artist', 'value' => $profile->display_name],
                    ['label' => 'Total support', 'value' => '$' . number_format($amount, 2)],
                ],
                ctaUrl: route('artist.dashboard'),
                secondaryCtaLabel: 'My Music',
                secondaryCtaUrl: route('artist.tracks'),
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

        $this->sendOnce(
            $artistUser,
            'track_' . $metric . '_milestone',
            'track:' . $track->id . ':' . $metric . ':' . $threshold,
            $this->activity('track_milestone', $artistUser, [
                'track_title' => $track->title,
                'threshold' => number_format($threshold),
                'metric' => $metric,
            ],
                highlights: [
                    ['label' => 'Track', 'value' => $track->title],
                    ['label' => ucfirst($metric), 'value' => number_format($value)],
                ],
                ctaUrl: route('artist.edit-track', $track),
                secondaryCtaLabel: 'Artist Dashboard',
                secondaryCtaUrl: route('artist.dashboard'),
            ),
        );
    }

    /**
     * A scheduled track or album just went live. Tracks that went live with
     * their album are covered by the album's email rather than one each.
     */
    public function sendScheduledReleaseLive(Track|Album $release, int $tracksReleasedWithAlbum = 0): void
    {
        $isAlbum = $release instanceof Album;
        $release->loadMissing($isAlbum ? 'artist.user' : 'artistProfile.user');
        $user = $isAlbum ? $release->artist?->user : $release->artistProfile?->user;
        $kind = $isAlbum ? ucfirst($release->type ?: 'album') : 'Track';

        $this->sendOnce(
            $user,
            'artist_scheduled_release_live',
            ($isAlbum ? 'album:' : 'track:') . $release->id . ':released:' . $release->updated_at?->timestamp,
            $this->activity('scheduled_release_live', $user, [
                'release_title' => $release->title,
                'release_kind' => strtolower($kind),
                'release_kind_label' => $kind,
                'tracks_with_album' => $tracksReleasedWithAlbum > 0
                    ? $tracksReleasedWithAlbum . ' ' . ($tracksReleasedWithAlbum === 1 ? 'track' : 'tracks')
                    : null,
            ],
                highlights: [
                    ['label' => $kind, 'value' => $release->title],
                    ['label' => 'Visibility', 'value' => 'Published'],
                ],
                ctaUrl: match (true) {
                    ! $isAlbum => route('track.show', $release),
                    in_array($release->type, Album::PUBLIC_TYPES, true) => route('album.show', $release),
                    default => route('artist.edit-album', $release),
                },
                secondaryCtaLabel: 'View My Music',
                secondaryCtaUrl: route($isAlbum ? 'artist.albums' : 'artist.tracks'),
            ),
        );
    }

    /**
     * Build a notification whose wording comes from the admin-editable
     * template; the caller supplies the data-driven parts (highlights, links).
     */
    protected function activity(string $template, ?User $recipient, array $vars, mixed ...$extra): ArtistActivityNotification
    {
        $copy = EmailTemplate::copy($template, $vars + ['recipient_name' => $recipient?->name]);

        return new ArtistActivityNotification(...array_merge([
            'subject' => $copy->subject,
            'eyebrow' => (string) $copy->eyebrow,
            'title' => $copy->title,
            'messageLines' => $copy->lines,
            'ctaLabel' => $copy->ctaLabel,
            'preheader' => $copy->preheader,
            'intro' => $copy->intro,
            'footerNote' => $copy->footerNote,
        ], $extra));
    }

    protected function profileStatus(ArtistProfile $profile): string
    {
        return $profile->is_approved ? 'Approved' : ($profile->is_verified ? 'Verified, awaiting activation' : 'Email verification required');
    }

    protected function visibilityLabel(Track $track): string
    {
        return match ($track->releaseState()) {
            'live' => 'Published',
            'scheduled' => 'Scheduled for ' . static::formatReleaseTime($track->scheduled_release_at),
            default => 'Draft',
        };
    }

    protected function visibilityLine(Track $track): string
    {
        return match ($track->releaseState()) {
            'live' => 'Because this release is published, it can start appearing across discovery surfaces right away.',
            'scheduled' => 'This release is scheduled. It stays hidden until ' . static::formatReleaseTime($track->scheduled_release_at) . ', then goes live automatically.',
            default => 'This release is currently saved as a draft until you choose to publish it.',
        };
    }

    public static function formatReleaseTime(\DateTimeInterface $at): string
    {
        return \Illuminate\Support\Carbon::instance($at)->utc()->format('j M Y, H:i') . ' UTC';
    }

    protected function send(?User $user, ArtistActivityNotification $notification): void
    {
        if (! $user || ! $user->isArtist() || blank($user->email)) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Throwable $exception) {
            report($exception);
        }
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

        try {
            $user->notify($notification);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function highestThreshold(int|float $value, array $thresholds): int|float|null
    {
        $eligible = collect($thresholds)
            ->filter(fn (int|float $threshold): bool => $value >= $threshold)
            ->values();

        return $eligible->isEmpty() ? null : $eligible->last();
    }
}
