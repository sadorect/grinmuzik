<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Timed releases for content gated by `is_published`.
 *
 * A scheduled item is simply unpublished with a `scheduled_release_at` set, so
 * it stays hidden everywhere the app already checks `is_published`. The
 * `releases:publish-scheduled` command calls releaseIfDue() every minute.
 */
trait SchedulesRelease
{
    public static function bootSchedulesRelease(): void
    {
        static::saving(function ($model): void {
            // A live item has nothing left to wait for. Clearing the time also
            // means a later unpublish can never be silently re-released.
            if ($model->is_published) {
                $model->scheduled_release_at = null;
            }
        });
    }

    public function initializeSchedulesRelease(): void
    {
        $this->mergeCasts(['scheduled_release_at' => 'datetime']);
        $this->mergeFillable(['scheduled_release_at']);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('is_published', false)->whereNotNull('scheduled_release_at');
    }

    public function scopeDueForRelease(Builder $query, ?Carbon $now = null): Builder
    {
        return $query->scheduled()->where('scheduled_release_at', '<=', $now ?? now());
    }

    public function getIsScheduledAttribute(): bool
    {
        return ! $this->is_published && $this->scheduled_release_at !== null;
    }

    /** 'live', 'scheduled' or 'draft'. */
    public function releaseState(): string
    {
        return match (true) {
            (bool) $this->is_published => 'live',
            $this->is_scheduled => 'scheduled',
            default => 'draft',
        };
    }

    public function releaseIfDue(): bool
    {
        return $this->claimAndRelease(due: true);
    }

    /** Release a scheduled item regardless of its time, dated $at (default now). */
    public function releaseNow(?Carbon $at = null): bool
    {
        return $this->claimAndRelease(due: false, at: $at);
    }

    /**
     * Claim the schedule with a conditional UPDATE before publishing, so two
     * overlapping scheduler runs can never both release (and notify) the same
     * item. The publish itself goes through Eloquent so observers, the audit
     * log and search indexing all see it.
     */
    protected function claimAndRelease(bool $due, ?Carbon $at = null): bool
    {
        return DB::transaction(function () use ($due, $at): bool {
            $releaseAt = $this->scheduled_release_at;
            $rawReleaseAt = $this->getRawOriginal('scheduled_release_at');

            $claim = static::query()->whereKey($this->getKey());
            $due ? $claim->dueForRelease() : $claim->scheduled();

            if ($claim->update(['scheduled_release_at' => null]) !== 1) {
                return false;
            }

            // Reload, but keep the pre-claim time as the original so update
            // hooks (Album's tied tracks) still see which schedule was released.
            $this->refresh();
            $this->original['scheduled_release_at'] = $rawReleaseAt;
            $this->scheduled_release_at = null;
            $this->forceFill(['is_published' => true] + $this->releaseAttributes($at ?? ($due && $releaseAt ? $releaseAt : now())))->save();

            return true;
        });
    }

    /** Extra attributes to set when the item goes live. */
    protected function releaseAttributes(Carbon $releaseAt): array
    {
        return [];
    }
}
