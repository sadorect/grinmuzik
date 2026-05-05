<?php

namespace App\Filament\Resources\PageViewResource\Pages;

use App\Filament\Resources\PageViewResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewPageView extends ViewRecord
{
    protected static string $resource = PageViewResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Visit Details')
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Viewed at')
                        ->dateTime('d M Y H:i:s'),
                    TextEntry::make('path'),
                    TextEntry::make('user.name')
                        ->label('User')
                        ->default('Guest'),
                    TextEntry::make('ip_address')
                        ->label('IP address'),
                    TextEntry::make('session_id')
                        ->label('Session ID'),
                ])
                ->columns(2),
            Section::make('Location')
                ->schema([
                    TextEntry::make('country')
                        ->default('Unknown'),
                    TextEntry::make('region')
                        ->default('-'),
                    TextEntry::make('city')
                        ->default('-'),
                    TextEntry::make('timezone')
                        ->default('-'),
                    TextEntry::make('coordinates')
                        ->label('Coordinates')
                        ->default('-'),
                    TextEntry::make('location_source')
                        ->label('Source')
                        ->formatStateUsing(fn (?string $state): string => match ($state) {
                            'private' => 'Private network',
                            'ip_geolocation' => 'Geo lookup',
                            'cache' => 'Cached from earlier visit',
                            default => 'Pending resolution',
                        }),
                ])
                ->columns(2),
            Section::make('Browser')
                ->schema([
                    TextEntry::make('user_agent')
                        ->label('User agent')
                        ->columnSpanFull()
                        ->default('-'),
                ]),
        ]);
    }
}
