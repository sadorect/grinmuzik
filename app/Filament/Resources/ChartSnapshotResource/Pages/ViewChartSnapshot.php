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
                ->description('These chart archives are immutable once written, so the leaderboard stays fixed for historical lookups.')
                ->schema([
                    TextEntry::make('display_title')
                        ->label('Chart window'),
                    TextEntry::make('period_type')
                        ->label('Period type')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => ChartSnapshot::periodTypeOptions()[$state] ?? ucfirst($state))
                        ->color(fn (string $state): string => $state === ChartSnapshot::PERIOD_MONTH ? 'warning' : 'info'),
                    TextEntry::make('date_range_label')
                        ->label('Range'),
                    TextEntry::make('period_key')
                        ->label('Archive key')
                        ->badge(),
                    TextEntry::make('generated_at')
                        ->label('Archived at')
                        ->dateTime('d M Y H:i'),
                    TextEntry::make('previousSnapshot.display_title')
                        ->label('Compared against')
                        ->default('No earlier archive'),
                ])
                ->columns(2),
            Section::make('Frozen Totals')
                ->schema([
                    TextEntry::make('total_tracks')
                        ->label('Ranked tracks')
                        ->numeric(),
                    TextEntry::make('total_plays')
                        ->label('Plays')
                        ->numeric(),
                    TextEntry::make('total_likes')
                        ->label('Likes')
                        ->numeric(),
                    TextEntry::make('total_downloads')
                        ->label('Downloads')
                        ->numeric(),
                ])
                ->columns(4),
            Section::make('Leaderboard')
                ->description('The top archived tracks for this period, including their locked movement versus the prior snapshot.')
                ->schema([
                    TextEntry::make('leaderboard')
                        ->hiddenLabel()
                        ->state(fn (ChartSnapshot $record): HtmlString => static::leaderboardMarkup($record))
                        ->html()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    protected static function leaderboardMarkup(ChartSnapshot $record): HtmlString
    {
        $record->loadMissing('entries.track.artistProfile.user', 'entries.track.genre');

        if ($record->entries->isEmpty()) {
            return new HtmlString(
                '<div class="rounded-3xl border border-dashed border-slate-200 bg-white/80 px-6 py-8 text-sm text-slate-500">No ranked tracks were captured for this chart window.</div>'
            );
        }

        $cards = $record->entries
            ->take(10)
            ->map(function ($entry): string {
                $track = $entry->track;
                $title = e($track?->title ?? 'Unknown track');
                $artist = e($track?->artistProfile?->stage_name ?? 'Unknown artist');
                $genre = e($track?->genre?->name ?? 'Open format');
                $movementClass = match ($entry->movement) {
                    'up' => 'bg-emerald-100 text-emerald-700',
                    'down' => 'bg-rose-100 text-rose-700',
                    'steady' => 'bg-slate-100 text-slate-600',
                    default => 'bg-primary/10 text-primary',
                };
                $movement = e($entry->movement_label);
                $plays = number_format($entry->plays);
                $likes = number_format($entry->likes_count);
                $downloads = number_format($entry->downloads_count);

                return <<<HTML
                    <article class="rounded-[1.75rem] border border-slate-200 bg-white/90 p-4 shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-start gap-4">
                                <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-900 to-slate-600 text-sm font-black text-white shadow-sm">#{$entry->rank}</div>
                                <div>
                                    <p class="text-base font-semibold text-slate-900">{$title}</p>
                                    <p class="mt-1 text-sm text-slate-500">{$artist} · {$genre}</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {$movementClass}">{$movement}</span>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{$plays} plays</span>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{$likes} likes</span>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{$downloads} downloads</span>
                            </div>
                        </div>
                    </article>
                HTML;
            })
            ->implode('');

        return new HtmlString('<div class="space-y-3">' . $cards . '</div>');
    }
}