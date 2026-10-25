<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Notifications\Concerns\BuildsBrandedMail;
use App\Support\Mail\EmailCopy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeletionRequestMail extends Mailable
{
    use BuildsBrandedMail, Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $confirmUrl,
        public readonly int $expiresInHours,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->copy()->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.branded-message',
            text: 'emails.branded-message-text',
            with: $this->brandedViewData([
                ...$this->copy()->brandedPayload(),
                'recipientName' => $this->recipientName,
                'ctaUrl' => $this->confirmUrl,
            ]),
        );
    }

    protected function copy(): EmailCopy
    {
        return EmailTemplate::copy('account_deletion_request', [
            'recipient_name' => $this->recipientName,
            'expires_hours' => (string) $this->expiresInHours,
            'confirm_url' => $this->confirmUrl,
        ]);
    }
}
