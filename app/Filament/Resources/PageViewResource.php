<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageViewResource\Pages;
use App\Models\PageView;
use App\Support\AdminPanelAccess;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PageViewResource extends Resource
{
    protected static ?string $model = PageView::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map';

    protected static \UnitEnum|string|null $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Page Views';

    protected static ?string $modelLabel = 'page view';

    protected static ?string $pluralModelLabel = 'page views';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return AdminPanelAccess::allows('view analytics');
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Viewed')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('path')
                    ->searchable()
                    ->limit(45)
                    ->wrap(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->placeholder('Guest'),
                Tables\Columns\TextColumn::make('location_label')
                    ->label('Location')
                    ->wrap()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $nested) use ($search): void {
                            $nested->where('country', 'like', "%{$search}%")
                                ->orWhere('region', 'like', "%{$search}%")
                                ->orWhere('city', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('session_id')
                    ->label('Session')
                    ->limit(16)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('timezone')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('coordinates')
                    ->label('Coordinates')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('location_source')
                    ->label('Source')
                    ->colors([
                        'gray' => 'private',
                        'success' => 'ip_geolocation',
                        'info' => 'cache',
                    ])
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'private' => 'Private network',
                        'ip_geolocation' => 'Geo lookup',
                        'cache' => 'Cached',
                        default => 'Pending',
                    }),
                Tables\Columns\TextColumn::make('user_agent')
                    ->label('User agent')
                    ->limit(70)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $query): Builder => $query->today()),
                Tables\Filters\SelectFilter::make('country')
                    ->options(fn (): array => PageView::query()
                        ->whereNotNull('country')
                        ->orderBy('country')
                        ->distinct()
                        ->pluck('country', 'country')
                        ->all()),
                Tables\Filters\TernaryFilter::make('user_id')
                    ->label('Authenticated user')
                    ->placeholder('All visits')
                    ->trueLabel('Signed-in visits')
                    ->falseLabel('Guest visits')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('user_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('user_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
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
            'index' => Pages\ListPageViews::route('/'),
            'view' => Pages\ViewPageView::route('/{record}'),
        ];
    }
}
