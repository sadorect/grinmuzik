<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChartSnapshotResource\Pages;
use App\Filament\Resources\Concerns\HasAdminResourcePermissions;
use App\Models\ChartSnapshot;
use App\Support\Charts\ChartSnapshotService;
use Carbon\CarbonImmutable;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ChartSnapshotResource extends Resource
{
    use HasAdminResourcePermissions;

    protected static ?string $model = ChartSnapshot::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    protected static \UnitEnum|string|null $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Chart History';

    protected static ?string $modelLabel = 'chart snapshot';

    protected static ?string $pluralModelLabel = 'chart history';

    protected static ?int $navigationSort = 3;

    protected static array|string|null $viewAnyPermissions = 'view analytics';

    protected static array|string|null $viewPermissions = 'view analytics';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['entries.track.artistProfile.user']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('period_start', 'desc')
            ->recordUrl(fn (ChartSnapshot $record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('display_title')
                    ->label('Snapshot')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('period_key', 'like', "%{$search}%")
                            ->orWhere('period_type', 'like', "%{$search}%");
                    })
                    ->description(fn (ChartSnapshot $record): string => $record->date_range_label)
                    ->wrap(),
                Tables\Columns\TextColumn::make('period_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ChartSnapshot::periodTypeOptions()[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => $state === ChartSnapshot::PERIOD_MONTH ? 'warning' : 'info'),
                Tables\Columns\TextColumn::make('headline_track')
                    ->label('Peak Track')
                    ->state(fn (ChartSnapshot $record): string => $record->entries->first()?->track?->title ?? 'No chart data')
                    ->description(fn (ChartSnapshot $record): string => $record->entries->first()?->track?->artistProfile?->stage_name ?? 'Awaiting chart data')
                    ->wrap(),
                Tables\Columns\TextColumn::make('total_tracks')
                    ->label('Ranked')
                    ->numeric()
                    ->badge(),
                Tables\Columns\TextColumn::make('total_plays')
                    ->label('Plays')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_likes')
                    ->label('Likes')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_downloads')
                    ->label('Downloads')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Archived')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('period_type')
                    ->label('Period type')
                    ->options(ChartSnapshot::periodTypeOptions()),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function snapshotTriggerAction(): Actions\Action
    {
        return Actions\Action::make('captureSnapshot')
            ->label('Capture Snapshot')
            ->icon('heroicon-o-bolt')
            ->color('primary')
            ->modalHeading('Capture immutable chart snapshot')
            ->modalDescription('Generate a one-off weekly or monthly chart archive from the admin panel. Existing periods remain locked and will be skipped.')
            ->modalWidth('lg')
            ->schema([
                Forms\Components\Select::make('period')
                    ->label('Snapshot period')
                    ->options([
                        'week' => 'Weekly archive',
                        'month' => 'Monthly archive',
                        'all' => 'Weekly and monthly archive',
                    ])
                    ->default('all')
                    ->required()
                    ->native(false),
                Forms\Components\DatePicker::make('date')
                    ->label('Reference date')
                    ->helperText('Leave blank to archive the most recently completed period. Set a date to archive the period containing that date instead.'),
            ])
            ->action(function (array $data): void {
                $summary = static::captureSnapshotsFromAdmin($data);

                Notification::make()
                    ->title($summary['title'])
                    ->body($summary['body'])
                    ->success()
                    ->send();
            });
    }

    public static function captureSnapshotsFromAdmin(array $data): array
    {
        /** @var ChartSnapshotService $snapshotService */
        $snapshotService = app(ChartSnapshotService::class);

        $periodSelection = strtolower((string) ($data['period'] ?? 'all'));
        $periods = $periodSelection === 'all' ? [ChartSnapshot::PERIOD_WEEK, ChartSnapshot::PERIOD_MONTH] : [$periodSelection];
        $referenceDate = blank($data['date'] ?? null) ? null : CarbonImmutable::parse((string) $data['date']);
        $exact = $referenceDate !== null;
        $created = [];
        $skipped = [];

        foreach ($periods as $period) {
            $normalizedPeriod = $snapshotService->normalizePeriodType($period);
            $existing = $snapshotService->findSnapshot($normalizedPeriod, $referenceDate, $exact);

            if ($existing) {
                $skipped[] = $existing->display_title;
                continue;
            }

            $created[] = $snapshotService
                ->createSnapshot($normalizedPeriod, $referenceDate, $exact)
                ->display_title;
        }

        foreach ([ChartSnapshot::PERIOD_WEEK, ChartSnapshot::PERIOD_MONTH] as $cachePeriod) {
            cache()->forget('charts.snapshots.' . $cachePeriod);
        }

        if ($created === [] && $skipped === []) {
            throw new InvalidArgumentException('No valid snapshot period was selected.');
        }

        $title = $created !== [] ? 'Chart snapshot captured' : 'Chart snapshot skipped';
        $parts = [];

        if ($created !== []) {
            $parts[] = 'Created: ' . implode(', ', $created) . '.';
        }

        if ($skipped !== []) {
            $parts[] = 'Skipped existing immutable records: ' . implode(', ', $skipped) . '.';
        }

        return [
            'title' => $title,
            'body' => implode(' ', $parts),
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChartSnapshots::route('/'),
            'view' => Pages\ViewChartSnapshot::route('/{record}'),
        ];
    }
}