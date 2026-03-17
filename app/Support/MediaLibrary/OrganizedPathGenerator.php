<?php

namespace App\Support\MediaLibrary;

use App\Models\Album;
use App\Models\ArtistProfile;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class OrganizedPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media) . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media) . '/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media) . '/responsive-images/';
    }

    protected function getBasePath(Media $media): string
    {
        $prefix = trim((string) config('media-library.prefix', ''), '/');
        $relativePath = $this->getRelativePath($media);

        return $prefix !== ''
            ? "{$prefix}/{$relativePath}"
            : $relativePath;
    }

    protected function getRelativePath(Media $media): string
    {
        $modelSegment = match ($media->model_type) {
            ArtistProfile::class => 'artists',
            User::class => 'users',
            Track::class => 'tracks',
            Album::class => 'albums',
            Playlist::class => 'playlists',
            default => Str::plural(Str::snake(class_basename((string) $media->model_type))),
        };

        return "{$modelSegment}/{$media->model_id}/{$media->collection_name}";
    }
}
