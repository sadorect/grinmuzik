<?php

namespace App\Observers;

use App\Models\Track;
use App\Support\AuditLogger;

/**
 * Audit hooks for tracks.
 *
 * Deliberately at the model layer rather than in the controllers. A track can
 * be deleted from the artist dashboard, from the upload rollback path, from
 * the admin API and from Filament — instrumenting each one guarantees the next
 * one added is missed. The observer sees them all, including tinker.
 */
class TrackObserver
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function created(Track $track): void
    {
        $this->audit->record('track.uploaded', $track, [
            'is_published' => ['old' => null, 'new' => (bool) $track->is_published],
        ]);
    }

    public function updated(Track $track): void
    {
        // A publish toggle is the thing most worth finding later, so it gets
        // its own event rather than hiding inside a generic "edited" diff.
        if ($track->wasChanged('is_published')) {
            $this->audit->record(
                $track->is_published ? 'track.published' : 'track.unpublished',
                $track,
            );
        }

        // Anything else changed in the same save is still worth a diff, but
        // is_published is already reported above.
        $changes = $this->audit->diff($track);
        unset($changes['is_published']);

        // published_at is set and cleared by the publish toggle itself, so
        // when it moves alongside is_published it is the same event, not a
        // separate edit. On its own it is a real change worth reporting.
        if ($track->wasChanged('is_published')) {
            unset($changes['published_at']);
        }

        if ($changes !== []) {
            $this->audit->record('track.updated', $track, $changes);
        }
    }

    public function deleted(Track $track): void
    {
        // Capture how much was lost — a delete that takes a well-played track
        // down reads very differently from removing an unplayed draft.
        $this->audit->record('track.deleted', $track, [
            'play_count' => $track->play_count,
            'was_published' => (bool) $track->is_published,
            'uploaded_at' => $track->created_at?->format('Y-m-d H:i:s'),
        ]);
    }

    public function restored(Track $track): void
    {
        $this->audit->record('track.restored', $track);
    }

    public function forceDeleted(Track $track): void
    {
        $this->audit->record('track.force_deleted', $track, [
            'play_count' => $track->play_count,
        ]);
    }
}
