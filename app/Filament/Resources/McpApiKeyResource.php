<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasAdminResourcePermissions;
use App\Filament\Resources\McpApiKeyResource\Pages;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;

class McpApiKeyResource extends Resource
{
    use HasAdminResourcePermissions;

    protected static ?string $model = PersonalAccessToken::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-key';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'MCP API Keys';

    protected static ?string $modelLabel = 'API Key';

    protected static ?string $pluralModelLabel = 'MCP API Keys';

    protected static array|string|null $viewAnyPermissions = 'manage settings';

    protected static array|string|null $viewPermissions = 'manage settings';

    protected static array|string|null $deletePermissions = 'manage settings';

    // Creation handled by a custom header action — not the standard create page
    protected static array|string|null $createPermissions = null;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('name', 'like', 'mcp-%')
            ->with('tokenable')
            ->latest();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Key name')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('abilities')
                    ->label('Abilities')
                    ->badge()
                    ->formatStateUsing(fn ($state) => implode(', ', (array) $state))
                    ->color('primary'),
                Tables\Columns\TextColumn::make('tokenable_email')
                    ->label('Issued to')
                    ->getStateUsing(fn (PersonalAccessToken $record): string => $record->tokenable?->email ?? '—'),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('Never'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->actions([
                Actions\DeleteAction::make()
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading('Revoke API Key')
                    ->modalDescription('This permanently invalidates the key. Any MCP connectors using it will immediately lose API access.'),
            ])
            ->emptyStateHeading('No MCP API keys yet')
            ->emptyStateDescription('Create a key to connect an MCP client to the API.')
            ->emptyStateIcon('heroicon-o-key');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMcpApiKeys::route('/'),
        ];
    }
}
