<?php

namespace App\Observers;

use App\Models\ArtistProfile;
use App\Support\AuditLogger;

/**
 * Audit hooks for artist profiles.
 *
 * The status flags (verified/approved/active/featured) are the admin-side
 * actions worth answering "who did that, and when" about, so each gets a named
 * event instead of being buried in an attribute diff.
 */
class ArtistProfileObserver
{
    /**
     * Flag => [event when switched on, event when switched off].
     */
    private const FLAG_EVENTS = [
        'is_verified' => ['artist.verified', 'artist.unverified'],
        'is_approved' => ['artist.approved', 'artist.unapproved'],
        'is_active' => ['artist.activated', 'artist.deactivated'],
        'is_featured' => ['artist.featured', 'artist.unfeatured'],
    ];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function created(ArtistProfile $profile): void
    {
        $this->audit->record('artist.created', $profile);
    }

    public function updated(ArtistProfile $profile): void
    {
        $changes = $this->audit->diff($profile);

        foreach (self::FLAG_EVENTS as $flag => [$onEvent, $offEvent]) {
            if (! $profile->wasChanged($flag)) {
                continue;
            }

            $this->audit->record($profile->getAttribute($flag) ? $onEvent : $offEvent, $profile);

            unset($changes[$flag]);
        }

        // A dormancy warning is a state change an admin will want to see in
        // sequence with everything else, not a field edit.
        if ($profile->wasChanged('warned_at') && $profile->warned_at !== null) {
            $this->audit->record('artist.warned', $profile);

            unset($changes['warned_at']);
        }

        if ($changes !== []) {
            $this->audit->record('artist.updated', $profile, $changes);
        }
    }

    public function deleted(ArtistProfile $profile): void
    {
        $this->audit->record('artist.deleted', $profile, [
            'total_plays' => $profile->total_plays,
            'followers_count' => $profile->followers_count,
        ]);
    }

    public function restored(ArtistProfile $profile): void
    {
        $this->audit->record('artist.restored', $profile);
    }
}
