<?php

use App\Models\Album;
use App\Models\ArtistProfile;
use App\Models\Follow;
use App\Models\Track;
use App\Support\ArtistActivityNotifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.glass-app')] class extends Component {
    public ArtistProfile $profile;
    public bool $isFollowing = false;

    public function mount(ArtistProfile $profile): void
    {
        abort_if(
            ! ArtistProfile::query()->publiclyDiscoverable()->whereKey($profile->getKey())->exists(),
            404,
        );

        $this->profile = $profile->load([
            'user',
            'genres',
            'tracks' => fn ($q) => $q->where('is_published', true)->with('genre')->orderByDesc('play_count')->take(20),
            'albums' => fn ($q) => $q->publiclyVisible()->withPublicTrackCount()->orderByDesc('release_date')->orderByDesc('created_at'),
        ]);

        if (auth()->check()) {
            $this->isFollowing = Follow::where('user_id', auth()->id())
                ->where('artist_profile_id', $profile->id)
                ->exists();
        }
    }

    public function with(): array
    {
        $description = $this->profile->bio
            ?: 'Explore music, albums, and artist updates from ' . ($this->profile->stage_name ?? $this->profile->user->name) . ' on GrinMuzik.';
        $shareImage = $this->profile->getAvatarMediaUrl();
        $genreNames = $this->profile->genres->pluck('name')->filter();
        $relatedArtistsSummary = $genreNames->isNotEmpty()
            ? 'Listeners who stay in '.$genreNames->take(2)->join(' and ').' also end up exploring these artists.'
            : 'Nearby artists based on listener overlap, catalog shape, and adjacent sound.';
        $relatedTracksSummary = $genreNames->isNotEmpty()
            ? 'Tracks pulled from the same genre orbit and artist neighborhood.'
            : 'Tracks nearby in artist crossover and listener momentum.';

        $genreIds = $this->profile->genres->pluck('id')->filter()->values();

        $relatedArtists = Cache::remember(
            "artist-page.{$this->profile->id}.related-artists.v2",
            now()->addMinutes(10),
            function () use ($genreIds) {
                $query = ArtistProfile::query()
                    ->withFeaturedRecommendationMetrics()
                    ->publiclyDiscoverable()
                    ->with(['user', 'genres'])
                    ->whereKeyNot($this->profile->id);

                if ($genreIds->isNotEmpty()) {
                    $query->whereHas('genres', fn ($genres) => $genres->whereIn('genres.id', $genreIds->all()));
                }

                $artists = $query
                    ->orderByDesc('followers_count')
                    ->orderByDesc('total_plays')
                    ->take(24)
                    ->get();

                $artists = ArtistProfile::rankForFeaturedListing($artists)->take(6)->values();

                if ($artists->isNotEmpty()) {
                    return $artists;
                }

                return ArtistProfile::query()
                    ->withFeaturedRecommendationMetrics()
                    ->publiclyDiscoverable()
                    ->with('user')
                    ->whereKeyNot($this->profile->id)
                    ->orderByDesc('followers_count')
                    ->orderByDesc('total_plays')
                    ->take(24)
                    ->get()
                    ->pipe(fn ($artists) => ArtistProfile::rankForFeaturedListing($artists)->take(6)->values());
            }
        );

        $relatedTracks = Cache::remember(
            "artist-page.{$this->profile->id}.related-tracks.v1",
            now()->addMinutes(10),
            function () use ($genreIds, $relatedArtists) {
                $query = Track::query()
                    ->with(['artistProfile.user', 'genre'])
                    ->where('is_published', true)
                    ->where('artist_profile_id', '!=', $this->profile->id);

                if ($relatedArtists->isNotEmpty()) {
                    $query->whereIn('artist_profile_id', $relatedArtists->pluck('id')->all());
                } elseif ($genreIds->isNotEmpty()) {
                    $query->whereIn('genre_id', $genreIds->all());
                }

                $tracks = $query
                    ->orderByDesc('play_count')
                    ->orderByDesc('downloads_count')
                    ->take(6)
                    ->get();

                if ($tracks->isNotEmpty()) {
                    return $tracks;
                }

                return Track::query()
                    ->with(['artistProfile.user', 'genre'])
                    ->where('is_published', true)
                    ->where('artist_profile_id', '!=', $this->profile->id)
                    ->orderByDesc('play_count')
                    ->take(6)
                    ->get();
            }
        );

        return [
            'profile' => $this->profile,
            'relatedArtists' => $relatedArtists,
            'relatedTracks' => $relatedTracks,
            'relatedArtistsSummary' => $relatedArtistsSummary,
            'relatedTracksSummary' => $relatedTracksSummary,
            'seo' => [
                'title' => $this->profile->stage_name ?? $this->profile->user->name,
                'description' => Str::limit(strip_tags($description), 160),
                'canonical' => route('artist.page', $this->profile),
                'type' => 'website',
                'image' => $shareImage,
                'json_ld' => [
                    [
                        '@context' => 'https://schema.org',
                        '@type' => 'MusicGroup',
                        'name' => $this->profile->stage_name ?? $this->profile->user->name,
                        'description' => Str::limit(strip_tags($description), 160),
                        'url' => route('artist.page', $this->profile),
                        'image' => $shareImage ?: null,
                    ],
                ],
            ],
        ];
    }

    public function toggleFollow(): void
    {
        if (!auth()->check()) {
            $this->dispatch('open-modal', id: 'login-required');

            return;
        }

        $userId = auth()->id();
        $existing = Follow::where('user_id', $userId)
            ->where('artist_profile_id', $this->profile->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->profile->decrement('followers_count');
            $this->isFollowing = false;
        } else {
            Follow::create([
                'user_id' => $userId,
                'artist_profile_id' => $this->profile->id,
            ]);
            $this->profile->increment('followers_count');
            $this->isFollowing = true;

            app(ArtistActivityNotifier::class)->sendFollowerMilestoneIfReached($this->profile->fresh('user'));
        }
    }
};
?>

<div class="min-h-screen">
    <section class="relative overflow-hidden">
        <div class="absolute inset-x-0 top-0 h-72 overflow-hidden sm:h-96">
            @if($profile->getBannerUrl('large'))
                <img src="{{ $profile->getBannerUrl('large') }}" alt="{{ $profile->banner_alt }}" class="h-full w-full object-cover">
            @else
                <div class="h-full w-full bg-gradient-to-br from-primary-700 via-primary-800 to-primary-900"></div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-b from-black/35 via-black/45 to-black/80"></div>
        </div>

        <div class="relative mx-auto max-w-5xl px-4 pb-10 pt-28 sm:px-6 sm:pt-40 lg:px-8">
            <div class="rounded-[2rem] border border-white/15 bg-black/35 p-5 shadow-2xl backdrop-blur-xl sm:p-8">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-end">
                        <div class="h-28 w-28 shrink-0 overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-primary-400 to-primary-700 ring-4 ring-white/70 shadow-xl sm:h-36 sm:w-36">
                            @php
                                $avatarUrl = $profile->getAvatarMediaUrl();
                            @endphp
                            @if($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="{{ $profile->avatar_alt }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-4xl font-bold text-white/30">
                                    {{ strtoupper(substr($profile->stage_name ?? $profile->user->name, 0, 1)) }}
                                </div>
                            @endif
                        </div>

                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <h1 class="text-2xl font-bold leading-tight text-white sm:text-4xl">
                                    {{ $profile->stage_name ?? $profile->user->name }}
                                </h1>
                                @if($profile->is_verified)
                                    <span class="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-primary-100">
                                        Verified
                                    </span>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-3 text-sm text-white/75">
                                <span class="rounded-full bg-white/10 px-3 py-1">{{ number_format($profile->followers_count) }} followers</span>
                                <span class="rounded-full bg-white/10 px-3 py-1">{{ $profile->tracks->count() }} tracks</span>
                                <span class="rounded-full bg-white/10 px-3 py-1">{{ number_format($profile->tracks->sum('play_count')) }} plays</span>
                            </div>

                            @if($profile->bio)
                                <p class="max-w-2xl text-sm leading-relaxed text-white/80">{{ $profile->bio }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="shrink-0">
                        <button wire:click="toggleFollow"
                                class="rounded-full px-6 py-2.5 text-sm font-semibold shadow-lg transition {{ $isFollowing
                                    ? 'bg-white/10 text-white hover:bg-white/20'
                                    : 'bg-primary text-white hover:bg-primary-500' }}">
                            {{ $isFollowing ? 'Following' : 'Follow' }}
                        </button>
                    </div>
                </div>

                @if($profile->genres->count())
                    <div class="mt-6 flex flex-wrap gap-2">
                        @foreach($profile->genres as $genre)
                            <a href="{{ route('browse', ['genre' => $genre->slug]) }}"
                               class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-medium text-white/90 transition hover:bg-white/20">
                                {{ $genre->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        @if($profile->tracks->count())
            <section class="mb-10">
                <h2 class="mb-4 text-xl font-bold text-gray-800">Tracks</h2>
                <div class="space-y-2">
                    @foreach($profile->tracks as $i => $track)
                        <div class="group flex cursor-pointer items-center gap-3 rounded-xl p-3 transition hover:bg-white/50 glass-card"
                             @click="Livewire.dispatch('play-track', { id: {{ $track->id }} })">
                            <span class="w-6 text-center text-sm text-gray-300 group-hover:hidden">{{ $i + 1 }}</span>
                            <svg class="hidden h-6 w-6 shrink-0 text-primary group-hover:block" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg">
                                <img src="{{ $track->getCoverUrl() }}" alt="{{ $track->cover_alt }}" class="h-full w-full object-cover">
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-800">{{ $track->title }}</p>
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-400">
                                    <span>{{ number_format($track->play_count) }} plays</span>
                                    <x-track-duration :track="$track" class="text-gray-400" />
                                </div>
                            </div>
                            @if($track->requires_donation)
                                <span class="shrink-0 rounded-full bg-yellow-100 px-2 py-0.5 text-xs text-yellow-700">
                                    ${{ number_format($track->donation_amount, 2) }}
                                </span>
                            @endif
                            <x-queue-track-button :track="$track" label="+" class="px-2.5 py-1.5 text-[11px]" />
                            @livewire('like-button', ['trackId' => $track->id], key('like-'.$track->id))
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if($profile->albums->count())
            <section class="mb-10">
                <h2 class="mb-4 text-xl font-bold text-gray-800">Albums &amp; EPs</h2>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                    @foreach($profile->albums as $album)
                        <a href="{{ route('album.show', $album) }}" wire:navigate class="overflow-hidden rounded-xl transition hover:bg-white/50 glass-card">
                            <div class="aspect-square">
                                <img src="{{ $album->getCoverUrl('large') }}" alt="{{ $album->cover_alt }}" class="h-full w-full object-cover">
                            </div>
                            <div class="p-2">
                                <p class="truncate text-sm font-semibold text-gray-800">{{ $album->title }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ ucfirst($album->type) }}
                                    @if($album->release_date)
                                        &middot; {{ $album->release_date->year }}
                                    @endif
                                    &middot; {{ $album->public_tracks_count }} track{{ $album->public_tracks_count === 1 ? '' : 's' }}
                                </p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.18em] {{ $album->is_upcoming ? 'text-amber-600' : 'text-emerald-600' }}">{{ $album->release_status_label }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if($relatedArtists->isNotEmpty())
            <section class="mb-10 rounded-[2rem] glass-card p-5 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-primary/70">Fans Also Explore</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">Artists moving in a similar lane</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $relatedArtistsSummary }}</p>
                    </div>
                    <a href="{{ route('browse') }}" wire:navigate class="text-sm font-semibold text-primary transition hover:text-primary-600">Browse more</a>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    @foreach($relatedArtists as $artist)
                        <a href="{{ route('artist.page', $artist) }}"
                           wire:navigate
                           class="rounded-[1.5rem] border border-white/60 bg-white/75 p-4 text-center shadow-sm transition hover:-translate-y-0.5 hover:bg-white/95 hover:shadow-md">
                            <div class="mx-auto h-16 w-16 overflow-hidden rounded-full bg-gradient-to-br from-primary-100 to-primary-200 ring-2 ring-white/80">
                                @php
                                    $avatarUrl = $artist->getAvatarMediaUrl();
                                @endphp
                                @if($avatarUrl)
                                    <img src="{{ $avatarUrl }}" alt="{{ $artist->avatar_alt }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-lg font-black text-primary-500">
                                        {{ strtoupper(substr($artist->display_name, 0, 1)) }}
                                    </div>
                                @endif
                            </div>
                            <p class="mt-3 truncate text-sm font-semibold text-gray-900">{{ $artist->display_name }}</p>
                            <p class="mt-1 text-[11px] text-gray-500">
                                {{ $artist->is_featured ? "Editor's Pick · " : '' }}{{ number_format($artist->followers_count) }} followers
                            </p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if($relatedTracks->isNotEmpty())
            <section class="mb-10 rounded-[2rem] glass-card p-5 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-primary/70">Because You Listen Here</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">Related tracks worth the next tap</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $relatedTracksSummary }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($relatedTracks as $track)
                        <article class="rounded-[1.5rem] border border-white/60 bg-white/75 p-3 shadow-sm transition hover:-translate-y-0.5 hover:bg-white/95 hover:shadow-md">
                            <div class="flex items-center gap-3">
                                <button @click="Livewire.dispatch('play-track', { id: {{ $track->id }} })"
                                        class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200">
                                    @if($track->getCoverUrl())
                                        <img src="{{ $track->getCoverUrl() }}" alt="{{ $track->cover_alt }}" class="h-full w-full object-cover">
                                    @endif
                                </button>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('track.show', $track) }}" wire:navigate class="truncate text-sm font-semibold text-gray-900 hover:text-primary">
                                        {{ $track->title }}
                                    </a>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                        <a href="{{ route('artist.page', $track->artistProfile) }}" wire:navigate class="truncate hover:text-primary">
                                            {{ $track->artistProfile?->display_name ?? 'Unknown artist' }}
                                        </a>
                                        @if($track->genre)
                                            <span>{{ $track->genre->name }}</span>
                                        @endif
                                        <x-track-duration :track="$track" class="text-gray-400" />
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center gap-3 text-[11px] font-medium text-gray-400">
                                        <span>{{ number_format($track->play_count) }} plays</span>
                                        <span>{{ number_format($track->downloads_count) }} downloads</span>
                                    </div>
                                    <div class="mt-3">
                                        <x-queue-track-button :track="$track" label="+" class="px-2.5 py-1.5 text-[11px]" />
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @php
            $socialLinks = array_filter([
                'spotify' => $profile->spotify_url ?? null,
                'youtube' => $profile->youtube_url ?? null,
                'instagram' => $profile->instagram_url ?? null,
                'twitter' => $profile->twitter_url ?? null,
                'website' => $profile->website_url ?? null,
            ]);
        @endphp

        @if(count($socialLinks))
            <div class="mb-10 flex flex-wrap gap-3">
                @foreach($socialLinks as $platform => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener"
                       class="flex items-center gap-1.5 rounded-full bg-white/70 px-3 py-1.5 text-sm text-gray-600 transition hover:bg-white hover:text-gray-900">
                        {{ ucfirst($platform) }}
                        <svg class="h-3.5 w-3.5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
