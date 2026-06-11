<?php

namespace App\Filament\Resources\McpApiKeyResource\Pages;

use App\Filament\Resources\McpApiKeyResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListMcpApiKeys extends ListRecords
{
    protected static string $resource = McpApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('newKey')
                ->label('New API Key')
                ->icon('heroicon-o-plus')
                ->modalHeading('Create MCP API Key')
                ->modalDescription('The token is shown only once after creation — copy it immediately.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Key name')
                        ->placeholder('e.g. production-server')
                        ->helperText('Will be prefixed with "mcp-" automatically.')
                        ->required()
                        ->maxLength(80)
                        ->regex('/^[a-zA-Z0-9\-_\s]+$/')
                        ->validationMessages(['regex' => 'Only letters, numbers, spaces, dashes and underscores are allowed.']),
                    Forms\Components\CheckboxList::make('abilities')
                        ->label('Abilities')
                        ->options([
                            'read'  => 'Read — browse tracks, artists, charts, playlists',
                            'write' => 'Write — record plays, toggle likes, manage playlists',
                        ])
                        ->default(['read'])
                        ->required()
                        ->minItems(1),
                ])
                ->action(function (array $data): void {
                    $name = 'mcp-' . Str::slug($data['name']);
                    /** @var \App\Models\User $user */
                    $user     = auth()->user();
                    $plain    = $user->createToken($name, $data['abilities'])->plainTextToken;

                    Notification::make()
                        ->title("API key \"{$name}\" created")
                        ->body(
                            "Copy your token now — it will **not** be shown again:\n\n" .
                            "`{$plain}`"
                        )
                        ->warning()
                        ->persistent()
                        ->icon('heroicon-o-key')
                        ->send();
                }),
        ];
    }
}
