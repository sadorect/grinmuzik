<?php

use App\Models\ArtistProfile;
use App\Models\Track;
use App\Models\Comment;
use App\Models\Like;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.glass-app')] class extends Component {

    public Track $track;
    public string $commentBody = '';

    // Resolved via route-model binding: /track/{track:slug}
    public function mount(Track $track): void
    {
        abort_if(!$track->is_published, 404);
        $this->track = $track->load(['artistProfile.user', 'artistProfile.genres', 'genre', 'album',
                                    'comments' => fn($q) => $q->whereNull('parent_id')->with('user', 'replies.user')->latest()]);
    }

    public function with(): array
    {
        $description = $this->track->description
            ?: 'Listen to ' . $this->track->title . ' by ' . ($this->track->artistProfile->stage_name ?? $this->track->artistProfile->user->name) . ' on GrinMuzik.';
        $relatedTracksSummary = $this->track->mood_label
            ? 'Picked for the '.$this->track->mood_label.' lane, plus adjacent artists and tracks that land in the same space.'
            : ($this->track->genre
                ? 'Picked from the '.$this->track->genre->name.' lane and the surrounding artist orbit.'
                : 'Picked from related artists and the wider lane this track sits inside.');
        $recommendedArtistsSummary = $this->track->genre
            ? 'Artists listeners often reach for when they stay in '.$this->track->genre->name.' mode.'
            : 'Artists nearby in style, energy, and listener crossover.';

        $relatedTracks = Cache::remember(
            "track-page.{$this->track->id}.related-tracks.v1",
            now()->addMinutes(10),
            function () {
                $artistId = $this->track->artist_profile_id;
                $genreId = $this->track->genre_id;

                $query = Track::query()
                    ->with(['artistProfile.user', 'genre'])
                    ->where('is_published', true)
                    ->whereKeyNot($this->track->id);

                if ($artistId || $genreId) {
                    $query->where(function ($nested) use ($artistId, $genreId): void {
                        if ($artistId) {
                            $nested->where('artist_profile_id', $artistId);
                        }

                        if ($genreId) {
                            $method = $artistId ? 'orWhere' : 'where';
                            $nested->{$method}('genre_id', $genreId);
                        }
                    });
                }

                $related = $query
                    ->orderByRaw('CASE WHEN artist_profile_id = ? THEN 0 ELSE 1 END', [$artistId ?: 0])
                    ->orderByRaw('CASE WHEN genre_id = ? THEN 0 ELSE 1 END', [$genreId ?: 0])
                    ->orderByDesc('play_count')
                    ->orderByDesc('downloads_count')
                    ->take(6)
                    ->get();

                if ($related->isNotEmpty()) {
                    return $related;
                }

                return Track::query()
                    ->with(['artistProfile.user', 'genre'])
                    ->where('is_published', true)
                    ->whereKeyNot($this->track->id)
                    ->orderByDesc('play_count')
                    ->orderByDesc('downloads_count')
                    ->take(6)
                    ->get();
            }
        );

        $recommendedArtists = Cache::remember(
            "track-page.{$this->track->id}.recommended-artists.v2",
            now()->addMinutes(10),
            function () {
                $genreIds = collect([$this->track->genre_id])
                    ->merge($this->track->artistProfile?->genres?->pluck('id') ?? collect())
                    ->filter()
                    ->unique()
                    ->values();

                $query = ArtistProfile::query()
                    ->withFeaturedRecommendationMetrics()
                    ->publiclyDiscoverable()
                    ->with(['user', 'genres'])
                    ->whereKeyNot($this->track->artist_profile_id);

                if ($genreIds->isNotEmpty()) {
                    $query->whereHas('genres', fn ($genres) => $genres->whereIn('genres.id', $genreIds->all()));
                }

                $artists = $query
                    ->orderByDesc('followers_count')
                    ->orderByDesc('total_plays')
                    ->take(16)
                    ->get();

                $artists = ArtistProfile::rankForFeaturedListing($artists)->take(4)->values();

                if ($artists->isNotEmpty()) {
                    return $artists;
                }

                return ArtistProfile::query()
                    ->withFeaturedRecommendationMetrics()
                    ->publiclyDiscoverable()
                    ->with('user')
                    ->whereKeyNot($this->track->artist_profile_id)
                    ->orderByDesc('followers_count')
                    ->orderByDesc('total_plays')
                    ->take(16)
                    ->get()
                    ->pipe(fn ($artists) => ArtistProfile::rankForFeaturedListing($artists)->take(4)->values());
            }
        );

        return [
            'track'      => $this->track,
            'likesCount' => Like::where('track_id', $this->track->id)->count(),
            'shareUrl'   => route('track.show', $this->track->slug),
            'relatedTracks' => $relatedTracks,
            'recommendedArtists' => $recommendedArtists,
            'relatedTracksSummary' => $relatedTracksSummary,
            'recommendedArtistsSummary' => $recommendedArtistsSummary,
            'seo'        => [
                'title' => $this->track->title,
                'description' => Str::limit(strip_tags($description), 160),
                'canonical' => route('track.show', $this->track->slug),
                'type' => 'website',
                'image' => $this->track->getCoverUrl(),
                'json_ld' => [
                    [
                        '@context' => 'https://schema.org',
                        '@type' => 'MusicRecording',
                        'name' => $this->track->title,
                        'url' => route('track.show', $this->track->slug),
                        'description' => Str::limit(strip_tags($description), 160),
                        'duration' => $this->track->duration ? 'PT' . $this->track->duration . 'S' : null,
                        'image' => $this->track->getCoverUrl() ?: null,
                        'byArtist' => [
                            '@type' => 'MusicGroup',
                            'name' => $this->track->artistProfile->stage_name ?? $this->track->artistProfile->user->name,
                            'url' => route('artist.page', $this->track->artistProfile),
                        ],
                        'genre' => $this->track->genre?->name,
                    ],
                ],
            ],
        ];
    }

    public function play(): void
    {
        $this->dispatch('play-track', id: $this->track->id);
    }

    public function download(): void
    {
        if (!auth()->check()) {
            $this->dispatchDownloadLoginPrompt();

            return;
        }

        if (! $this->track->canBeDownloadedBy(auth()->user())) {
            $this->dispatch(
                'download-unavailable',
                message: $this->track->requires_donation
                    ? 'Unlock this track before downloading it.'
                    : 'This track is not available for download right now.'
            );

            return;
        }

        $this->dispatch(
            'start-download',
            url: route('track.download', $this->track),
            filename: $this->track->getDownloadFilename(),
        );
    }

    protected function dispatchDownloadLoginPrompt(): void
    {
        $this->dispatch(
            'open-modal',
            id: 'login-required',
            title: 'Sign in to download',
            message: 'Log in or create a listener account to download this track.',
            loginUrl: route('track.download', $this->track),
            registerUrl: route('register', [
                'role' => 'listener',
                'show_role_guide' => 0,
            ]),
        );
    }

    public function submitComment(): void
    {
        $this->validate(['commentBody' => ['required', 'string', 'min:2', 'max:500']]);

        $user = auth()->user();
        abort_unless($user, 403);

        Comment::create([
            'user_id'    => $user->id,
            'track_id'   => $this->track->id,
            'body'       => $this->commentBody,
            'parent_id'  => null,
        ]);

        $this->commentBody = '';
        $this->track->load(['comments' => fn($q) => $q->whereNull('parent_id')->with('user', 'replies.user')->latest()]);
    }
};
?>

<div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8"
     x-data="{
         copied: false,
         lyricLinkCopied: false,
         lyricTextCopied: false,
         shareUrl: @js($shareUrl),
         lyricsText: @js($track->lyrics),
         async copyValue(value) {
             if (!value) {
                 return false;
             }

             if (navigator.clipboard?.writeText) {
                 try {
                     await navigator.clipboard.writeText(value);
                     return true;
                 } catch (_) {
                 }
             }

             const input = document.createElement('textarea');
             input.value = value;
             input.setAttribute('readonly', '');
             input.style.position = 'absolute';
             input.style.left = '-9999px';
             document.body.appendChild(input);
             input.select();

             const copied = document.execCommand('copy');
             document.body.removeChild(input);

             return copied;
         },
         flashCopied(stateKey) {
             this[stateKey] = true;
             setTimeout(() => this[stateKey] = false, 2200);
         },
         async shareTrack() {
             if (navigator.share) {
                 await navigator.share({ title: @js($track->title), text: @js($track->artistProfile->stage_name ?? $track->artistProfile->user->name), url: this.shareUrl });
                 return;
             }

             await this.copyTrackLink();
         },
         getLyricsShareUrl() {
             return `${this.shareUrl}#lyrics`;
         },
         scrollToLyrics() {
             this.$refs.lyricsSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
         },
         scrollToComments() {
             this.$refs.commentsSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
         },
         async shareLyrics() {
             const lyricsUrl = this.getLyricsShareUrl();
             const shareTitle = `${@js($track->title)} lyrics`;
             const shareText = `Read the lyrics for ${@js($track->title)} by ${@js($track->artistProfile->stage_name ?? $track->artistProfile->user->name)}.`;

             if (navigator.share) {
                 await navigator.share({ title: shareTitle, text: shareText, url: lyricsUrl });
                 return;
             }

             await this.copyLyricsLink();
         },
         async copyTrackLink() {
             if (await this.copyValue(this.shareUrl)) {
                 this.flashCopied('copied');
                 return;
             }

             window.prompt('Copy this track link:', this.shareUrl);
         },
         async copyLyricsLink() {
             const lyricsUrl = this.getLyricsShareUrl();

             if (await this.copyValue(lyricsUrl)) {
                 this.flashCopied('lyricLinkCopied');
                 return;
             }

             window.prompt('Copy this lyrics link:', lyricsUrl);
         },
         async copyLyricsText() {
             const lyricsText = (this.lyricsText || '').trim();

             if (!lyricsText) {
                 return;
             }

             if (await this.copyValue(lyricsText)) {
                 this.flashCopied('lyricTextCopied');
                 return;
             }

             window.prompt('Copy these lyrics:', lyricsText);
         }
     }"
    >
    <div class="max-w-4xl mx-auto">

        {{-- Hero section --}}
        <div class="flex flex-col sm:flex-row gap-8 mb-10">
            {{-- Cover art --}}
            <div class="w-full sm:w-56 h-56 shrink-0 rounded-2xl overflow-hidden shadow-2xl">
                <img src="{{ $track->getCoverUrl('large') }}" alt="{{ $track->title }}" class="w-full h-full object-cover">
            </div>

            {{-- Info --}}
            <div class="flex-1 flex flex-col justify-between">
                <div>
                    <div class="flex flex-wrap gap-2 mb-2">
                        @if($track->genre)
                            <a href="{{ route('browse', ['genre' => $track->genre->slug]) }}"
                               class="text-xs bg-primary-100 text-primary-600 px-3 py-1 rounded-full hover:bg-primary-200 transition">
                                {{ $track->genre->name }}
                            </a>
                        @endif
                        @if($track->album && $track->album->is_published && in_array($track->album->type, \App\Models\Album::PUBLIC_TYPES, true))
                            <a href="{{ route('album.show', $track->album) }}"
                               wire:navigate
                               class="text-xs bg-gray-100 text-gray-600 px-3 py-1 rounded-full hover:bg-gray-200 transition">
                                {{ $track->album->title }}
                            </a>
                        @endif
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-1">{{ $track->title }}</h1>
                    <a href="{{ route('artist.page', $track->artistProfile) }}"
                       wire:navigate
                       class="text-lg text-primary-500 hover:text-primary-600 transition">
                        {{ $track->artistProfile->stage_name ?? $track->artistProfile->user->name }}
                    </a>
                    @if($track->artistProfile->is_verified)
                        <span class="ml-1 text-sm text-primary-400">✓</span>
                    @endif

                    {{-- Stats row --}}
                    <div class="flex flex-wrap items-center gap-4 mt-3 text-sm text-gray-400">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4 opacity-60" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            {{ number_format($track->play_count) }} plays
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4 opacity-60" fill="currentColor" viewBox="0 0 24 24"><path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                            {{ number_format($likesCount) }} likes
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            {{ number_format($track->downloads_count) }} downloads
                        </span>
                        @if($track->formatted_duration)
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $track->formatted_duration }}
                            </span>
                        @endif
                    </div>

                    @if($track->description)
                        <p class="text-gray-600 mt-3 text-sm leading-relaxed">{{ $track->description }}</p>
                    @endif

                    @if($track->album && $track->album->is_published && in_array($track->album->type, \App\Models\Album::PUBLIC_TYPES, true))
                        <p class="mt-3 text-sm text-gray-500">
                            From the release
                            <a href="{{ route('album.show', $track->album) }}" wire:navigate class="font-semibold text-primary transition hover:text-primary-600">
                                {{ $track->album->title }}
                            </a>
                            @if($track->album->is_upcoming)
                                <span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700">Upcoming</span>
                            @endif
                        </p>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3 mt-4">
                    @if($track->requires_donation)
                        {{-- Unlock / donation gate --}}
                        @livewire('unlock-track', ['track' => $track], key('unlock-'.$track->id))
                    @else
                        <button wire:click="play"
                                class="flex items-center gap-2 bg-primary hover:bg-primary-500 text-white px-6 py-3 rounded-full font-semibold transition shadow-lg">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            Play
                        </button>
                    @endif
                    <x-queue-track-button :track="$track" class="px-4 py-2 text-sm" />
                    @livewire('like-button', ['trackId' => $track->id], key('like-'.$track->id))
                    @if(filled($track->lyrics))
                        <button @click="scrollToLyrics"
                                class="flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-2 text-sm font-medium text-primary-700 transition hover:border-primary/30 hover:bg-primary/15">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8M8 12h8M8 17h5M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                            </svg>
                            Lyrics
                        </button>
                    @endif
                    <button @click="scrollToComments"
                            title="Jump to comments"
                            class="flex items-center gap-2 glass-btn glass-btn-hover px-4 py-2 rounded-full text-sm font-medium text-gray-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8-1.05 0-2.058-.16-3-.454L3 21l1.5-4.5C3.55 15.155 3 13.62 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        Comment{{ $track->comments->count() > 0 ? ' (' . $track->comments->count() . ')' : '' }}
                    </button>
                    {{-- Download button --}}
                    <button wire:click="download"
                            title="Download track"
                            class="flex items-center gap-2 glass-btn glass-btn-hover px-4 py-2 rounded-full text-sm font-medium text-gray-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download
                    </button>
                    <button @click="shareTrack"
                            class="flex items-center gap-2 glass-btn glass-btn-hover px-4 py-2 rounded-full text-sm font-medium text-gray-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C9.173 13.13 9.713 13 10.286 13c.573 0 1.113.13 1.602.342m-3.204 0a3 3 0 110-2.684m3.204 2.684a3 3 0 100-2.684m0 0L15.5 8m-5.214 5.342L6.5 16"/>
                        </svg>
                        Share
                    </button>
                    <button @click="copyTrackLink"
                            class="flex items-center gap-2 glass-btn glass-btn-hover px-4 py-2 rounded-full text-sm font-medium text-gray-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <span x-show="!copied">Copy Link</span>
                        <span x-show="copied" style="display:none">Copied</span>
                    </button>

                    {{-- Play's UGC policy requires reporting to be reachable
                         from the content itself, not from a separate page. --}}
                    <div class="flex items-center px-2">
                        <livewire:report-content :content="$track" :key="'report-track-'.$track->id" />
                    </div>
                </div>
            </div>
        </div>

        @if(filled($track->lyrics))
            @php
                $lyricsLines = preg_split("/\r\n|\n|\r/", trim($track->lyrics)) ?: [];
                $lyricsLineCount = collect($lyricsLines)->filter(fn ($line) => filled(trim($line)))->count();
                $lyricsWordCount = str_word_count(trim($track->lyrics));
            @endphp

            <section id="lyrics" x-ref="lyricsSection" class="mb-8">
                <div class="relative overflow-hidden rounded-[2rem] border border-white/70 bg-[radial-gradient(circle_at_top_left,_rgba(255,255,255,0.95),_rgba(254,242,242,0.92)_42%,_rgba(255,255,255,0.88)_100%)] p-5 shadow-[0_24px_70px_rgba(15,23,42,0.12)] sm:p-6">
                    <div class="absolute -left-16 top-0 h-40 w-40 rounded-full bg-primary/12 blur-3xl"></div>
                    <div class="absolute -right-12 bottom-0 h-36 w-36 rounded-full bg-secondary/15 blur-3xl"></div>

                    <div class="relative">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div class="max-w-2xl">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary/70">Lyrics View</p>
                                <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900 sm:text-[2rem]">Read every line while the track plays</h2>
                                <p class="mt-2 text-sm leading-6 text-gray-500 sm:text-base">
                                    A dedicated lyrics view for <span class="font-semibold text-gray-700">{{ $track->title }}</span>.
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button @click="shareLyrics"
                                        class="inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C9.173 13.13 9.713 13 10.286 13c.573 0 1.113.13 1.602.342m-3.204 0a3 3 0 110-2.684m3.204 2.684a3 3 0 100-2.684m0 0L15.5 8m-5.214 5.342L6.5 16"/>
                                    </svg>
                                    Share Lyrics
                                </button>
                                <button @click="copyLyricsLink"
                                        class="inline-flex items-center gap-2 rounded-full border border-white/80 bg-white/80 px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary/20 hover:text-primary">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <span x-show="!lyricLinkCopied">Copy Link</span>
                                    <span x-show="lyricLinkCopied" style="display:none">Link Copied</span>
                                </button>
                                <button @click="copyLyricsText"
                                        class="inline-flex items-center gap-2 rounded-full border border-white/80 bg-white/80 px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary/20 hover:text-primary">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M9 8h6m-7 12h8a2 2 0 002-2V6a2 2 0 00-2-2H8a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span x-show="!lyricTextCopied">Copy Text</span>
                                    <span x-show="lyricTextCopied" style="display:none">Text Copied</span>
                                </button>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-gray-400">
                            <span class="rounded-full border border-white/80 bg-white/70 px-3 py-1 text-primary/80">Official Lyrics</span>
                            <span class="rounded-full border border-white/80 bg-white/70 px-3 py-1">{{ number_format($lyricsLineCount) }} lines</span>
                            <span class="rounded-full border border-white/80 bg-white/70 px-3 py-1">{{ number_format($lyricsWordCount) }} words</span>
                        </div>

                        <div class="mt-6 rounded-[1.75rem] border border-white/80 bg-white/82 p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)] sm:p-6">
                            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
                                <div class="rounded-[1.4rem] border border-primary/10 bg-gradient-to-br from-white to-primary-50/40 p-4 sm:p-5">
                                    <div class="space-y-3 text-sm leading-7 text-gray-700 sm:text-[15px]">
                                        @foreach($lyricsLines as $index => $line)
                                            @if(filled(trim($line)))
                                                <p class="flex gap-3">
                                                    <span class="mt-0.5 hidden w-7 shrink-0 text-right text-[10px] font-semibold uppercase tracking-[0.2em] text-gray-300 sm:inline">{{ $index + 1 }}</span>
                                                    <span class="whitespace-pre-line break-words">{{ $line }}</span>
                                                </p>
                                            @else
                                                <div class="h-3"></div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>

                                <div class="flex flex-col justify-between rounded-[1.4rem] border border-dashed border-primary/20 bg-primary/5 p-4 text-sm text-gray-600">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary/70">Share Options</p>
                                        <p class="mt-3 leading-6">
                                            Send listeners straight to the lyrics section, or copy the full text for captions, notes, and repost-ready snippets.
                                        </p>
                                    </div>
                                    <div class="mt-5 space-y-2">
                                        <button @click="shareLyrics"
                                                class="w-full rounded-2xl bg-gray-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-gray-800">
                                            Share This View
                                        </button>
                                        <button @click="copyLyricsText"
                                                class="w-full rounded-2xl border border-primary/20 bg-white px-4 py-3 text-sm font-semibold text-primary transition hover:border-primary/35 hover:bg-primary/5">
                                            Copy Full Lyrics
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @if($relatedTracks->isNotEmpty())
            <section class="mb-8 rounded-[2rem] glass-card p-5 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-primary/70">More Like This</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">Keep the vibe moving</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $relatedTracksSummary }}</p>
                    </div>
                    @if($track->genre)
                        <a href="{{ route('browse', ['genre' => $track->genre->slug]) }}"
                           class="inline-flex items-center gap-2 text-sm font-semibold text-primary transition hover:text-primary-600">
                            Explore {{ $track->genre->name }}
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    @endif
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($relatedTracks as $relatedTrack)
                        <article class="rounded-[1.5rem] border border-white/60 bg-white/75 p-3 shadow-sm transition hover:-translate-y-0.5 hover:bg-white/95 hover:shadow-md">
                            <div class="flex items-center gap-3">
                                <button @click="Livewire.dispatch('play-track', { id: {{ $relatedTrack->id }} })"
                                        class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200">
                                    @if($relatedTrack->getCoverUrl())
                                        <img src="{{ $relatedTrack->getCoverUrl() }}" alt="{{ $relatedTrack->cover_alt }}" class="h-full w-full object-cover">
                                    @endif
                                </button>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('track.show', $relatedTrack) }}" wire:navigate class="truncate text-sm font-semibold text-gray-900 hover:text-primary">
                                            {{ $relatedTrack->title }}
                                        </a>
                                        @if($relatedTrack->artist_profile_id === $track->artist_profile_id)
                                            <span class="rounded-full bg-primary-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-primary-700">Same Artist</span>
                                        @elseif($relatedTrack->genre_id === $track->genre_id && $track->genre_id)
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-gray-500">Same Genre</span>
                                        @elseif($relatedTrack->effective_mood && $relatedTrack->effective_mood === $track->effective_mood)
                                            <span class="rounded-full bg-black/5 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-gray-600">{{ $relatedTrack->mood_label }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                        <a href="{{ route('artist.page', $relatedTrack->artistProfile) }}" wire:navigate class="truncate hover:text-primary">
                                            {{ $relatedTrack->artistProfile?->display_name ?? 'Unknown artist' }}
                                        </a>
                                        @if($relatedTrack->genre)
                                            <span>{{ $relatedTrack->genre->name }}</span>
                                        @endif
                                        <x-track-duration :track="$relatedTrack" class="text-gray-400" />
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center gap-3 text-[11px] font-medium text-gray-400">
                                        <span>{{ number_format($relatedTrack->play_count) }} plays</span>
                                        <span>{{ number_format($relatedTrack->downloads_count) }} downloads</span>
                                    </div>
                                    <div class="mt-3">
                                        <x-queue-track-button :track="$relatedTrack" label="+" class="px-2.5 py-1.5 text-[11px]" />
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if($recommendedArtists->isNotEmpty())
            <section class="mb-8 rounded-[2rem] glass-card p-5 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-primary/70">Related Artists</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">Explore nearby scenes</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $recommendedArtistsSummary }}</p>
                    </div>
                    <a href="{{ route('browse') }}" wire:navigate class="text-sm font-semibold text-primary transition hover:text-primary-600">Discover more artists</a>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach($recommendedArtists as $artist)
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

        {{-- Comments section --}}
        <div x-ref="commentsSection" class="glass-card p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                Comments ({{ $track->comments->count() }})
            </h2>

            @auth
                <form wire:submit="submitComment" class="flex gap-3 mb-6">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shrink-0 text-sm font-bold text-white">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 flex gap-2">
                        <input
                            wire:model="commentBody"
                            type="text"
                            placeholder="Add a comment..."
                            class="flex-1 bg-white/60 border border-gray-200 rounded-xl px-4 py-2 text-gray-800 placeholder-gray-400 text-sm focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                        <button type="submit"
                                class="bg-primary hover:bg-primary-500 text-white px-4 py-2 rounded-xl text-sm font-semibold transition">
                            Post
                        </button>
                    </div>
                </form>
            @else
                <p class="text-gray-500 text-sm mb-6">
                    <a href="{{ route('login') }}" class="text-primary-500 hover:underline">Sign in</a> to leave a comment.
                </p>
            @endauth

            <div class="space-y-4">
                @forelse($track->comments->whereNull('parent_id') as $comment)
                    <div class="flex gap-3">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shrink-0 text-sm font-bold text-white">
                            {{ strtoupper(substr($comment->user->name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-gray-900 font-medium text-sm">{{ $comment->user->name }}</span>
                                <span class="text-gray-400 text-xs">{{ $comment->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-gray-600 text-sm mt-0.5">{{ $comment->body }}</p>

                            {{-- Comments are user-generated content too, so they
                                 carry the same report affordance as tracks. --}}
                            <div class="mt-1">
                                <livewire:report-content :content="$comment" :key="'report-comment-'.$comment->id" />
                            </div>

                            {{-- Replies --}}
                            @foreach($comment->replies as $reply)
                                <div class="flex gap-3 mt-3 pl-4 border-l border-gray-200">
                                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shrink-0 text-xs font-bold text-white">
                                        {{ strtoupper(substr($reply->user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="flex items-baseline gap-2">
                                            <span class="text-gray-900 font-medium text-xs">{{ $reply->user->name }}</span>
                                            <span class="text-gray-400 text-xs">{{ $reply->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="text-gray-600 text-xs mt-0.5">{{ $reply->body }}</p>

                                        <div class="mt-1">
                                            <livewire:report-content :content="$reply" :key="'report-comment-'.$reply->id" />
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-gray-400 text-sm">No comments yet. Be the first!</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
