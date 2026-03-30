<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasAdminResourcePermissions;
use App\Filament\Resources\ArtistProfileResource\Pages;
use App\Models\ArtistProfile;
use App\Support\AdminPanelAccess;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArtistProfileResource extends Resource
{
    use HasAdminResourcePermissions;

    protected static ?string $model = ArtistProfile::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-musical-note';

    protected static \UnitEnum|string|null $navigationGroup = 'Music Catalog';

    protected static array|string|null $viewAnyPermissions = [
        'view artists',
        'approve artists',
        'reject artists',
        'manage artists',
    ];

    protected static array|string|null $viewPermissions = [
        'view artists',
        'approve artists',
        'reject artists',
        'manage artists',
    ];

    protected static array|string|null $createPermissions = 'manage artists';

    protected static array|string|null $editPermissions = 'manage artists';

    protected static array|string|null $deletePermissions = 'manage artists';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Artist Info')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('stage_name')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('slug')
                        ->maxLength(100)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Textarea::make('bio')
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
            Section::make('Social Links')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('website')->url(),
                    Forms\Components\TextInput::make('twitter')->maxLength(200),
                    Forms\Components\TextInput::make('instagram')->maxLength(200),
                    Forms\Components\TextInput::make('youtube')->maxLength(200),
                    Forms\Components\TextInput::make('spotify')->maxLength(200),
                ]),
            Section::make('Status')
                ->columns(4)
                ->schema([
                    Forms\Components\Toggle::make('is_verified')->label('Verified'),
                    Forms\Components\Toggle::make('is_approved')->label('Approved'),
                    Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
                    Forms\Components\Toggle::make('is_featured')
                        ->label('Featured')
                        ->helperText('Prioritize this artist in discovery rails.')
                        ->visible(fn (): bool => ArtistProfile::supportsFeaturedCuration()),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->columns([
                Tables\Columns\TextColumn::make('stage_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('User')->searchable(),
                Tables\Columns\TextColumn::make('online_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (ArtistProfile $record): string => $record->user?->is_online ? 'Online now' : 'Offline')
                    ->color(fn (ArtistProfile $record): string => $record->user?->is_online ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('last_seen_status')
                    ->label('Last Seen')
                    ->state(fn (ArtistProfile $record): string => $record->user?->last_seen_at?->diffForHumans() ?? 'No recent activity'),
                Tables\Columns\TextColumn::make('total_plays')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('followers_count')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('total_donations')->money('USD')->sortable(),
                Tables\Columns\IconColumn::make('is_verified')->boolean()->label('Verified'),
                Tables\Columns\IconColumn::make('is_featured')->boolean()->label('Featured')->visible(ArtistProfile::supportsFeaturedCuration()),
                Tables\Columns\IconColumn::make('is_approved')->boolean()->label('Approved'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved'),
                Tables\Filters\TernaryFilter::make('is_verified'),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\Filter::make('online_now')
                    ->label('Online now')
                    ->query(fn (Builder $query): Builder => $query->whereHas('user', fn (Builder $userQuery): Builder => $userQuery->online())),
                ...(ArtistProfile::supportsFeaturedCuration()
                    ? [Tables\Filters\TernaryFilter::make('is_featured')]
                    : []),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (): bool => AdminPanelAccess::allows(['approve artists', 'manage artists']))
                    ->action(fn (ArtistProfile $record) => $record->update([
                        'is_approved' => true,
                        'is_active' => true,
                    ])),
                Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (): bool => AdminPanelAccess::allows(['reject artists', 'manage artists']))
                    ->action(fn (ArtistProfile $record) => $record->update([
                        'is_approved' => false,
                    ])),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\BulkAction::make('approveSelected')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check-circle')
                        ->visible(fn (): bool => AdminPanelAccess::allows(['approve artists', 'manage artists']))
                        ->action(fn ($records) => $records->each->update([
                            'is_approved' => true,
                            'is_active' => true,
                        ])),
                    Actions\BulkAction::make('rejectSelected')
                        ->label('Reject selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (): bool => AdminPanelAccess::allows(['reject artists', 'manage artists']))
                        ->action(fn ($records) => $records->each->update([
                            'is_approved' => false,
                        ])),
                    ...(ArtistProfile::supportsFeaturedCuration()
                        ? [
                            Actions\BulkAction::make('feature')
                                ->label('Feature selected')
                                ->visible(fn (): bool => AdminPanelAccess::allows('manage artists'))
                                ->action(fn ($records) => $records->each->update(['is_featured' => true]))
                                ->icon('heroicon-o-star'),
                            Actions\BulkAction::make('unfeature')
                                ->label('Remove feature')
                                ->visible(fn (): bool => AdminPanelAccess::allows('manage artists'))
                                ->action(fn ($records) => $records->each->update(['is_featured' => false]))
                                ->icon('heroicon-o-star'),
                        ]
                        : []),
                ]),
            ]);  
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListArtistProfiles::route('/'),
            'create' => Pages\CreateArtistProfile::route('/create'),
            'edit'   => Pages\EditArtistProfile::route('/{record}/edit'),
        ];
    }
}
