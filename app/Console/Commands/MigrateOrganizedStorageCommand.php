<?php

namespace App\Console\Commands;

use App\Models\HomepageBannerSlide;
use App\Models\SiteSetting;
use App\Models\Track;
use App\Support\MediaStorage\OrganizedStorageMigrator;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MigrateOrganizedStorageCommand extends Command
{
    protected $signature = 'media:migrate-organized-storage
        {--dry-run : Report the moves without copying files}
        {--delete-source : Remove the legacy source files after a successful copy}';

    protected $description = 'Move legacy uploads onto the configured organized storage layout.';

    public function handle(OrganizedStorageMigrator $migrator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $keepSource = ! (bool) $this->option('delete-source');

        $this->components->info('Migrating media library assets...');

        $mediaSummary = ['items' => 0, 'files' => 0, 'warnings' => 0];

        Media::query()->orderBy('id')->each(function (Media $media) use ($migrator, $keepSource, $dryRun, &$mediaSummary): void {
            $result = $migrator->migrateMediaLibraryItem($media, keepSource: $keepSource, dryRun: $dryRun);
            $mediaSummary['items']++;
            $mediaSummary['files'] += $result['files'];
            $mediaSummary['warnings'] += count($result['warnings']);

            foreach ($result['warnings'] as $warning) {
                $this->components->warn($warning);
            }
        });

        $this->components->info('Normalizing direct-path assets...');

        $directSummary = ['tracks' => 0, 'slides' => 0, 'branding' => 0];

        Track::query()
            ->whereNotNull('audio_file_path')
            ->where('audio_file_path', '!=', '')
            ->orderBy('id')
            ->each(function (Track $track) use ($migrator, $keepSource, $dryRun, &$directSummary): void {
                $result = $migrator->normalizeTrackAudioPath($track, keepSource: $keepSource, dryRun: $dryRun);

                if ($result['status'] === 'migrated') {
                    $directSummary['tracks']++;
                }
            });

        HomepageBannerSlide::query()
            ->whereNotNull('background_image')
            ->where('background_image', '!=', '')
            ->orderBy('id')
            ->each(function (HomepageBannerSlide $slide) use ($migrator, $keepSource, $dryRun, &$directSummary): void {
                $result = $migrator->normalizeHomepageBannerSlideBackground($slide, keepSource: $keepSource, dryRun: $dryRun);

                if ($result['status'] === 'migrated') {
                    $directSummary['slides']++;
                }
            });

        SiteSetting::query()
            ->orderBy('id')
            ->each(function (SiteSetting $settings) use ($migrator, $keepSource, $dryRun, &$directSummary): void {
                foreach (['site_logo', 'favicon'] as $attribute) {
                    $result = $migrator->normalizeSiteBrandAsset($settings, $attribute, keepSource: $keepSource, dryRun: $dryRun);

                    if ($result['status'] === 'migrated') {
                        $directSummary['branding']++;
                    }
                }
            });

        $this->newLine();
        $this->line('Summary');
        $this->line("  Media rows processed: {$mediaSummary['items']}");
        $this->line("  Files copied: {$mediaSummary['files']}");
        $this->line("  Track paths normalized: {$directSummary['tracks']}");
        $this->line("  Banner paths normalized: {$directSummary['slides']}");
        $this->line("  Branding assets normalized: {$directSummary['branding']}");
        $this->line("  Warnings: {$mediaSummary['warnings']}");
        $this->line($dryRun ? '  Mode: dry run' : '  Mode: live migration');
        $this->line($keepSource ? '  Source cleanup: skipped' : '  Source cleanup: enabled');

        return self::SUCCESS;
    }
}
