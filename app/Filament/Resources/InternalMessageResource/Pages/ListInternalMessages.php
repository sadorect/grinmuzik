<?php

namespace App\Filament\Resources\InternalMessageResource\Pages;

use App\Filament\Resources\InternalMessageResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListInternalMessages extends ListRecords
{
    protected static string $resource = InternalMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Compose Message'),
        ];
    }
}
