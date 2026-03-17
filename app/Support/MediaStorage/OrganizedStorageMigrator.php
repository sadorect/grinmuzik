<?php

namespace App\Support\MediaStorage;

use App\Models\HomepageBannerSlide;
use App\Models\SiteSetting;
use App\Models\Track;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class OrganizedStorageMigrator
{
    /**
     * @return array{status:string, files:int, warnings:array<int, string>}
     */
    public function migrateMediaLibraryItem(Media $media, bool $keepSource = true, bool $dryRun = false): array
    {
        $targetDisk = $this->targetDiskForMedia($media);
        $targetConversionsDisk = $targetDisk;
        $targetOriginalPath = $media->getPathRelativeToRoot();
        $targetBasePath = trim(dirname($targetOriginalPath), '/.');
        $legacyBasePath = (string) $media->getKey();
        $sourceDisk = $media->disk;
        $sourceConversionsDisk = $media->conversions_disk ?: $sourceDisk;

        $files = 0;
        $warnings = [];

        $originalStatus = $this->copyPath(
            $sourceDisk,
            "{$legacyBasePath}/{$media->file_name}",
            $targetDisk,
            $targetOriginalPath,
            $keepSource,
            $dryRun,
        );

        if ($originalStatus === 'copied') {
            $files++;
        } elseif ($originalStatus === 'missing' && ! Storage::disk($targetDisk)->exists($targetOriginalPath)) {
            $warnings[] = "Missing original media file for media #{$media->getKey()} at {$legacyBasePath}/{$media->file_name}.";
        }

        foreach (Storage::disk($sourceConversionsDisk)->allFiles("{$legacyBasePath}/conversions") as $legacyConversionPath) {
            $relativePath = Str::after($legacyConversionPath, "{$legacyBasePath}/conversions/");
            $conversionStatus = $this->copyPath(
                $sourceConversionsDisk,
                $legacyConversionPath,
                $targetConversionsDisk,
                "{$targetBasePath}/conversions/{$relativePath}",
                $keepSource,
                $dryRun,
            );

            if ($conversionStatus === 'copied') {
                $files++;
            }
        }

        foreach (Storage::disk($sourceConversionsDisk)->allFiles("{$legacyBasePath}/responsive-images") as $legacyResponsivePath) {
            $relativePath = Str::after($legacyResponsivePath, "{$legacyBasePath}/responsive-images/");
            $responsiveStatus = $this->copyPath(
                $sourceConversionsDisk,
                $legacyResponsivePath,
                $targetConversionsDisk,
                "{$targetBasePath}/responsive-images/{$relativePath}",
                $keepSource,
                $dryRun,
            );

            if ($responsiveStatus === 'copied') {
                $files++;
            }
        }

        if (! $dryRun) {
            $dirty = false;

            if ($media->disk !== $targetDisk) {
                $media->disk = $targetDisk;
                $dirty = true;
            }

            if (($media->conversions_disk ?: $targetConversionsDisk) !== $targetConversionsDisk) {
                $media->conversions_disk = $targetConversionsDisk;
                $dirty = true;
            }

            if ($dirty) {
                $media->save();
            }
        }

        if (! $keepSource && ! $dryRun) {
            $this->cleanupLegacyMediaDirectories($media, $legacyBasePath, $sourceDisk, $sourceConversionsDisk);
        }

        return [
            'status' => $files > 0 ? 'migrated' : (count($warnings) > 0 ? 'warning' : 'skipped'),
            'files' => $files,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{status:string, path:?string}
     */
    public function normalizeTrackAudioPath(Track $track, ?string $previousPath = null, bool $keepSource = false, bool $dryRun = false): array
    {
        if (blank($track->audio_file_path) || $this->isExternalPath($track->audio_file_path)) {
            return ['status' => 'skipped', 'path' => null];
        }

        $extension = strtolower(pathinfo($track->audio_file_path, PATHINFO_EXTENSION) ?: 'bin');

        return $this->normalizeStoredPathAttribute(
            $track,
            'audio_file_path',
            "tracks/{$track->getKey()}/audio/original.{$extension}",
            $this->privateDisk(),
            array_values(array_unique(array_filter([
                $this->privateDisk(),
                $this->publicDisk(),
                'public',
                'local',
            ]))),
            $previousPath,
            $keepSource,
            $dryRun,
        );
    }

    /**
     * @return array{status:string, path:?string}
     */
    public function normalizeHomepageBannerSlideBackground(HomepageBannerSlide $slide, ?string $previousPath = null, bool $keepSource = false, bool $dryRun = false): array
    {
        if (blank($slide->background_image) || $this->isExternalPath($slide->background_image)) {
            return ['status' => 'skipped', 'path' => null];
        }

        $extension = strtolower(pathinfo($slide->background_image, PATHINFO_EXTENSION) ?: 'bin');

        return $this->normalizeStoredPathAttribute(
            $slide,
            'background_image',
            "site/homepage-slides/{$slide->getKey()}/background/original.{$extension}",
            $this->publicDisk(),
            array_values(array_unique(array_filter([
                $this->publicDisk(),
                'public',
            ]))),
            $previousPath,
            $keepSource,
            $dryRun,
        );
    }

    /**
     * @return array{status:string, path:?string}
     */
    public function normalizeSiteBrandAsset(SiteSetting $settings, string $attribute, ?string $previousPath = null, bool $keepSource = false, bool $dryRun = false): array
    {
        $path = $settings->getAttribute($attribute);

        if (blank($path) || $this->isExternalPath($path)) {
            return ['status' => 'skipped', 'path' => null];
        }

        $directory = match ($attribute) {
            'site_logo' => 'site/branding/logo',
            'favicon' => 'site/branding/favicon',
            default => "site/branding/{$attribute}",
        };

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'bin');

        return $this->normalizeStoredPathAttribute(
            $settings,
            $attribute,
            "{$directory}/current.{$extension}",
            $this->publicDisk(),
            array_values(array_unique(array_filter([
                $this->publicDisk(),
                'public',
            ]))),
            $previousPath,
            $keepSource,
            $dryRun,
        );
    }

    protected function publicDisk(): string
    {
        return config('media-storage.public_disk', 'public');
    }

    protected function privateDisk(): string
    {
        return config('media-storage.private_disk', 'local');
    }

    protected function targetDiskForMedia(Media $media): string
    {
        return $media->model_type === Track::class && $media->collection_name === 'audio'
            ? $this->privateDisk()
            : $this->publicDisk();
    }

    protected function cleanupLegacyMediaDirectories(Media $media, string $legacyBasePath, string $sourceDisk, string $sourceConversionsDisk): void
    {
        if (! Storage::disk($sourceDisk)->exists("{$legacyBasePath}/{$media->file_name}")) {
            Storage::disk($sourceDisk)->deleteDirectory($legacyBasePath);
        }

        if ($sourceConversionsDisk === $sourceDisk) {
            return;
        }

        foreach (["{$legacyBasePath}/conversions", "{$legacyBasePath}/responsive-images", $legacyBasePath] as $directory) {
            Storage::disk($sourceConversionsDisk)->deleteDirectory($directory);
        }
    }

    /**
     * @return array{status:string, path:?string}
     */
    protected function normalizeStoredPathAttribute(
        Model $model,
        string $attribute,
        string $targetPath,
        string $targetDisk,
        array $sourceDisks,
        ?string $previousPath = null,
        bool $keepSource = false,
        bool $dryRun = false,
    ): array {
        $currentPath = (string) $model->getAttribute($attribute);

        if ($currentPath === '') {
            return ['status' => 'skipped', 'path' => null];
        }

        $sourceDisk = $this->locateDiskForPath($currentPath, $sourceDisks);

        if ($sourceDisk === null && ! Storage::disk($targetDisk)->exists($targetPath)) {
            return ['status' => 'missing', 'path' => $currentPath];
        }

        if ($sourceDisk !== null) {
            $this->copyPath(
                $sourceDisk,
                $currentPath,
                $targetDisk,
                $targetPath,
                $keepSource,
                $dryRun,
            );
        }

        if (! $dryRun && $currentPath !== $targetPath) {
            $model->forceFill([$attribute => $targetPath])->saveQuietly();
        }

        if (
            ! $keepSource &&
            ! $dryRun &&
            filled($previousPath) &&
            $previousPath !== $currentPath &&
            $previousPath !== $targetPath
        ) {
            $previousDisk = $this->locateDiskForPath($previousPath, $sourceDisks);

            if ($previousDisk && Storage::disk($previousDisk)->exists($previousPath)) {
                Storage::disk($previousDisk)->delete($previousPath);
            }
        }

        return ['status' => $currentPath === $targetPath ? 'skipped' : 'migrated', 'path' => $targetPath];
    }

    protected function locateDiskForPath(string $path, array $candidateDisks): ?string
    {
        foreach ($candidateDisks as $disk) {
            if ($disk !== '' && Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    protected function isExternalPath(string $path): bool
    {
        return Str::startsWith($path, ['http://', 'https://']);
    }

    protected function copyPath(
        string $sourceDisk,
        string $sourcePath,
        string $targetDisk,
        string $targetPath,
        bool $keepSource,
        bool $dryRun,
    ): string {
        if (! Storage::disk($sourceDisk)->exists($sourcePath)) {
            return 'missing';
        }

        if ($sourceDisk === $targetDisk && $sourcePath === $targetPath) {
            return 'skipped';
        }

        if (! $dryRun) {
            $stream = Storage::disk($sourceDisk)->readStream($sourcePath);

            if ($stream === false) {
                return 'missing';
            }

            try {
                Storage::disk($targetDisk)->put($targetPath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (! $keepSource) {
                Storage::disk($sourceDisk)->delete($sourcePath);
            }
        }

        return 'copied';
    }
}
