<?php
namespace App\Filament\Resources\TrackResource\Pages;
use App\Filament\Resources\TrackResource;
use App\Support\MediaStorage\OrganizedStorageMigrator;
use Filament\Resources\Pages\CreateRecord;
class CreateTrack extends CreateRecord {
    protected static string $resource = TrackResource::class;

    protected function afterCreate(): void
    {
        app(OrganizedStorageMigrator::class)->normalizeTrackAudioPath(
            $this->record,
            keepSource: false,
        );
    }
}
