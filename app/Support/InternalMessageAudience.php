<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class InternalMessageAudience
{
    public static function audienceOptions(): array
    {
        return [
            'all' => 'All users',
            'artists' => 'Artists only',
            'listeners' => 'Listeners only',
        ];
    }

    public static function emailStatusOptions(): array
    {
        return [
            'any' => 'Any email status',
            'verified' => 'Verified email only',
            'unverified' => 'Unverified email only',
        ];
    }

    public static function activityWindowOptions(): array
    {
        return [
            'any' => 'Any activity level',
            'online_now' => 'Online in last 5 minutes',
            'active_7d' => 'Active in last 7 days',
            'active_30d' => 'Active in last 30 days',
            'active_90d' => 'Active in last 90 days',
            'inactive_30d' => 'No activity in 30+ days',
        ];
    }

    public static function memberSegmentOptions(): array
    {
        return [
            'any' => 'Any membership age',
            'new_7d' => 'Joined in last 7 days',
            'new_30d' => 'Joined in last 30 days',
            'established_90d' => 'Joined 90+ days ago',
        ];
    }

    public static function artistProfileStateOptions(): array
    {
        return [
            'any' => 'Any artist profile state',
            'has_profile' => 'Has artist profile',
            'missing_profile' => 'No artist profile',
            'approved' => 'Approved artist profile',
            'pending' => 'Pending artist profile',
            'featured' => 'Featured artist profile',
        ];
    }

    public static function engagementSegmentOptions(): array
    {
        return [
            'any' => 'Any engagement',
            'has_uploads' => 'Has uploaded tracks',
            'no_uploads' => 'No uploaded tracks',
            'has_playlists' => 'Has playlists',
            'has_likes' => 'Has liked tracks',
            'has_donations' => 'Has made donations',
        ];
    }

    public static function normalize(?array $filters): array
    {
        $filters = is_array($filters) ? $filters : [];

        $allowed = [
            'email_status' => array_keys(static::emailStatusOptions()),
            'activity_window' => array_keys(static::activityWindowOptions()),
            'member_segment' => array_keys(static::memberSegmentOptions()),
            'artist_profile_state' => array_keys(static::artistProfileStateOptions()),
            'engagement_segment' => array_keys(static::engagementSegmentOptions()),
        ];

        $normalized = [];

        foreach ($allowed as $key => $validOptions) {
            $value = $filters[$key] ?? 'any';

            if (! is_string($value) || ! in_array($value, $validOptions, true) || $value === 'any') {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    public static function apply(Builder $query, string $audience = 'all', ?array $filters = null): Builder
    {
        static::applyAudience($query, $audience);

        $filters = static::normalize($filters);

        foreach ($filters as $key => $value) {
            match ($key) {
                'email_status' => static::applyEmailStatus($query, $value),
                'activity_window' => static::applyActivityWindow($query, $value),
                'member_segment' => static::applyMemberSegment($query, $value),
                'artist_profile_state' => static::applyArtistProfileState($query, $value),
                'engagement_segment' => static::applyEngagementSegment($query, $value),
                default => null,
            };
        }

        return $query;
    }

    public static function matches(User $user, string $audience = 'all', ?array $filters = null): bool
    {
        if (! static::matchesAudience($user, $audience)) {
            return false;
        }

        $filters = static::normalize($filters);

        foreach ($filters as $key => $value) {
            $matches = match ($key) {
                'email_status' => static::matchesEmailStatus($user, $value),
                'activity_window' => static::matchesActivityWindow($user, $value),
                'member_segment' => static::matchesMemberSegment($user, $value),
                'artist_profile_state' => static::matchesArtistProfileState($user, $value),
                'engagement_segment' => static::matchesEngagementSegment($user, $value),
                default => true,
            };

            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    public static function describe(string $audience = 'all', ?array $filters = null): string
    {
        $filters = static::normalize($filters);
        $segments = [static::audienceOptions()[$audience] ?? static::audienceOptions()['all']];

        $labels = [
            'email_status' => static::emailStatusOptions(),
            'activity_window' => static::activityWindowOptions(),
            'member_segment' => static::memberSegmentOptions(),
            'artist_profile_state' => static::artistProfileStateOptions(),
            'engagement_segment' => static::engagementSegmentOptions(),
        ];

        foreach ($filters as $key => $value) {
            $segments[] = $labels[$key][$value] ?? $value;
        }

        return implode(' • ', $segments);
    }

    public static function personalizationTokens(): array
    {
        return [
            '{name}' => 'Full recipient name',
            '{first_name}' => 'Recipient first name',
            '{username}' => 'Recipient display name',
            '{email}' => 'Recipient email address',
            '{role_label}' => 'Listener, Artist, or Admin',
            '{joined_date}' => 'Account creation date',
            '{last_seen_at}' => 'Most recent activity timestamp',
            '{playlist_count}' => 'Number of playlists created',
            '{liked_tracks_count}' => 'Number of liked tracks',
            '{followed_artists_count}' => 'Number of followed artists',
            '{donation_count}' => 'Number of donations made',
            '{artist_stage_name}' => 'Artist stage name or fallback name',
            '{artist_tracks_count}' => 'Artist track count',
            '{artist_followers_count}' => 'Artist follower count',
            '{artist_total_plays}' => 'Artist total plays',
            '{artist_total_donations}' => 'Artist lifetime donations',
            '{artist_profile_status}' => 'Approved, Pending, or No artist profile',
            '{artist_profile_url}' => 'Public artist profile URL',
            '{dashboard_url}' => 'Best-fit dashboard URL',
            '{inbox_url}' => 'Inbox URL',
            '{app_name}' => 'Application name',
        ];
    }

    public static function personalizationContext(User $user): array
    {
        $profile = $user->relationLoaded('artistProfile')
            ? $user->artistProfile
            : $user->artistProfile()->first();

        $lastSeenAt = $user->last_seen_at?->format('d M Y H:i');
        $artistFollowers = $profile
            ? max((int) ($profile->followers_count ?? 0), (int) $profile->followers()->count())
            : 0;
        $artistTotalPlays = $profile
            ? max((int) ($profile->total_plays ?? 0), (int) $profile->tracks()->sum('play_count'))
            : 0;
        $artistTotalDonationsValue = $profile
            ? max((float) ($profile->total_donations ?? 0), (float) $profile->donations()->sum('amount'))
            : 0.0;
        $artistTotalDonations = number_format($artistTotalDonationsValue, 2, '.', '');

        return [
            '{name}' => $user->name,
            '{first_name}' => static::firstName($user->name),
            '{username}' => $user->name,
            '{email}' => $user->email ?? '',
            '{role_label}' => static::roleLabel($user),
            '{joined_date}' => optional($user->created_at)->format('d M Y') ?? '',
            '{last_seen_at}' => $lastSeenAt ?? 'Not recorded yet',
            '{playlist_count}' => (string) $user->playlists()->count(),
            '{liked_tracks_count}' => (string) $user->likes()->count(),
            '{followed_artists_count}' => (string) $user->followedArtists()->count(),
            '{donation_count}' => (string) $user->donations()->count(),
            '{artist_stage_name}' => $profile?->display_name ?? $user->name,
            '{artist_tracks_count}' => (string) ($profile?->tracks()->count() ?? 0),
            '{artist_followers_count}' => (string) $artistFollowers,
            '{artist_total_plays}' => (string) $artistTotalPlays,
            '{artist_total_donations}' => $artistTotalDonations,
            '{artist_profile_status}' => static::artistProfileStatus($profile),
            '{artist_profile_url}' => $profile ? route('artist.page', $profile) : '',
            '{dashboard_url}' => $user->isArtist() ? route('artist.dashboard') : route('listener.dashboard'),
            '{inbox_url}' => route('inbox'),
            '{app_name}' => config('app.name', 'GrinMuzik'),
        ];
    }

    protected static function applyAudience(Builder $query, string $audience): void
    {
        match ($audience) {
            'artists' => $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'artist')),
            'listeners' => $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'listener')),
            default => null,
        };
    }

    protected static function applyEmailStatus(Builder $query, string $value): void
    {
        match ($value) {
            'verified' => $query->whereNotNull('email_verified_at'),
            'unverified' => $query->whereNull('email_verified_at'),
            default => null,
        };
    }

    protected static function applyActivityWindow(Builder $query, string $value): void
    {
        match ($value) {
            'online_now' => $query->whereNotNull('last_seen_at')->where('last_seen_at', '>=', User::onlineThreshold()),
            'active_7d' => $query->whereNotNull('last_seen_at')->where('last_seen_at', '>=', now()->subDays(7)),
            'active_30d' => $query->whereNotNull('last_seen_at')->where('last_seen_at', '>=', now()->subDays(30)),
            'active_90d' => $query->whereNotNull('last_seen_at')->where('last_seen_at', '>=', now()->subDays(90)),
            'inactive_30d' => $query->where(function (Builder $nested): void {
                $nested->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays(30));
            }),
            default => null,
        };
    }

    protected static function applyMemberSegment(Builder $query, string $value): void
    {
        match ($value) {
            'new_7d' => $query->where('created_at', '>=', now()->subDays(7)),
            'new_30d' => $query->where('created_at', '>=', now()->subDays(30)),
            'established_90d' => $query->where('created_at', '<=', now()->subDays(90)),
            default => null,
        };
    }

    protected static function applyArtistProfileState(Builder $query, string $value): void
    {
        match ($value) {
            'has_profile' => $query->whereHas('artistProfile'),
            'missing_profile' => $query->whereDoesntHave('artistProfile'),
            'approved' => $query->whereHas('artistProfile', fn (Builder $profileQuery) => $profileQuery->where('is_approved', true)->where('is_active', true)),
            'pending' => $query->whereHas('artistProfile', fn (Builder $profileQuery) => $profileQuery->where('is_active', true)->where('is_approved', false)),
            'featured' => $query->whereHas('artistProfile', fn (Builder $profileQuery) => $profileQuery->where('is_featured', true)),
            default => null,
        };
    }

    protected static function applyEngagementSegment(Builder $query, string $value): void
    {
        match ($value) {
            'has_uploads' => $query->whereHas('artistProfile.tracks'),
            'no_uploads' => $query->whereHas('artistProfile', fn (Builder $profileQuery) => $profileQuery->doesntHave('tracks')),
            'has_playlists' => $query->has('playlists'),
            'has_likes' => $query->has('likes'),
            'has_donations' => $query->has('donations'),
            default => null,
        };
    }

    protected static function matchesAudience(User $user, string $audience): bool
    {
        return match ($audience) {
            'artists' => $user->isArtist(),
            'listeners' => $user->isListener(),
            default => true,
        };
    }

    protected static function matchesEmailStatus(User $user, string $value): bool
    {
        return match ($value) {
            'verified' => $user->hasVerifiedEmail(),
            'unverified' => ! $user->hasVerifiedEmail(),
            default => true,
        };
    }

    protected static function matchesActivityWindow(User $user, string $value): bool
    {
        $lastSeenAt = $user->last_seen_at;

        return match ($value) {
            'online_now' => $lastSeenAt?->gte(User::onlineThreshold()) ?? false,
            'active_7d' => $lastSeenAt?->gte(now()->subDays(7)) ?? false,
            'active_30d' => $lastSeenAt?->gte(now()->subDays(30)) ?? false,
            'active_90d' => $lastSeenAt?->gte(now()->subDays(90)) ?? false,
            'inactive_30d' => $lastSeenAt?->lt(now()->subDays(30)) ?? true,
            default => true,
        };
    }

    protected static function matchesMemberSegment(User $user, string $value): bool
    {
        return match ($value) {
            'new_7d' => $user->created_at?->gte(now()->subDays(7)) ?? false,
            'new_30d' => $user->created_at?->gte(now()->subDays(30)) ?? false,
            'established_90d' => $user->created_at?->lte(now()->subDays(90)) ?? false,
            default => true,
        };
    }

    protected static function matchesArtistProfileState(User $user, string $value): bool
    {
        $profile = $user->relationLoaded('artistProfile')
            ? $user->artistProfile
            : $user->artistProfile()->first();

        return match ($value) {
            'has_profile' => $profile !== null,
            'missing_profile' => $profile === null,
            'approved' => (bool) ($profile?->is_approved && $profile?->is_active),
            'pending' => $profile !== null && ! $profile->is_approved && $profile->is_active,
            'featured' => (bool) ($profile?->is_featured),
            default => true,
        };
    }

    protected static function matchesEngagementSegment(User $user, string $value): bool
    {
        $profile = $user->relationLoaded('artistProfile')
            ? $user->artistProfile
            : $user->artistProfile()->first();

        return match ($value) {
            'has_uploads' => ($profile?->tracks()->count() ?? 0) > 0,
            'no_uploads' => $profile !== null && $profile->tracks()->count() === 0,
            'has_playlists' => $user->playlists()->count() > 0,
            'has_likes' => $user->likes()->count() > 0,
            'has_donations' => $user->donations()->count() > 0,
            default => true,
        };
    }

    protected static function firstName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        return explode(' ', $name)[0];
    }

    protected static function roleLabel(User $user): string
    {
        if ($user->isAdmin()) {
            return 'Admin';
        }

        if ($user->isArtist()) {
            return 'Artist';
        }

        if ($user->isListener()) {
            return 'Listener';
        }

        return 'Member';
    }

    protected static function artistProfileStatus(mixed $profile): string
    {
        if (! $profile) {
            return 'No artist profile';
        }

        if ($profile->is_featured) {
            return 'Featured artist';
        }

        if ($profile->is_approved) {
            return 'Approved artist';
        }

        if ($profile->is_active) {
            return 'Pending approval';
        }

        return 'Artist profile inactive';
    }
}
