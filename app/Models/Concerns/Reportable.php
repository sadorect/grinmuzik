<?php

namespace App\Models\Concerns;

use App\Models\ContentReport;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Applied to anything a listener can encounter and object to.
 *
 * Keeping this a trait rather than repeating the relation means a new
 * reportable type is one `use` away from appearing in the same moderation
 * queue, which is the property that stops the queue fragmenting.
 */
trait Reportable
{
    public function reports(): MorphMany
    {
        return $this->morphMany(ContentReport::class, 'reportable');
    }

    public function pendingReports(): MorphMany
    {
        return $this->reports()->where('status', ContentReport::STATUS_PENDING);
    }

    /**
     * A short human label for the moderation queue, so a reviewer can tell what
     * they are looking at without opening the record.
     */
    public function reportableLabel(): string
    {
        return $this->title
            ?? $this->stage_name
            ?? \Illuminate\Support\Str::limit((string) ($this->body ?? ''), 60)
            ?: 'Untitled';
    }

    /**
     * Take the content out of public view without destroying it.
     *
     * Deliberately not a delete: a report may be wrong, and the artist deserves
     * their work back if it is. Types that cannot be unpublished override this.
     */
    public function hideFromPublic(): void
    {
        if (array_key_exists('is_published', $this->getAttributes())) {
            $hidden = ['is_published' => false];

            // Otherwise the scheduler would put hidden content back up.
            if (array_key_exists('scheduled_release_at', $this->getAttributes())) {
                $hidden['scheduled_release_at'] = null;
            }

            $this->forceFill($hidden)->save();

            return;
        }

        if (array_key_exists('is_active', $this->getAttributes())) {
            $this->forceFill(['is_active' => false])->save();

            return;
        }

        // Nothing to toggle — a comment, for instance — so soft-delete it.
        $this->delete();
    }
}
