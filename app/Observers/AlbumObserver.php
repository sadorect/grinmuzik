<?php

namespace App\Observers;

use App\Models\Album;
use App\Support\AuditLogger;

/**
 * Audit hooks for albums.
 *
 * Album has no SoftDeletes, unlike Track and ArtistProfile — a deleted album
 * is gone. The log row written here is the only surviving record that it ever
 * existed, so the delete event carries more context than its counterparts.
 */
class AlbumObserver
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function created(Album $album): void
    {
        $this->audit->record('album.created', $album);
    }

    public function updated(Album $album): void
    {
        $this->audit->recordChanges('album.updated', $album);
    }

    public function deleted(Album $album): void
    {
        $this->audit->record('album.deleted', $album, [
            'type' => $album->type,
            'was_published' => (bool) $album->is_published,
            'release_date' => $album->release_date?->format('Y-m-d'),
            // Hard delete: nothing else will remember the tracklist size.
            'track_count' => $album->tracks()->count(),
            'created_at' => $album->created_at?->format('Y-m-d H:i:s'),
        ]);
    }
}
