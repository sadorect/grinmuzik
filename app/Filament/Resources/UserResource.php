<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasAdminResourcePermissions;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\AdminPanelAccess;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    use HasAdminResourcePermissions;

    protected static ?string $model = User::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static \UnitEnum|string|null $navigationGroup = 'Administration';

    protected static array|string|null $viewAnyPermissions = [
        'view users',
        'create users',
        'edit users',
        'delete users',
        'manage users',
    ];

    protected static array|string|null $viewPermissions = [
        'view users',
        'edit users',
        'delete users',
        'manage users',
    ];

    protected static array|string|null $createPermissions = [
        'create users',
        'manage users',
    ];

    protected static array|string|null $editPermissions = [
        'edit users',
        'manage users',
    ];

    protected static array|string|null $deletePermissions = [
        'delete users',
        'manage users',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('User Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->label('Email Verified At')
                            ->seconds(false),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $context) => $context === 'create')
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->helperText('Leave blank to keep the current password.'),
                    ]),
                Section::make('Roles')
                    ->schema([
                        Forms\Components\Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Assign one or more roles to the user.'),
                    ])
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ]);
    }

    public static function canEdit(Model $record): bool
    {
        if ($record instanceof User && $record->isSuperAdmin() && ! (auth()->user()?->isSuperAdmin() ?? false)) {
            return false;
        }

        return AdminPanelAccess::allows(static::$editPermissions);
    }

    public static function canDelete(Model $record): bool
    {
        if ($record instanceof User && $record->isSuperAdmin() && ! (auth()->user()?->isSuperAdmin() ?? false)) {
            return false;
        }

        return AdminPanelAccess::allows(static::$deletePermissions);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('online_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (User $record): string => $record->is_online ? 'Online now' : 'Offline')
                    ->color(fn (User $record): string => $record->is_online ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->since()
                    ->placeholder('No recent activity')
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->separator(', ')
                    ->label('Roles'),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->boolean()
                    ->label('Verified'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->label('Roles'),
                Tables\Filters\Filter::make('online_now')
                    ->label('Online now')
                    ->query(fn (Builder $query): Builder => $query->online()),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
