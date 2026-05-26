<?php

namespace App\Filament\Resources\ChartSnapshotResource\Pages;

use App\Filament\Resources\ChartSnapshotResource;
use Filament\Resources\Pages\ListRecords;

class ListChartSnapshots extends ListRecords
{
    protected static string $resource = ChartSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ChartSnapshotResource::snapshotTriggerAction(),
        ];
    }
}