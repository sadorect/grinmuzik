<?php

namespace App\Support;

use App\Models\SiteSetting;
use App\Models\Track;
use Illuminate\Support\Facades\DB;

/**
 * What plays after a track: same album, then same artist, then same genre,
 * then the most popular of the rest, each tier capped by its site setting.
 *
 * Shared by the web player and the TV app so both queue the same music.
 */
class AutoQueue
{
    /**
     * @return list<int> track ids in play order
     */
    public static function trackIdsFor(Track $seedTrack): array
    {
        $totalLimit  = SiteSetting::autoQueueTotalLimit();
        $albumLimit  = SiteSetting::autoQueueAlbumLimit();
        $artistLimit = SiteSetting::autoQueueArtistLimit();
        $genreLimit  = SiteSetting::autoQueueGenreLimit();

        $seedId   = $seedTrack->id;
        $albumId  = $seedTrack->album_id;
        $artistId = $seedTrack->artist_profile_id;
        $genreId  = $seedTrack->genre_id;

        $orderCols  = 'is_featured DESC, play_count DESC, downloads_count DESC, created_at DESC';
        $subqueries = [];
        $bindings   = [];

        // Tier 1 – same album
        if ($albumId && $albumLimit > 0) {
            $subqueries[] = "(SELECT id, 1 AS tier, is_featured, play_count, downloads_count, created_at
                FROM tracks WHERE is_published = 1 AND album_id = ? AND id != ?
                ORDER BY {$orderCols} LIMIT ?)";
            $bindings = array_merge($bindings, [$albumId, $seedId, $albumLimit]);
        }

        // Tier 2 – same artist, excluding tracks already covered by the album tier
        if ($artistId && $artistLimit > 0) {
            $albumExclude = $albumId ? 'AND (album_id IS NULL OR album_id != ?)' : '';
            $subqueries[] = "(SELECT id, 2 AS tier, is_featured, play_count, downloads_count, created_at
                FROM tracks WHERE is_published = 1 AND artist_profile_id = ? {$albumExclude} AND id != ?
                ORDER BY {$orderCols} LIMIT ?)";
            $bindings = $albumId
                ? array_merge($bindings, [$artistId, $albumId, $seedId, $artistLimit])
                : array_merge($bindings, [$artistId, $seedId, $artistLimit]);
        }

        // Tier 3 – same genre, excluding tracks already covered by the artist tier
        if ($genreId && $genreLimit > 0) {
            $artistExclude = $artistId ? 'AND (artist_profile_id IS NULL OR artist_profile_id != ?)' : '';
            $subqueries[] = "(SELECT id, 3 AS tier, is_featured, play_count, downloads_count, created_at
                FROM tracks WHERE is_published = 1 AND genre_id = ? {$artistExclude} AND id != ?
                ORDER BY {$orderCols} LIMIT ?)";
            $bindings = $artistId
                ? array_merge($bindings, [$genreId, $artistId, $seedId, $genreLimit])
                : array_merge($bindings, [$genreId, $seedId, $genreLimit]);
        }

        // Tier 4 – global remainder, excluding everything already covered
        $globalWhereParts = ['id != ?'];
        $globalBindings   = [$seedId];
        if ($albumId) {
            $globalWhereParts[] = '(album_id IS NULL OR album_id != ?)';
            $globalBindings[]   = $albumId;
        }
        if ($artistId) {
            $globalWhereParts[] = '(artist_profile_id IS NULL OR artist_profile_id != ?)';
            $globalBindings[]   = $artistId;
        }
        if ($genreId) {
            $globalWhereParts[] = '(genre_id IS NULL OR genre_id != ?)';
            $globalBindings[]   = $genreId;
        }
        $globalBindings[] = $totalLimit;
        $globalWhere      = implode(' AND ', $globalWhereParts);
        $subqueries[]     = "(SELECT id, 4 AS tier, is_featured, play_count, downloads_count, created_at
            FROM tracks WHERE is_published = 1 AND {$globalWhere}
            ORDER BY {$orderCols} LIMIT ?)";
        $bindings         = array_merge($bindings, $globalBindings);

        // Single UNION ALL — outer query applies tier ordering and the total cap
        $union      = implode(' UNION ALL ', $subqueries);
        $sql        = "SELECT id FROM ({$union}) AS combined ORDER BY tier, {$orderCols} LIMIT ?";
        $bindings[] = $totalLimit;

        $orderedIds = array_column(DB::select($sql, $bindings), 'id');

        return array_map('intval', $orderedIds);
    }
}
