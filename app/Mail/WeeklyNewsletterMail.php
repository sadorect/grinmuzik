<?php

namespace App\Mail;

use App\Models\NewsletterDraft;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyNewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly NewsletterDraft $draft) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->draft->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.weekly-newsletter',
        );
    }
}
