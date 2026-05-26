<?php

use App\Models\ChartSnapshot;
use App\Models\ChartSnapshotEntry;
use App\Models\Genre;
use App\Models\Track;
use App\Support\Charts\ChartRankingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.glass-app')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $timeframe = 'week';

    #[Url]
    public ?string $genre = null;

    #[Url]
    public ?string $snapshot = null;

    public function with(): array
    {
        $genres = Cache::remember('charts.genres.v1', now()->addMinutes(15), fn () => Genre::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get());
        $historySnapshots = $this->historySnapshots();
        $selectedSnapshot = $this->selectedSnapshot($historySnapshots);

        if ($selectedSnapshot) {
            $tracks = $this->paginateSnapshotTracks($selectedSnapshot);
            $topTracks = $selectedSnapshot->entries
                ->take(3)
                ->map(fn (ChartSnapshotEntry $entry) => $this->hydrateSnapshotTrack($entry))
                ->filter()
                ->values();
            $movementMap = $this->snapshotMovementMap($selectedSnapshot);
            $stats = [
                'timeframeLabel' => $selectedSnapshot->period_type === ChartSnapshot::PERIOD_MONTH ? 'Monthly Archive' : 'Weekly Archive',
                'plays' => (int) $selectedSnapshot->total_plays,
                'likes' => (int) $selectedSnapshot->total_likes,
                'downloads' => (int) $selectedSnapshot->total_downloads,
            ];
        } else {
            [$start, $end, $previousStart, $previousEnd] = $this->timeframeWindow();
            $currentRankings = Cache::remember(
                'charts.rankings.current.' . md5(json_encode([$this->timeframe, $this->genre, optional($start)?->toDateTimeString(), optional($end)?->toDateTimeString()])),
                now()->addMinutes(5),
                fn () => $this->rankingService()->rankedTracks($start, $end, $this->genre)->all()
            );
            $previousRankings = Cache::remember(
                'charts.rankings.previous.' . md5(json_encode([$this->timeframe, $this->genre, optional($previousStart)?->toDateTimeString(), optional($previousEnd)?->toDateTimeString()])),
                now()->addMinutes(5),
                fn () => $this->rankingService()->rankedTracks($previousStart, $previousEnd, $this->genre)->all()
            );
            $movementMap = $this->rankingService()->movementMap($currentRankings, $previousRankings);

            $query = Track::with(['artistProfile.user', 'genre'])
                ->withCount('likedByUsers as likes_count')
                ->where('is_published', true)
                ->when($this->genre, fn (Builder $query) => $query->whereHas('genre', fn (Builder $genreQuery) => $genreQuery->where('slug', $this->genre)));

            if ($start && $end) {
                $query->withCount([
                    'playHistories as chart_plays' => fn (Builder $plays) => $plays->whereBetween('created_at', [$start, $end]),
                ])->orderByDesc('chart_plays');
            } else {
                $query->withCount('playHistories as chart_plays')->orderByDesc('play_count');
            }

            $query->orderByDesc('likes_count')->orderByDesc('downloads_count')->orderByDesc('id');

            /** @var LengthAwarePaginator $tracks */
            $tracks = $query->paginate(30);
            $topTracks = collect($currentRankings)
                ->take(3)
                ->map(fn (array $row) => $row['track'])
                ->filter()
                ->values();
            $stats = [
                'timeframeLabel' => $this->timeframeLabel(),
                'plays' => (int) $tracks->getCollection()->sum(fn (Track $track) => $start && $end ? $track->chart_plays : $track->play_count),
                'likes' => (int) $tracks->getCollection()->sum('likes_count'),
                'downloads' => (int) $tracks->getCollection()->sum('downloads_count'),
            ];
        }

        $heroTrack = $topTracks->first();
        $remainingTopTracks = $topTracks->slice(1)->values();

        return [
            'genres' => $genres,
            'historySnapshots' => $historySnapshots,
            'selectedSnapshot' => $selectedSnapshot,
            'tracks' => $tracks,
            'heroTrack' => $heroTrack,
            'remainingTopTracks' => $remainingTopTracks,
            'movementMap' => $movementMap,
            'stats' => $stats,
        ];
    }

    public function setTimeframe(string $value): void
    {
        if (! in_array($value, ['week', 'month', 'all'], true)) {
            return;
        }

        $this->timeframe = $value;
        $this->snapshot = null;
        $this->resetPage();
    }

    public function setGenre(?string $slug): void
    {
        $this->genre = $slug;
        $this->snapshot = null;
        $this->resetPage();
    }

    public function setSnapshot(?string $periodKey): void
    {
        $this->snapshot = blank($periodKey) ? null : $periodKey;
        $this->genre = null;
        $this->resetPage();
    }

    public function clearSnapshot(): void
    {
        $this->snapshot = null;
        $this->resetPage();
    }

    protected function timeframeWindow(): array
    {
        $now = CarbonImmutable::now();

        return match ($this->timeframe) {
            'month' => [
                $now->startOfMonth(),
                $now,
                $now->subMonthNoOverflow()->startOfMonth(),
                $now->subMonthNoOverflow()->endOfMonth(),
            ],
            'all' => [null, null, $now->subDays(60)->startOfDay(), $now->subDays(30)->endOfDay()],
            default => [
                $now->startOfWeek(),
                $now,
                $now->subWeek()->startOfWeek(),
                $now->subWeek()->endOfWeek(),
            ],
        };
    }

    protected function timeframeLabel(): string
    {
        return match ($this->timeframe) {
            'month' => 'This Month',
            'all' => 'All Time',
            default => 'This Week',
        };
    }

    protected function historySnapshots(): Collection
    {
        if (! in_array($this->timeframe, [ChartSnapshot::PERIOD_WEEK, ChartSnapshot::PERIOD_MONTH], true)) {
            return collect();
        }

        return Cache::remember(
            'charts.snapshots.' . $this->timeframe,
            now()->addMinutes(10),
            fn () => ChartSnapshot::query()
                ->where('period_type', $this->timeframe)
                ->latest('period_start')
                ->take(8)
                ->get()
        );
    }

    protected function selectedSnapshot(Collection $historySnapshots): ?ChartSnapshot
    {
        if (! $this->snapshot || ! in_array($this->timeframe, [ChartSnapshot::PERIOD_WEEK, ChartSnapshot::PERIOD_MONTH], true)) {
            return null;
        }

        /** @var ChartSnapshot|null $snapshot */
        $snapshot = $historySnapshots->firstWhere('period_key', $this->snapshot)
            ?? ChartSnapshot::query()
                ->where('period_type', $this->timeframe)
                ->where('period_key', $this->snapshot)
                ->first();

        if (! $snapshot) {
            $this->snapshot = null;

            return null;
        }

        return $snapshot->load(['entries.track.artistProfile.user', 'entries.track.genre', 'previousSnapshot']);
    }

    protected function paginateSnapshotTracks(ChartSnapshot $snapshot): LengthAwarePaginator
    {
        $entries = $snapshot->entries()
            ->with(['track.artistProfile.user', 'track.genre'])
            ->paginate(30);

        $entries->setCollection(
            $entries->getCollection()
                ->map(fn (ChartSnapshotEntry $entry) => $this->hydrateSnapshotTrack($entry))
                ->filter()
                ->values()
        );

        return $entries;
    }

    protected function hydrateSnapshotTrack(ChartSnapshotEntry $entry): ?Track
    {
        $track = $entry->track;

        if (! $track) {
            return null;
        }

        $track->setAttribute('chart_rank', $entry->rank);
        $track->setAttribute('chart_plays', $entry->plays);
        $track->setAttribute('likes_count', $entry->likes_count);
        $track->setAttribute('downloads_count', $entry->downloads_count);

        return $track;
    }

    protected function snapshotMovementMap(ChartSnapshot $snapshot): array
    {
        return $snapshot->entries->mapWithKeys(fn (ChartSnapshotEntry $entry): array => [
            $entry->track_id => [
                'type' => $entry->movement,
                'label' => $entry->movement_label,
            ],
        ])->all();
    }

    protected function rankingService(): ChartRankingService
    {
        return app(ChartRankingService::class);
    }
};
?>

<div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="glass-card relative overflow-hidden rounded-[2rem] p-6 sm:p-8">
            <div class="absolute inset-y-0 right-0 w-1/3 bg-gradient-to-l from-primary/10 to-transparent"></div>
            <div class="relative z-10 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.28em] text-primary/70">Pulse Monitor</p>
                    <h1 class="text-3xl font-black tracking-tight text-gray-900 sm:text-5xl">Charts that actually move</h1>
                    <p class="mt-3 max-w-2xl text-sm text-gray-500 sm:text-base">Track what is climbing right now with live plays, likes, downloads, and movement against the previous period.</p>
                </div>

                <div class="grid grid-cols-3 gap-3 sm:gap-4">
                    @foreach([
                        ['label' => 'Plays', 'value' => number_format($stats['plays'])],
                        ['label' => 'Likes', 'value' => number_format($stats['likes'])],
                        ['label' => 'Downloads', 'value' => number_format($stats['downloads'])],
                    ] as $metric)
                        <div class="rounded-2xl border border-white/60 bg-white/80 px-4 py-3 text-center shadow-sm backdrop-blur-sm">
                            <p class="text-lg font-black text-gray-900 sm:text-2xl">{{ $metric['value'] }}</p>
                            <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-gray-400">{{ $metric['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @if($heroTrack)
            <section class="grid gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(20rem,1fr)]">
                <article class="glass-card overflow-hidden rounded-[2rem]">
                    <div class="grid gap-0 md:grid-cols-[18rem_minmax(0,1fr)]">
                        <div class="relative min-h-[16rem] bg-gradient-to-br from-primary-100 to-primary-200">
                            @if($heroTrack->getCoverUrl('large'))
                                <img src="{{ $heroTrack->getCoverUrl('large') }}" alt="{{ $heroTrack->cover_alt }}" class="h-full w-full object-cover">
                            @elseif($heroTrack->getCoverUrl())
                                <img src="{{ $heroTrack->getCoverUrl() }}" alt="{{ $heroTrack->cover_alt }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center">
                                    <svg class="h-16 w-16 text-primary-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55A4 4 0 1014 17V7h4V3h-6z"/></svg>
                                </div>
                            @endif
                            <div class="absolute left-4 top-4 inline-flex items-center gap-2 rounded-full bg-black/65 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-white">
                                <span>#1</span>
                                <span>{{ $stats['timeframeLabel'] }}</span>
                            </div>
                        </div>

                        <div class="flex flex-col justify-between gap-5 p-6">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary/70">Now Trending</p>
                                <h2 class="mt-3 text-3xl font-black tracking-tight text-gray-900">{{ $heroTrack->title }}</h2>
                                <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500">
                                    <a href="{{ route('artist.page', $heroTrack->artistProfile) }}" wire:navigate class="font-medium transition hover:text-primary">
                                        {{ $heroTrack->artistProfile?->stage_name ?? 'Unknown Artist' }}
                                    </a>
                                    <span>&middot;</span>
                                    <span>{{ $heroTrack->genre?->name ?? 'Open format' }}</span>
                                </div>

                                <div class="mt-5 flex flex-wrap gap-2">
                                    @php $heroMovement = $movementMap[$heroTrack->id] ?? null; @endphp
                                    @if($heroMovement)
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $heroMovement['type'] === 'up' ? 'bg-emerald-100 text-emerald-700' : ($heroMovement['type'] === 'down' ? 'bg-rose-100 text-rose-700' : ($heroMovement['type'] === 'new' ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-600')) }}">
                                            {{ $heroMovement['label'] }}
                                        </span>
                                    @endif
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">{{ $heroTrack->formatted_duration }}</span>
                                    @if($heroTrack->requires_donation)
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">${{ number_format($heroTrack->donation_amount, 2) }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div class="rounded-2xl bg-gray-50 px-4 py-3">
                                    <p class="text-xl font-black text-gray-900">{{ number_format($timeframe === 'all' ? $heroTrack->play_count : $heroTrack->chart_plays) }}</p>
                                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-400">Plays</p>
                                </div>
                                <div class="rounded-2xl bg-gray-50 px-4 py-3">
                                    <p class="text-xl font-black text-gray-900">{{ number_format($heroTrack->likes_count) }}</p>
                                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-400">Likes</p>
                                </div>
                                <div class="rounded-2xl bg-gray-50 px-4 py-3">
                                    <p class="text-xl font-black text-gray-900">{{ number_format($heroTrack->downloads_count) }}</p>
                                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-400">Downloads</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <button @click="Livewire.dispatch('play-track', { id: {{ $heroTrack->id }} })" class="rounded-full bg-primary px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-600">Play #1</button>
                                <button @click="Livewire.dispatch('queue-track', { id: {{ $heroTrack->id }} })" class="rounded-full border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-700 transition hover:border-primary hover:text-primary">Queue It Up</button>
                                <a href="{{ route('artist.page', $heroTrack->artistProfile) }}" wire:navigate class="rounded-full border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-700 transition hover:border-primary hover:text-primary">View Artist</a>
                            </div>
                        </div>
                    </div>
                </article>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    @foreach($remainingTopTracks as $index => $track)
                        <article class="glass-card flex items-center gap-4 rounded-[1.8rem] p-4">
                            <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200">
                                @if($track->getCoverUrl())
                                    <img src="{{ $track->getCoverUrl() }}" alt="{{ $track->cover_alt }}" class="h-full w-full object-cover">
                                @endif
                                <span class="absolute left-2 top-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-sm font-black text-gray-900 shadow-sm">#{{ $index + 2 }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-base font-semibold text-gray-900">{{ $track->title }}</p>
                                <a href="{{ route('artist.page', $track->artistProfile) }}" wire:navigate class="block truncate text-sm text-gray-500 transition hover:text-primary">
                                    {{ $track->artistProfile?->stage_name ?? 'Unknown Artist' }}
                                </a>
                                <div class="mt-3 flex items-center gap-3 text-xs text-gray-400">
                                    <span>{{ number_format($timeframe === 'all' ? $track->play_count : $track->chart_plays) }} plays</span>
                                    <span>{{ number_format($track->likes_count) }} likes</span>
                                </div>
                            </div>
                            <x-queue-track-button :track="$track" label="+" class="px-2.5 py-1.5 text-[11px]" />
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="sticky top-20 z-20 rounded-[1.5rem] border border-white/60 bg-white/75 p-3 shadow-sm backdrop-blur-xl">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach(['week' => 'This Week', 'month' => 'This Month', 'all' => 'All Time'] as $value => $label)
                        <button wire:click="setTimeframe('{{ $value }}')"
                                class="rounded-full px-4 py-2 text-sm font-semibold transition {{ $timeframe === $value ? 'bg-primary text-white shadow-sm' : 'bg-white text-gray-500 hover:text-gray-900' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button wire:click="setGenre(null)"
                            class="rounded-full px-4 py-2 text-sm font-semibold transition {{ is_null($genre) ? 'bg-gray-900 text-white' : 'bg-white text-gray-500 hover:text-gray-900' }}">
                        All Genres
                    </button>
                    @foreach($genres as $chartGenre)
                        <button wire:click="setGenre('{{ $chartGenre->slug }}')"
                                class="rounded-full px-4 py-2 text-sm font-semibold transition {{ $genre === $chartGenre->slug ? 'bg-gray-900 text-white' : 'bg-white text-gray-500 hover:text-gray-900' }}">
                            {{ $chartGenre->name }}
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        @if($timeframe !== 'all')
            <section class="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(19rem,1fr)]">
                <article class="glass-card overflow-hidden rounded-[2rem] p-6">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="max-w-2xl">
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary/70">{{ $selectedSnapshot ? 'Historical Record' : 'Live Pulse' }}</p>
                            <h2 class="mt-3 text-2xl font-black tracking-tight text-gray-900 sm:text-3xl">
                                {{ $selectedSnapshot?->display_title ?? ($timeframe === 'month' ? 'Live monthly chart' : 'Live weekly chart') }}
                            </h2>
                            <p class="mt-3 text-sm leading-6 text-gray-500">
                                {{ $selectedSnapshot
                                    ? 'This leaderboard is frozen and will not change. Use it whenever you need a locked chart period for admin review, campaigns, or throwback discovery.'
                                    : 'Switch between the live chart and archived weekly or monthly records whenever you need a locked period from the past.' }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-2xl border border-white/60 bg-white/80 px-4 py-3 shadow-sm">
                                <p class="text-lg font-black text-gray-900">{{ number_format($stats['plays']) }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-gray-400">Plays</p>
                            </div>
                            <div class="rounded-2xl border border-white/60 bg-white/80 px-4 py-3 shadow-sm">
                                <p class="text-lg font-black text-gray-900">{{ number_format($stats['likes']) }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-gray-400">Likes</p>
                            </div>
                            <div class="rounded-2xl border border-white/60 bg-white/80 px-4 py-3 shadow-sm">
                                <p class="text-lg font-black text-gray-900">{{ number_format($stats['downloads']) }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-gray-400">Downloads</p>
                            </div>
                            <div class="rounded-2xl border border-white/60 bg-white/80 px-4 py-3 shadow-sm">
                                <p class="text-lg font-black text-gray-900">{{ $selectedSnapshot ? $selectedSnapshot->period_key : 'Live' }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-gray-400">Mode</p>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="glass-card rounded-[2rem] p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary/70">Archive Switcher</p>
                            <h3 class="mt-2 text-lg font-black text-gray-900">Pick a locked {{ $timeframe }}ly chart</h3>
                        </div>
                        @if($selectedSnapshot)
                            <button wire:click="clearSnapshot" class="rounded-full border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-600 transition hover:border-primary hover:text-primary">
                                Return to live
                            </button>
                        @endif
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <button wire:click="clearSnapshot"
                                class="rounded-full px-4 py-2 text-sm font-semibold transition {{ is_null($selectedSnapshot) ? 'bg-primary text-white shadow-sm' : 'bg-white text-gray-500 hover:text-gray-900' }}">
                            Live now
                        </button>

                        @forelse($historySnapshots as $historySnapshot)
                            <button wire:click="setSnapshot('{{ $historySnapshot->period_key }}')"
                                    class="rounded-full px-4 py-2 text-sm font-semibold transition {{ optional($selectedSnapshot)->period_key === $historySnapshot->period_key ? 'bg-gray-900 text-white shadow-sm' : 'bg-white text-gray-500 hover:text-gray-900' }}">
                                {{ $historySnapshot->period_type === 'month' ? $historySnapshot->period_start->format('M Y') : $historySnapshot->period_start->format('M j') }}
                            </button>
                        @empty
                            <p class="rounded-2xl border border-dashed border-white/60 bg-white/70 px-4 py-4 text-sm text-gray-500">
                                No archived {{ $timeframe }}ly charts yet. Snapshot jobs will start filling this rail automatically.
                            </p>
                        @endforelse
                    </div>

                    <div class="mt-5 rounded-[1.5rem] bg-gradient-to-br from-slate-900 via-slate-800 to-slate-700 px-5 py-5 text-white">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-white/60">Why it matters</p>
                        <p class="mt-3 text-sm leading-6 text-white/80">
                            Historical records are immutable after capture, so the rank, movement, and totals you see here remain stable even as new plays keep arriving.
                        </p>
                    </div>
                </article>
            </section>
        @endif

        <section class="glass-card overflow-hidden rounded-[2rem]">
            @if($tracks->isEmpty())
                <div class="px-6 py-20 text-center">
                    <p class="text-lg font-semibold text-gray-800">No chart data yet</p>
                    <p class="mt-2 text-sm text-gray-500">Try another genre or timeframe once more plays start coming in.</p>
                </div>
            @else
                <div class="hidden items-center gap-4 border-b border-white/40 px-6 py-3 text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400 md:flex">
                    <span class="w-16 shrink-0">Rank</span>
                    <span class="w-14 shrink-0"></span>
                    <span class="min-w-0 flex-1">Track</span>
                    <span class="w-24 text-right">Plays</span>
                    <span class="w-20 text-right">Likes</span>
                    <span class="w-24 text-right">Downloads</span>
                    <span class="w-16 shrink-0"></span>
                </div>

                <div class="divide-y divide-white/40">
                    @foreach($tracks as $index => $track)
                        @php
                            $rank = $track->chart_rank ?? (($tracks->currentPage() - 1) * $tracks->perPage() + $index + 1);
                            $movement = $movementMap[$track->id] ?? null;
                            $rankStyles = match (true) {
                                $rank === 1 => 'from-yellow-300 to-amber-500 text-amber-900',
                                $rank === 2 => 'from-slate-200 to-slate-400 text-slate-700',
                                $rank === 3 => 'from-orange-200 to-orange-400 text-orange-800',
                                default => 'from-gray-100 to-gray-200 text-gray-500',
                            };
                        @endphp
                        <article class="group flex flex-col gap-4 px-4 py-4 transition hover:bg-white/60 sm:px-6 md:flex-row md:items-center">
                            <div class="flex items-center gap-4 md:w-16 md:shrink-0 md:flex-col md:items-start md:gap-2">
                                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br {{ $rankStyles }} text-sm font-black shadow-sm">
                                    {{ $rank }}
                                </span>
                                @if($movement)
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $movement['type'] === 'up' ? 'bg-emerald-100 text-emerald-700' : ($movement['type'] === 'down' ? 'bg-rose-100 text-rose-700' : ($movement['type'] === 'new' ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-600')) }}">
                                        {{ $movement['label'] }}
                                    </span>
                                @endif
                            </div>

                            <button @click="Livewire.dispatch('play-track', { id: {{ $track->id }} })"
                                    class="relative h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200 group/play">
                                @if($track->getCoverUrl())
                                    <img src="{{ $track->getCoverUrl() }}" alt="{{ $track->cover_alt }}" class="h-full w-full object-cover">
                                @endif
                                <div class="absolute inset-0 flex items-center justify-center bg-black/35 opacity-0 transition group-hover/play:opacity-100">
                                    <svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                </div>
                            </button>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('track.show', $track->slug) }}" wire:navigate class="block truncate text-base font-semibold text-gray-900 transition hover:text-primary">{{ $track->title }}</a>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500">
                                            <a href="{{ route('artist.page', $track->artistProfile) }}" wire:navigate class="truncate transition hover:text-primary">
                                                {{ $track->artistProfile?->stage_name ?? 'Unknown Artist' }}
                                            </a>
                                            @if($track->genre)
                                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500">{{ $track->genre->name }}</span>
                                            @endif
                                            <x-track-duration :track="$track" class="text-gray-400" />
                                        </div>
                                    </div>

                                    <div class="hidden items-center gap-2 md:flex">
                                        @livewire('like-button', ['trackId' => $track->id], key('chart-like-'.$track->id))
                                        <button @click="Livewire.dispatch('queue-track', { id: {{ $track->id }} })"
                                                class="rounded-full border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-500 transition hover:border-primary hover:text-primary">
                                            Queue
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-3 gap-2 sm:max-w-md">
                                    <div class="rounded-2xl bg-gray-50 px-3 py-2">
                                        <p class="text-sm font-black text-gray-900">{{ number_format($timeframe === 'all' ? $track->play_count : $track->chart_plays) }}</p>
                                        <p class="text-[11px] uppercase tracking-[0.18em] text-gray-400">Plays</p>
                                    </div>
                                    <div class="rounded-2xl bg-gray-50 px-3 py-2">
                                        <p class="text-sm font-black text-gray-900">{{ number_format($track->likes_count) }}</p>
                                        <p class="text-[11px] uppercase tracking-[0.18em] text-gray-400">Likes</p>
                                    </div>
                                    <div class="rounded-2xl bg-gray-50 px-3 py-2">
                                        <p class="text-sm font-black text-gray-900">{{ number_format($track->downloads_count) }}</p>
                                        <p class="text-[11px] uppercase tracking-[0.18em] text-gray-400">Downloads</p>
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center gap-2 md:hidden">
                                    @livewire('like-button', ['trackId' => $track->id], key('chart-like-mobile-'.$track->id))
                                    <button @click="Livewire.dispatch('queue-track', { id: {{ $track->id }} })"
                                            class="rounded-full border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-500 transition hover:border-primary hover:text-primary">
                                        Queue
                                    </button>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if($tracks->hasPages())
                    <div class="border-t border-white/40 px-6 py-4">{{ $tracks->links() }}</div>
                @endif
            @endif
        </section>
    </div>
</div>
