<?php

namespace App\Support\Tv;

use App\Models\Album;
use App\Models\ArtistProfile;
use App\Models\Playlist;
use App\Models\Track;

/**
 * The JSON shapes the TV app reads. One place, so every row and page hands
 * the TV the same fields: small art for tiles, large art for the
 * now-playing screen, durations in seconds.
 */
class TvPayload
{
    /** Eager loads every track payload needs, so a row costs a fixed number of queries. */
    public const TRACK_WITH = ['artistProfile:id,slug,stage_name', 'album:id,slug,title', 'media', 'album.media'];

    public static function track(Track $track): array
    {
        return [
            'id' => $track->id,
            'slug' => $track->slug,
            'title' => $track->title,
            'artist' => $track->artistProfile ? [
                'slug' => $track->artistProfile->slug,
                'name' => $track->artistProfile->stage_name,
            ] : null,
            'album' => $track->album ? [
                'slug' => $track->album->slug,
                'title' => $track->album->title,
            ] : null,
            'duration' => $track->duration_in_seconds,
            'cover_url' => self::absolute($track->getCoverUrl()),
            'artwork_url' => self::absolute($track->getLargeCoverUrl()),
        ];
    }

    public static function album(Album $album): array
    {
        return [
            'slug' => $album->slug,
            'title' => $album->title,
            'type' => $album->type,
            'artist' => $album->artist ? [
                'slug' => $album->artist->slug,
                'name' => $album->artist->stage_name,
            ] : null,
            'release_year' => $album->release_date?->year,
            'track_count' => $album->public_tracks_count ?? null,
            'cover_url' => self::absolute($album->getCoverUrl()),
            'artwork_url' => self::absolute($album->getLargeCoverUrl()),
        ];
    }

    public static function artist(ArtistProfile $artist): array
    {
        return [
            'slug' => $artist->slug,
            'name' => $artist->stage_name,
            'image_url' => self::absolute($artist->getAvatarUrl('preview')),
        ];
    }

    public static function playlist(Playlist $playlist): array
    {
        return [
            'slug' => $playlist->slug,
            'name' => $playlist->name,
            'owner' => $playlist->user?->name,
            'track_count' => $playlist->tracks_count ?? null,
            'cover_url' => self::absolute($playlist->getCoverUrl()),
        ];
    }

    /**
     * A packaged TV app runs from local files, where "/storage/x.jpg" would
     * point at the TV itself. Media on R2 is already absolute.
     */
    private static function absolute(string $url): string
    {
        return $url === '' ? '' : url($url);
    }
}
