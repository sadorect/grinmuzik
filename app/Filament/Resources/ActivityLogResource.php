<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use App\Models\ArtistProfile;
use App\Support\AdminPanelAccess;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
// NB: Schema is not imported — Filament\Schemas\Schema already owns that name
// in this file, so the facade is referenced by FQCN in tableExists().

/**
 * Read-only view of the audit trail.
 *
 * Every mutating action is disabled: an audit log an admin can edit or delete
 * is not an audit log. Rows are only ever written by AuditLogger.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static \UnitEnum|string|null $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?string $modelLabel = 'activity log entry';

    protected static ?string $pluralModelLabel = 'audit log';

    protected static ?int $navigationSort = 0;

    /**
     * Hidden entirely until the table exists.
     *
     * Filament discovers resources from the filesystem, so this class is live
     * the moment it is deployed — before the migration has run. Without this
     * guard the sidebar links to a table that does not exist yet, and the
     * first click 500s the panel.
     */
    public static function canViewAny(): bool
    {
        return static::tableExists() && AdminPanelAccess::allows('view audit log');
    }

    /**
     * Cached per request: this is consulted on every panel render, and an
     * uncached hasTable() would add a metadata query to each one.
     */
    protected static function tableExists(): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        try {
            return $exists = \Illuminate\Support\Facades\Schema::hasTable('activity_logs');
        } catch (\Throwable) {
            return $exists = false;
        }
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

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

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function getEloquentQuery(): Builder
    {
        // actor and artistProfile are rendered on every row.
        return parent::getEloquentQuery()->with(['actor', 'artistProfile']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y H:i:s')
                    ->description(fn (ActivityLog $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (ActivityLog $record): string => $record->eventLabel())
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'deleted'),
                        str_contains($state, 'deactivated'),
                        str_contains($state, 'unpublished') => 'danger',
                        str_contains($state, 'uploaded'),
                        str_contains($state, 'published'),
                        str_contains($state, 'created'),
                        str_contains($state, 'restored') => 'success',
                        str_contains($state, 'login') => 'gray',
                        default => 'warning',
                    })
                    ->searchable(),

                // Frozen at write time, so this still reads correctly for
                // subjects that no longer exist.
                Tables\Columns\TextColumn::make('subject_label')
                    ->label('Subject')
                    ->searchable()
                    ->placeholder('—')
                    ->limit(40)
                    ->wrap(),

                Tables\Columns\TextColumn::make('actor_label')
                    ->label('Who')
                    ->formatStateUsing(fn (ActivityLog $record): string => $record->actorName())
                    ->description(fn (ActivityLog $record): ?string => $record->actor?->email)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $nested) use ($search): void {
                            $nested->where('actor_label', 'like', "%{$search}%")
                                ->orWhereHas('actor', fn (Builder $actor) => $actor
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%"));
                        });
                    }),

                Tables\Columns\TextColumn::make('actor_type')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ActivityLog::actorTypeLabels()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'admin' => 'danger',
                        'artist' => 'info',
                        'system' => 'gray',
                        default => 'warning',
                    }),

                Tables\Columns\TextColumn::make('artistProfile.stage_name')
                    ->label('Artist')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user_agent')
                    ->label('User agent')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label('Action')
                    ->multiple()
                    ->options(ActivityLog::eventLabels()),

                Tables\Filters\SelectFilter::make('actor_type')
                    ->label('Role')
                    ->multiple()
                    ->options(ActivityLog::actorTypeLabels()),

                Tables\Filters\SelectFilter::make('artist_profile_id')
                    ->label('Artist')
                    ->searchable()
                    ->options(fn (): array => ArtistProfile::query()
                        ->withTrashed()
                        ->orderBy('stage_name')
                        ->pluck('stage_name', 'id')
                        ->all()),

                Tables\Filters\SelectFilter::make('actor_id')
                    ->label('Performed by')
                    ->relationship('actor', 'name')
                    ->searchable()
                    ->preload(),

                // The shortcut for "something disappeared, what happened".
                Tables\Filters\Filter::make('destructive')
                    ->label('Deletions & takedowns only')
                    ->query(fn (Builder $query): Builder => $query->destructive()),

                Tables\Filters\Filter::make('created_at')
                    ->schema([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'From ' . $data['from'];
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until ' . $data['until'];
                        }

                        return $indicators;
                    }),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
