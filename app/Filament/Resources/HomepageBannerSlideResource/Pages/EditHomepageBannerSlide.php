<?php

namespace App\Filament\Resources\HomepageBannerSlideResource\Pages;

use App\Filament\Resources\HomepageBannerSlideResource;
use App\Support\MediaStorage\OrganizedStorageMigrator;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHomepageBannerSlide extends EditRecord
{
    protected static string $resource = HomepageBannerSlideResource::class;
    protected ?string $previousBackgroundImage = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $this->previousBackgroundImage = $this->record->getOriginal('background_image');
    }

    protected function afterSave(): void
    {
        app(OrganizedStorageMigrator::class)->normalizeHomepageBannerSlideBackground(
            $this->record,
            previousPath: $this->previousBackgroundImage,
            keepSource: false,
        );
    }
}
