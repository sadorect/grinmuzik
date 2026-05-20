<?php

use App\Models\Album;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.glass-app')] class extends Component
{
    public Album $album;

    public function mount(Album $album): void
    {
        abort_if(
            ! Album::query()->publiclyVisible()->whereKey($album->getKey())->exists(),
            404,
        );

        $this->album = $album->load([
            'artist.user',
            'genre',
            'publicTracks' => fn ($query) => $query
                ->with(['artistProfile.user', 'genre'])
                ->orderByRaw('CASE WHEN track_number IS NULL THEN 1 ELSE 0 END')
                ->orderBy('track_number')
                ->orderBy('title'),
        ]);
    }

    public function with(): array
    {
        $tracks = $this->album->publicTracks;
        $trackIds = $tracks->pluck('id')->values()->all();
        $artistName = $this->album->artist?->display_name ?? 'Unknown artist';
        $description = $this->album->description
            ?: 'Explore the ' . $this->album->title . ' release by ' . $artistName . ' on GrinMuzik.';

        return [
            'tracks' => $tracks,
            'trackIds' => $trackIds,
            'totalDuration' => (int) $tracks->sum('duration'),
            'shareUrl' => route('album.show', $this->album),
            'seo' => [
                'title' => $this->album->title,
                'description' => Str::limit(strip_tags($description), 160),
                'canonical' => route('album.show', $this->album),
                'type' => 'website',
                'image' => $this->album->getShareImageUrl(),
                'robots' => 'index,follow',
                'json_ld' => [
                    [
                        '@context' => 'https://schema.org',
                        '@type' => 'MusicAlbum',
                        'name' => $this->album->title,
                        'description' => Str::limit(strip_tags($description), 160),
                        'url' => route('album.show', $this->album),
                        'image' => $this->album->getShareImageUrl() ?: null,
                        'datePublished' => $this->album->release_date?->toDateString(),
                        'byArtist' => [
                            '@type' => 'MusicGroup',
                            'name' => $artistName,
                            'url' => $this->album->artist ? route('artist.page', $this->album->artist) : null,
                        ],
                        'numTracks' => $tracks->count(),
                        'track' => $tracks->take(20)->values()->map(fn ($track) => [
                            '@type' => 'MusicRecording',
                            'name' => $track->title,
                            'url' => route('track.show', $track),
                        ])->all(),
                    ],
                ],
            ],
        ];
    }

    public function formatDuration(int $seconds): string
    {
        if ($seconds < 1) {
            return '0:00';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds);
        }

        return sprintf('%d:%02d', $minutes, $remainingSeconds);
    }
};
?>

<div
    class="min-h-screen px-4 py-8 sm:px-6 lg:px-8"
    x-data="{
        copied: false,
        shareUrl: @js($shareUrl),
        async share() {
            if (navigator.share) {
                await navigator.share({ title: @js($album->title), text: @js($album->description ?: $album->title), url: this.shareUrl });
                return;
            }

            await this.copy();
        },
        async copy() {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(this.shareUrl);
                this.copied = true;
                setTimeout(() => this.copied = false, 2200);
                return;
            }

            window.prompt('Copy this album link:', this.shareUrl);
        }
    }"
>
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="glass-card relative overflow-hidden rounded-[2rem]">
            <div class="absolute inset-0">
                @if($album->getCoverUrl('large'))
                    <img src="{{ $album->getCoverUrl('large') }}" alt="{{ $album->cover_alt }}" class="h-full w-full object-cover opacity-25 blur-sm scale-105">
                @else
                    <div class="h-full w-full bg-gradient-to-br from-primary-100 via-white to-primary-50"></div>
                @endif
            </div>
            <div class="absolute inset-0 bg-gradient-to-br from-white/90 via-white/85 to-white/75"></div>

            <div class="relative z-10 grid gap-6 p-5 sm:p-8 lg:grid-cols-[220px,minmax(0,1fr)]">
                <div class="mx-auto w-full max-w-[220px]">
                    <div class="aspect-square overflow-hidden rounded-[2rem] border border-white/70 bg-gradient-to-br from-primary-100 to-primary-200 shadow-2xl">
                        @if($album->getCoverUrl('large'))
                            <img src="{{ $album->getCoverUrl('large') }}" alt="{{ $album->cover_alt }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center">
                                <svg class="h-16 w-16 text-primary-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55A4 4 0 1010 17V7h8V3h-6z"/></svg>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col justify-between gap-6">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border border-primary/10 bg-primary/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-700">{{ strtoupper($album->type) }}</span>
                            <span class="rounded-full {{ $album->is_upcoming ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }} px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em]">{{ $album->release_status_label }}</span>
                        </div>

                        <div>
                            <h1 class="text-3xl font-black tracking-tight text-gray-900 sm:text-5xl">{{ $album->title }}</h1>
                            @if($album->artist)
                                <a href="{{ route('artist.page', $album->artist) }}" wire:navigate class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-primary transition hover:text-primary-600 sm:text-base">
                                    {{ $album->artist->display_name }}
                                </a>
                            @endif
                            <p class="mt-2 text-sm text-gray-500 sm:text-base">
                                @if($album->release_date)
                                    {{ $album->is_upcoming ? 'Coming' : 'Released' }} {{ $album->release_date->format('M j, Y') }}
                                @else
                                    {{ $album->updated_at->diffForHumans() }}
                                @endif
                            </p>
                        </div>

                        <p class="max-w-3xl text-sm leading-relaxed text-gray-600 sm:text-base">
                            {{ $album->description ?: 'A full release page for this project, with the playable tracklist, artist context, and a direct share link.' }}
                        </p>

                        <div class="grid gap-3 sm:grid-cols-4">
                            <div class="rounded-2xl border border-white/70 bg-white/80 px-4 py-3 shadow-sm">
                                <p class="text-2xl font-black text-gray-900">{{ number_format($tracks->count()) }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">Tracks</p>
                            </div>
                            <div class="rounded-2xl border border-white/70 bg-white/80 px-4 py-3 shadow-sm">
                                <p class="text-2xl font-black text-gray-900">{{ $this->formatDuration($totalDuration) }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">Runtime</p>
                            </div>
                            <div class="rounded-2xl border border-white/70 bg-white/80 px-4 py-3 shadow-sm">
                                <p class="text-2xl font-black text-gray-900">{{ number_format($tracks->sum('play_count')) }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">Combined Plays</p>
                            </div>
                            <div class="rounded-2xl border border-white/70 bg-white/80 px-4 py-3 shadow-sm">
                                <p class="text-2xl font-black text-gray-900">{{ $album->genre?->name ?? 'Open' }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">Genre</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        @if($tracks->isNotEmpty())
                            <button
                                onclick="Livewire.dispatch('play-playlist', { ids: {{ json_encode($trackIds) }} })"
                                class="inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-primary-500"
                            >
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                Play All
                            </button>

                            <button
                                onclick="Livewire.dispatch('queue-playlist', { ids: {{ json_encode($trackIds) }} })"
                                class="inline-flex items-center gap-2 rounded-full border border-white/70 bg-white/80 px-6 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-white"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10"/>
                                </svg>
                                Queue All
                            </button>
                        @endif

                        <button
                            @click="share()"
                            class="inline-flex items-center gap-2 rounded-full border border-white/70 bg-white/80 px-6 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-white"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C9.173 13.13 9.713 13 10.286 13c.573 0 1.113.13 1.602.342m-3.204 0a3 3 0 110-2.684m3.204 2.684a3 3 0 100-2.684m0 0L15.5 8m-5.214 5.342L6.5 16"/>
                            </svg>
                            Share
                        </button>

                        <button
                            @click="copy()"
                            class="inline-flex items-center gap-2 rounded-full border border-white/70 bg-white/80 px-6 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-white"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <span x-show="!copied">Copy Link</span>
                            <span x-show="copied" style="display:none">Copied</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="glass-card overflow-hidden rounded-[2rem]">
            <div class="border-b border-white/40 px-5 py-4 sm:px-6">
                <h2 class="text-lg font-black tracking-tight text-gray-900">Tracklist</h2>
                <p class="text-sm text-gray-500">
                    {{ $album->is_upcoming ? 'Tracks will unlock here as the release goes live or when the artist publishes them.' : 'Every published track on this release is playable here, with direct links back to the full track pages.' }}
                </p>
            </div>

            @if($tracks->isEmpty())
                <div class="px-6 py-16 text-center">
                    <p class="text-lg font-semibold text-gray-800">{{ $album->is_upcoming ? 'This release is on the way' : 'No tracks are live on this release yet.' }}</p>
                    <p class="mt-2 text-sm text-gray-500">{{ $album->is_upcoming ? 'Check back on release day for the full tracklist.' : 'Published tracks will appear here once the artist adds them.' }}</p>
                </div>
            @else
                <ul class="divide-y divide-white/30">
                    @foreach($tracks as $index => $track)
                        <li class="group flex items-center gap-3 px-4 py-3 transition hover:bg-white/45 sm:px-6">
                            <div class="w-8 shrink-0 text-center text-sm font-semibold text-gray-400 group-hover:hidden">{{ $track->track_number ?: $index + 1 }}</div>
                            <button
                                @click="Livewire.dispatch('play-track', { id: {{ $track->id }} })"
                                class="hidden w-8 shrink-0 items-center justify-center text-primary group-hover:flex"
                                aria-label="Play {{ $track->title }}"
                            >
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </button>

                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200">
                                @if($track->getCoverUrl())
                                    <img src="{{ $track->getCoverUrl() }}" alt="{{ $track->cover_alt }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center">
                                        <svg class="h-5 w-5 text-primary-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55A4 4 0 1014 17V7h4V3h-6z"/></svg>
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <a href="{{ route('track.show', $track) }}" wire:navigate class="block truncate text-sm font-semibold text-gray-900 transition hover:text-primary">
                                    {{ $track->title }}
                                </a>
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                    <a href="{{ route('artist.page', $track->artistProfile) }}" wire:navigate class="truncate hover:text-primary">
                                        {{ $track->artistProfile?->display_name ?? $track->artistProfile?->user?->name ?? 'Unknown artist' }}
                                    </a>
                                    <span>&middot;</span>
                                    <span>{{ number_format($track->play_count) }} plays</span>
                                    <x-track-duration :track="$track" class="text-gray-400" />
                                </div>
                            </div>

                            <button
                                @click="Livewire.dispatch('queue-track', { id: {{ $track->id }} })"
                                class="hidden rounded-full border border-white/60 bg-white/80 px-3 py-2 text-xs font-semibold text-gray-600 shadow-sm transition hover:bg-white sm:inline-flex"
                            >
                                Queue
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>