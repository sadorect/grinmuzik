<?php

namespace App\Mail;

use App\Models\ContentReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContentReportedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ContentReport $report,
    ) {}

    public function envelope(): Envelope
    {
        // The urgent prefix is what makes a child-safety report visible in a
        // mailbox that also receives routine spam flags.
        $prefix = $this->report->isUrgent()
            ? '[URGENT] '
            : '';

        return new Envelope(
            subject: $prefix.'Content reported on GrinMuzik: '.$this->report->reasonLabel(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.content-reported',
        );
    }
}
