<?php
namespace App\Filament\Resources\TrackResource\Pages;
use App\Filament\Resources\TrackResource;
use App\Support\MediaStorage\OrganizedStorageMigrator;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditTrack extends EditRecord {
    protected static string $resource = TrackResource::class;
    protected ?string $previousAudioPath = null;
    protected function getHeaderActions(): array {
        return [Actions\DeleteAction::make()];
    }

    protected function beforeSave(): void
    {
        $this->previousAudioPath = $this->record->getOriginal('audio_file_path');
    }

    protected function afterSave(): void
    {
        app(OrganizedStorageMigrator::class)->normalizeTrackAudioPath(
            $this->record,
            previousPath: $this->previousAudioPath,
            keepSource: false,
        );
    }
}
