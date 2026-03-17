<?php

namespace App\Filament\Resources\HomepageBannerSlideResource\Pages;

use App\Filament\Resources\HomepageBannerSlideResource;
use App\Support\MediaStorage\OrganizedStorageMigrator;
use Filament\Resources\Pages\CreateRecord;

class CreateHomepageBannerSlide extends CreateRecord
{
    protected static string $resource = HomepageBannerSlideResource::class;

    protected function afterCreate(): void
    {
        app(OrganizedStorageMigrator::class)->normalizeHomepageBannerSlideBackground(
            $this->record,
            keepSource: false,
        );
    }
}
