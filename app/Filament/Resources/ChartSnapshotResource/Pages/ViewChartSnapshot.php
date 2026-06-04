<?php

namespace App\Filament\Resources\ChartSnapshotResource\Pages;

use App\Filament\Resources\ChartSnapshotResource;
use App\Models\ChartSnapshot;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewChartSnapshot extends ViewRecord
{
    protected static string $resource = ChartSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ChartSnapshotResource::snapshotTriggerAction(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Snapshot Overview')
                ->schema([
                    TextEntry::make('overview_card')
                        ->hiddenLabel()
                        ->state(fn (ChartSnapshot $record): HtmlString => static::overviewCardMarkup($record))
                        ->html()
                        ->columnSpanFull(),
                ]),
            Section::make('Leaderboard')
                ->description('Podium + full ranked list. Records are frozen — plays, movement, and rank reflect the moment this snapshot was generated.')
                ->schema([
                    TextEntry::make('leaderboard')
                        ->hiddenLabel()
                        ->state(fn (ChartSnapshot $record): HtmlString => static::leaderboardMarkup($record))
                        ->html()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    protected static function overviewCardMarkup(ChartSnapshot $record): HtmlString
    {
        $isPeriodMonth = $record->period_type === ChartSnapshot::PERIOD_MONTH;
        $periodLabel   = $isPeriodMonth ? 'Monthly' : 'Weekly';
        $periodColor   = $isPeriodMonth
            ? 'from-amber-400 to-orange-500 text-amber-900'
            : 'from-blue-400 to-indigo-500 text-white';
        $badgeBg       = $isPeriodMonth ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800';
        $title         = e($record->display_title);
        $range         = e($record->date_range_label);
        $key           = e($record->period_key);
        $generatedAt   = $record->generated_at?->format('d M Y, H:i') ?? '—';
        $prevTitle     = e($record->previousSnapshot?->display_title ?? 'No earlier archive');
        $totalTracks   = number_format((int) $record->total_tracks);
        $totalPlays    = number_format((int) $record->total_plays);
        $totalLikes    = number_format((int) $record->total_likes);
        $totalDowns    = number_format((int) $record->total_downloads);

        return new HtmlString(<<<HTML
            <div class="overflow-hidden rounded-3xl border border-slate-200 shadow-sm">

                <!-- Header band -->
                <div class="bg-gradient-to-r {$periodColor} px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <span class="inline-flex items-center rounded-full {$badgeBg} px-3 py-0.5 text-xs font-bold uppercase tracking-widest mb-2">{$periodLabel}</span>
                        <h3 class="text-2xl font-black leading-tight">{$title}</h3>
                        <p class="mt-1 text-sm opacity-80">{$range}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-xs opacity-60 uppercase tracking-widest">Archive key</p>
                        <p class="mt-0.5 font-mono text-lg font-black">{$key}</p>
                        <p class="mt-1 text-xs opacity-70">Generated {$generatedAt}</p>
                    </div>
                </div>

                <!-- Stat cards row -->
                <div class="grid grid-cols-2 divide-x divide-y divide-slate-100 sm:grid-cols-4 bg-white">
                    <div class="px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Ranked tracks</p>
                        <p class="mt-1 text-3xl font-black text-slate-900">{$totalTracks}</p>
                    </div>
                    <div class="px-5 py-4 bg-blue-50/60">
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-500">Total plays</p>
                        <p class="mt-1 text-3xl font-black text-blue-700">{$totalPlays}</p>
                    </div>
                    <div class="px-5 py-4 bg-rose-50/60">
                        <p class="text-xs font-semibold uppercase tracking-widest text-rose-400">Total likes</p>
                        <p class="mt-1 text-3xl font-black text-rose-600">{$totalLikes}</p>
                    </div>
                    <div class="px-5 py-4 bg-emerald-50/60">
                        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-500">Downloads</p>
                        <p class="mt-1 text-3xl font-black text-emerald-700">{$totalDowns}</p>
                    </div>
                </div>

                <!-- Footer meta -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-6 py-3">
                    <p class="text-xs text-slate-500">
                        <span class="font-semibold text-slate-700">Compared against:</span> {$prevTitle}
                    </p>
                    <p class="text-xs text-slate-400 italic">immutable once written — this record will not change</p>
                </div>

            </div>
        HTML);
    }

    protected static function leaderboardMarkup(ChartSnapshot $record): HtmlString
    {
        $record->loadMissing('entries.track.artistProfile.user', 'entries.track.genre');

        if ($record->entries->isEmpty()) {
            return new HtmlString(
                '<div class="rounded-3xl border border-dashed border-slate-200 bg-gradient-to-br from-slate-50 to-white px-8 py-12 text-center text-sm text-slate-400">
                    <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl">🎵</div>
                    No ranked tracks were captured for this chart window.
                </div>'
            );
        }

        $cards = $record->entries
            ->take(100)
            ->map(function ($entry) use ($record): string {
                $track       = $entry->track;
                $title       = e($track?->title ?? 'Unknown track');
                $artist      = e($track?->artistProfile?->stage_name ?? 'Unknown artist');
                $genre       = e($track?->genre?->name ?? '');
                $rank        = (int) $entry->rank;
                $plays       = number_format($entry->plays);
                $likes       = number_format($entry->likes_count);
                $downloads   = number_format($entry->downloads_count);
                $movement    = e($entry->movement_label);

                // ── rank medal / gradient ────────────────────────────────────
                [$rankGradient, $rankText, $rankRing] = match (true) {
                    $rank === 1 => ['from-yellow-300 via-amber-400 to-orange-400', 'text-amber-900', 'ring-2 ring-amber-300'],
                    $rank === 2 => ['from-slate-300 via-slate-400 to-slate-500',   'text-slate-100',  'ring-2 ring-slate-300'],
                    $rank === 3 => ['from-orange-300 via-orange-400 to-amber-500', 'text-orange-900', 'ring-2 ring-orange-300'],
                    $rank <= 10 => ['from-primary/80 via-primary to-indigo-600',   'text-white',      ''],
                    default     => ['from-slate-700 via-slate-800 to-slate-900',   'text-slate-200',  ''],
                };

                // ── card background: top 3 get a subtle tinted wash ─────────
                $cardBg = match (true) {
                    $rank === 1 => 'bg-gradient-to-r from-amber-50 via-yellow-50 to-white border-amber-200',
                    $rank === 2 => 'bg-gradient-to-r from-slate-50 via-slate-50 to-white border-slate-200',
                    $rank === 3 => 'bg-gradient-to-r from-orange-50 via-amber-50 to-white border-orange-200',
                    $rank <= 10 => 'bg-gradient-to-r from-indigo-50/60 to-white border-indigo-100',
                    default     => 'bg-white border-slate-100',
                };

                // ── movement pill ────────────────────────────────────────────
                [$movementBg, $movementIcon] = match ($entry->movement) {
                    'up'     => ['bg-emerald-100 text-emerald-700 border border-emerald-200', '▲'],
                    'down'   => ['bg-rose-100 text-rose-700 border border-rose-200',          '▼'],
                    'steady' => ['bg-slate-100 text-slate-600 border border-slate-200',        '='],
                    default  => ['bg-violet-100 text-violet-700 border border-violet-200',     '★'],
                };

                // ── stat pills ───────────────────────────────────────────────
                $statPills = "
                    <span class=\"inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 border border-blue-100\">
                        <svg class=\"h-3 w-3\" fill=\"currentColor\" viewBox=\"0 0 24 24\"><path d=\"M8 5v14l11-7z\"/></svg>
                        {$plays}
                    </span>
                    <span class=\"inline-flex items-center gap-1 rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-600 border border-rose-100\">
                        <svg class=\"h-3 w-3\" fill=\"currentColor\" viewBox=\"0 0 24 24\"><path d=\"M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z\"/></svg>
                        {$likes}
                    </span>
                    <span class=\"inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 border border-emerald-100\">
                        <svg class=\"h-3 w-3\" fill=\"currentColor\" viewBox=\"0 0 24 24\"><path d=\"M5 20h14v-2H5v2zm7-18l-7 7h4v6h6v-6h4l-7-7z\"/></svg>
                        {$downloads}
                    </span>
                ";

                $genreTag = $genre
                    ? "<span class=\"rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500\">{$genre}</span>"
                    : '';

                $prevRankNote = '';
                if ($entry->previous_rank && $entry->previous_rank !== $entry->rank) {
                    $prev = (int) $entry->previous_rank;
                    $prevRankNote = "<span class=\"text-[10px] text-slate-400\">was #{$prev}</span>";
                }

                // ── top 3 get a star / trophy accent ────────────────────────
                $crownAccent = match ($rank) {
                    1 => '<span class="absolute -top-2 -right-2 text-xl leading-none" title="Chart topper">🏆</span>',
                    2 => '<span class="absolute -top-2 -right-2 text-lg leading-none" title="Runner-up">🥈</span>',
                    3 => '<span class="absolute -top-2 -right-2 text-lg leading-none" title="Third place">🥉</span>',
                    default => '',
                };

                return <<<HTML
                    <article class="relative overflow-hidden rounded-2xl border {$cardBg} p-4 shadow-sm transition-shadow hover:shadow-md">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                            <!-- Rank + title block -->
                            <div class="flex items-center gap-4 min-w-0">
                                <div class="relative shrink-0">
                                    <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br {$rankGradient} {$rankText} {$rankRing} text-sm font-black shadow-md">
                                        #{$rank}
                                    </div>
                                    {$crownAccent}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-slate-900">{$title}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <span class="text-xs text-slate-500">{$artist}</span>
                                        {$genreTag}
                                        {$prevRankNote}
                                    </div>
                                </div>
                            </div>

                            <!-- Stats + movement -->
                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <span class="inline-flex items-center gap-1 rounded-full {$movementBg} px-2.5 py-1 text-xs font-bold">
                                    {$movementIcon} {$movement}
                                </span>
                                {$statPills}
                            </div>

                        </div>
                    </article>
                HTML;
            })
            ->implode('');

        // ── split top 3 hero vs rest ─────────────────────────────────────────
        $topEntries  = $record->entries->take(3);
        $restEntries = $record->entries->skip(3)->take(97);

        $heroCards = $topEntries->map(function ($entry) use ($record): string {
            $track      = $entry->track;
            $title      = e($track?->title ?? 'Unknown track');
            $artist     = e($track?->artistProfile?->stage_name ?? 'Unknown artist');
            $genre      = e($track?->genre?->name ?? '');
            $rank       = (int) $entry->rank;
            $plays      = number_format($entry->plays);
            $likes      = number_format($entry->likes_count);
            $downloads  = number_format($entry->downloads_count);
            $movement   = e($entry->movement_label);

            [$heroGradient, $heroText, $heroBorder, $crown] = match ($rank) {
                1 => ['from-amber-400 via-yellow-400 to-orange-400',  'text-amber-900', 'border-amber-300', '🏆'],
                2 => ['from-slate-300 via-slate-400 to-zinc-400',     'text-slate-800', 'border-slate-300', '🥈'],
                3 => ['from-orange-300 via-orange-400 to-amber-400',  'text-orange-900','border-orange-300','🥉'],
                default => ['from-slate-700 to-slate-900', 'text-white', 'border-slate-200', ''],
            };

            [$movementBg, $movementIcon] = match ($entry->movement) {
                'up'     => ['bg-emerald-500/20 text-emerald-900 border border-emerald-300', '▲'],
                'down'   => ['bg-rose-500/20 text-rose-900 border border-rose-300',          '▼'],
                'steady' => ['bg-white/40 text-slate-700 border border-white/60',            '='],
                default  => ['bg-violet-500/20 text-violet-900 border border-violet-300',    '★'],
            };

            $genreTag = $genre
                ? "<span class=\"rounded-full bg-black/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide\">{$genre}</span>"
                : '';

            $prevRankNote = '';
            if ($entry->previous_rank && $entry->previous_rank !== $entry->rank) {
                $prev = (int) $entry->previous_rank;
                $prevRankNote = "<span class=\"text-[10px] opacity-70\">was #{$prev}</span>";
            }

            return <<<HTML
                <article class="relative overflow-hidden rounded-3xl bg-gradient-to-br {$heroGradient} border {$heroBorder} p-6 shadow-lg">
                    <div class="absolute right-5 top-4 text-4xl opacity-70">{$crown}</div>
                    <div class="relative">
                        <p class="text-5xl font-black {$heroText} opacity-20 leading-none mb-3">#{$rank}</p>
                        <p class="text-lg font-black {$heroText} leading-snug">{$title}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 {$heroText} opacity-80">
                            <span class="text-sm font-medium">{$artist}</span>
                            {$genreTag}
                            {$prevRankNote}
                        </div>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full {$movementBg} px-2.5 py-1 text-xs font-bold">
                                {$movementIcon} {$movement}
                            </span>
                            <span class="rounded-full bg-black/10 px-3 py-1 text-xs font-semibold {$heroText}">▶ {$plays} plays</span>
                            <span class="rounded-full bg-black/10 px-3 py-1 text-xs font-semibold {$heroText}">♥ {$likes} likes</span>
                            <span class="rounded-full bg-black/10 px-3 py-1 text-xs font-semibold {$heroText}">↓ {$downloads}</span>
                        </div>
                    </div>
                </article>
            HTML;
        })->implode('');

        $restCards = $restEntries->map(function ($entry): string {
            $track      = $entry->track;
            $title      = e($track?->title ?? 'Unknown track');
            $artist     = e($track?->artistProfile?->stage_name ?? 'Unknown artist');
            $genre      = e($track?->genre?->name ?? '');
            $rank       = (int) $entry->rank;
            $plays      = number_format($entry->plays);
            $likes      = number_format($entry->likes_count);
            $downloads  = number_format($entry->downloads_count);
            $movement   = e($entry->movement_label);

            $rankColor = $rank <= 10
                ? 'bg-gradient-to-br from-primary/80 to-indigo-600 text-white'
                : 'bg-gradient-to-br from-slate-600 to-slate-800 text-slate-200';

            [$movementBg, $movementIcon] = match ($entry->movement) {
                'up'     => ['bg-emerald-100 text-emerald-700 border border-emerald-200', '▲'],
                'down'   => ['bg-rose-100 text-rose-700 border border-rose-200',          '▼'],
                'steady' => ['bg-slate-100 text-slate-600 border border-slate-200',        '='],
                default  => ['bg-violet-100 text-violet-700 border border-violet-200',     '★'],
            };

            $genreTag = $genre
                ? "<span class=\"rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500\">{$genre}</span>"
                : '';

            $prevRankNote = '';
            if ($entry->previous_rank && $entry->previous_rank !== $entry->rank) {
                $prev = (int) $entry->previous_rank;
                $prevRankNote = "<span class=\"text-[10px] text-slate-400\">was #{$prev}</span>";
            }

            $cardBg = $rank <= 10
                ? 'bg-gradient-to-r from-indigo-50/60 to-white border-indigo-100'
                : 'bg-white border-slate-100';

            return <<<HTML
                <article class="flex items-center gap-4 rounded-2xl border {$cardBg} px-4 py-3 shadow-sm transition-shadow hover:shadow-md">
                    <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {$rankColor} text-xs font-black shadow-sm">
                        #{$rank}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-900">{$title}</p>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                            <span class="text-xs text-slate-500">{$artist}</span>
                            {$genreTag}
                            {$prevRankNote}
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                        <span class="inline-flex items-center gap-0.5 rounded-full {$movementBg} px-2 py-0.5 text-[10px] font-bold">{$movementIcon} {$movement}</span>
                        <span class="rounded-full bg-blue-50 border border-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700">▶ {$plays}</span>
                        <span class="rounded-full bg-rose-50 border border-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-600">♥ {$likes}</span>
                        <span class="rounded-full bg-emerald-50 border border-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">↓ {$downloads}</span>
                    </div>
                </article>
            HTML;
        })->implode('');

        $restSection = $restCards
            ? '<div class="mt-6 space-y-2">' . $restCards . '</div>'
            : '';

        $html = <<<HTML
            <div class="space-y-4">
                <!-- Podium: top 3 -->
                <div class="grid gap-4 sm:grid-cols-3">
                    {$heroCards}
                </div>
                <!-- Rest of chart -->
                {$restSection}
            </div>
        HTML;

        return new HtmlString($html);
    }
}