<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Release choice on the artist upload/edit forms: publish now, schedule, or draft.
 *
 * `release_at` arrives as an ISO-8601 UTC string. The browser converts the
 * artist's local date/time picker value, so the server never has to guess
 * which timezone the artist meant (the app runs in UTC).
 */
trait ManagesReleaseSchedule
{
    public string $release_mode = 'now';

    public ?string $release_at = null;

    protected function releaseScheduleRules(): array
    {
        return [
            'release_mode' => ['required', Rule::in(['now', 'scheduled', 'draft'])],
            'release_at' => [
                // A leftover time is ignored unless "Schedule" is the choice.
                'exclude_unless:release_mode,scheduled',
                'required',
                'date',
                'after:now',
                'before:' . now()->addYears(2)->toIso8601String(),
            ],
        ];
    }

    protected function releaseScheduleMessages(): array
    {
        return [
            'release_at.required' => 'Pick the date and time this should go live.',
            'release_at.after' => 'The release time must be in the future.',
            'release_at.before' => 'Releases can be scheduled up to two years ahead.',
        ];
    }

    /** Validated release choice as model attributes. */
    protected function releaseAttributes(): array
    {
        return [
            'is_published' => $this->release_mode === 'now',
            'scheduled_release_at' => $this->release_mode === 'scheduled' ? $this->scheduledReleaseTime() : null,
        ];
    }

    protected function scheduledReleaseTime(): ?Carbon
    {
        return $this->release_at ? Carbon::parse($this->release_at)->utc()->startOfMinute() : null;
    }

    protected function fillReleaseScheduleFrom(Model $model): void
    {
        $this->release_mode = $model->releaseState() === 'live' ? 'now' : $model->releaseState();
        $this->release_at = $model->scheduled_release_at?->toIso8601ZuluString();
    }

    protected function releaseFlash(string $what, string $title): string
    {
        return match ($this->release_mode) {
            // No time here: the flash can't render it in the artist's own timezone.
            'scheduled' => "{$what} \"{$title}\" is scheduled and will go live automatically at the time you picked.",
            'draft' => "{$what} \"{$title}\" saved as a draft.",
            default => '',
        };
    }
}
