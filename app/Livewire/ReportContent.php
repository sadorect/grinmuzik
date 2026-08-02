<?php

namespace App\Livewire;

use App\Models\ContentReport;
use App\Rules\Captcha;
use App\Rules\Honeypot;
use App\Services\ContentModerationService;
use App\Support\AuthThrottle;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The "Report" control attached to a track, artist or comment.
 *
 * Google Play requires reporting to be reachable from the content itself, so
 * this is embedded beside what it reports rather than living on a separate
 * page a reviewer has to go looking for.
 *
 * Guests can report. Requiring an account would mean objectionable content
 * stays up until someone is willing to sign up to complain about it.
 */
class ReportContent extends Component
{
    #[Locked]
    public string $contentType = '';

    #[Locked]
    public int $contentId = 0;

    /**
     * Where the trigger sits, so it stays legible.
     *
     * 'default' is a muted control for a light surface. 'onDark' is for the
     * artist hero, which is bg-black/35 with white text — the default grey
     * button is all but invisible there.
     */
    public string $variant = 'default';

    /** Distinct per instance, since several can share a page. */
    #[Locked]
    public string $modalName = '';

    public string $reason = '';
    public string $details = '';
    public string $reporterEmail = '';
    public string $captcha = '';
    public string $website = '';

    public bool $submitted = false;

    public function mount(Model $content, string $variant = 'default'): void
    {
        // #[Locked] means Livewire rejects any client-side change to this, and
        // resolveContent() additionally refuses anything that is not Reportable.
        $this->contentType = $content->getMorphClass();
        $this->contentId = $content->getKey();
        $this->variant = $variant;

        $this->modalName = 'report-'.\Illuminate\Support\Str::slug(class_basename($this->contentType)).'-'.$this->contentId;
    }

    public function openModal(): void
    {
        $this->reset('reason', 'details', 'captcha', 'website', 'submitted');
        $this->resetErrorBag();
    }

    /**
     * Tailwind classes for the trigger, per surface.
     */
    public function triggerClasses(): string
    {
        return match ($this->variant) {
            'onDark' => 'text-white/70 hover:text-white',
            default => 'text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400',
        };
    }

    public function submit(): void
    {
        AuthThrottle::ensureAllowed('content_report', 'reason');

        $this->validate([
            'reason' => ['required', 'string', 'in:'.implode(',', array_keys(ContentReport::reasons()))],
            'details' => ['nullable', 'string', 'max:1000'],
            'reporterEmail' => ['nullable', 'email', 'max:200'],
            'captcha' => ['required', new Captcha('content-report')],
            'website' => [new Honeypot],
        ]);

        $content = $this->resolveContent();

        if (! $content) {
            $this->addError('reason', 'That content is no longer available.');

            return;
        }

        $moderation = app(ContentModerationService::class);

        // Silently accept a duplicate. Telling someone their report was
        // discarded invites them to file it again from another address, and the
        // first one is already in the queue.
        if (! $moderation->alreadyReported($content, auth()->id(), request()->ip())) {
            $moderation->report($content, [
                'reporter_id' => auth()->id(),
                'reporter_email' => $this->reporterEmail ?: auth()->user()?->email,
                'reporter_ip' => request()->ip(),
                'reason' => $this->reason,
                'details' => $this->details,
            ]);
        }

        $this->submitted = true;
    }

    protected function resolveContent(): ?Model
    {
        $class = $this->contentType;

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        $model = new $class;

        // Only things that opted in are reportable. Without this check, a
        // tampered payload could aim a report at any model in the app.
        if (! in_array(\App\Models\Concerns\Reportable::class, class_uses_recursive($model), true)) {
            return null;
        }

        return $class::find($this->contentId);
    }

    public function render()
    {
        return view('livewire.report-content', [
            'reasons' => ContentReport::reasons(),
        ]);
    }
}
