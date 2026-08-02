<?php

namespace App\Services;

use App\Mail\ContentReportedMail;
use App\Models\ContentReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Receives reports and applies moderator decisions.
 *
 * Google Play's UGC policy asks for two things this class provides: a way for
 * users to flag content, and evidence that flags actually go somewhere. The
 * second half matters as much as the first — a report form with no queue behind
 * it fails review.
 */
class ContentModerationService
{
    /**
     * Number of pending reports after which content is hidden automatically.
     *
     * The point is not to be a verdict — it is to bound how long something
     * plainly objectionable stays up when nobody is at a desk. A moderator can
     * restore it in one click, so the cost of a wrong auto-hide is small and the
     * cost of leaving it up overnight is not.
     */
    public function autoHideThreshold(): int
    {
        return (int) config('security.moderation.auto_hide_threshold', 3);
    }

    public function report(Model $content, array $attributes): ContentReport
    {
        $report = $content->reports()->create([
            'reporter_id' => $attributes['reporter_id'] ?? null,
            'reporter_email' => $attributes['reporter_email'] ?? null,
            'reporter_ip' => $attributes['reporter_ip'] ?? null,
            'reason' => $attributes['reason'],
            'details' => $attributes['details'] ?? null,
            'status' => ContentReport::STATUS_PENDING,
        ]);

        // A single child-safety or violent-extremism report is enough. Waiting
        // for a threshold on those categories is not a defensible position.
        if ($report->isUrgent()) {
            $this->hide($content, $report, auto: true);
        } elseif ($content->pendingReports()->count() >= $this->autoHideThreshold()) {
            $this->hide($content, $report, auto: true);
        }

        $this->notifyModerators($report);

        return $report;
    }

    /**
     * Has this reporter already flagged this content?
     *
     * Stops one person inflating the count toward the auto-hide threshold on
     * their own, which would turn the safety net into a heckler's veto.
     */
    public function alreadyReported(Model $content, ?int $userId, ?string $ip): bool
    {
        return $content->reports()
            ->where('status', ContentReport::STATUS_PENDING)
            ->where(function ($query) use ($userId, $ip): void {
                if ($userId) {
                    $query->orWhere('reporter_id', $userId);
                }

                if ($ip) {
                    $query->orWhere('reporter_ip', $ip);
                }
            })
            ->exists();
    }

    protected function hide(Model $content, ContentReport $report, bool $auto = false): void
    {
        $content->hideFromPublic();

        if ($auto) {
            $report->forceFill([
                'action_taken' => ContentReport::ACTION_UNPUBLISHED,
                'moderator_note' => 'Automatically hidden pending review.',
            ])->save();
        }
    }

    /**
     * Apply a moderator's decision to every pending report on this content.
     *
     * Resolving them together is deliberate: five reports about one track are
     * one decision, and leaving four of them open would make the queue look
     * like unfinished work forever.
     */
    public function resolve(ContentReport $report, string $status, User $moderator, ?string $note = null, string $action = ContentReport::ACTION_NONE): void
    {
        $content = $report->reportable;

        if ($content) {
            match ($action) {
                ContentReport::ACTION_UNPUBLISHED => $content->hideFromPublic(),
                ContentReport::ACTION_DELETED => $content->delete(),
                default => null,
            };
        }

        $siblings = $content
            ? $content->reports()->where('status', ContentReport::STATUS_PENDING)->get()
            : collect([$report]);

        foreach ($siblings->push($report)->unique('id') as $pending) {
            $pending->forceFill([
                'status' => $status,
                'reviewed_by' => $moderator->id,
                'reviewed_at' => now(),
                'moderator_note' => $note,
                'action_taken' => $action,
            ])->save();
        }
    }

    protected function notifyModerators(ContentReport $report): void
    {
        $recipient = config('security.moderation.notify_address') ?: config('mail.from.address');

        if (! $recipient) {
            return;
        }

        try {
            Mail::to($recipient)->send(new ContentReportedMail($report));
        } catch (Throwable $e) {
            // A report that was saved but not emailed is still in the queue.
            // Losing the report because the mailer is down would be worse.
            Log::error('Could not email a content report notification.', [
                'report_id' => $report->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
