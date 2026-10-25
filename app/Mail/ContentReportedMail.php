<?php

namespace App\Mail;

use App\Models\ContentReport;
use App\Models\EmailTemplate;
use App\Notifications\Concerns\BuildsBrandedMail;
use App\Support\Mail\EmailCopy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContentReportedMail extends Mailable
{
    use BuildsBrandedMail, Queueable, SerializesModels;

    public function __construct(
        public readonly ContentReport $report,
    ) {}

    public function envelope(): Envelope
    {
        // The urgent template carries the [URGENT] prefix, which is what makes a
        // child-safety report visible in a mailbox that also gets routine flags.
        return new Envelope(subject: $this->copy()->subject);
    }

    public function content(): Content
    {
        $content = class_basename($this->report->reportable_type) . ' — '
            . ($this->report->reportable ? $this->report->reportable->reportableLabel() : '(no longer exists)');

        return new Content(
            view: 'emails.branded-message',
            text: 'emails.branded-message-text',
            with: $this->brandedViewData([
                ...$this->copy()->brandedPayload(),
                'highlights' => [
                    ['label' => 'Content', 'value' => $content],
                    ['label' => 'Reported by', 'value' => $this->report->reporter?->name ?? $this->report->reporter_email ?? 'Anonymous'],
                    ['label' => 'Reason', 'value' => $this->report->reasonLabel()],
                ],
                'ctaUrl' => url('/musicdirector/content-reports'),
            ]),
        );
    }

    protected function copy(): EmailCopy
    {
        return EmailTemplate::copy($this->report->isUrgent() ? 'content_reported_urgent' : 'content_reported', [
            'reason' => $this->report->reasonLabel(),
            'details' => filled($this->report->details) ? $this->report->details : null,
        ]);
    }
}
