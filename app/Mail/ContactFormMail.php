<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Notifications\Concerns\BuildsBrandedMail;
use App\Support\Mail\EmailCopy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
{
    use BuildsBrandedMail, Queueable, SerializesModels;

    public function __construct(
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $messageSubject,
        public readonly string $body,
        public readonly ?int $messageId = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->copy()->subject,
            replyTo: [
                new Address($this->senderEmail, $this->senderName),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.branded-message',
            text: 'emails.branded-message-text',
            with: $this->brandedViewData([
                ...$this->copy()->brandedPayload(),
                'highlights' => array_values(array_filter([
                    ['label' => 'From', 'value' => $this->senderName . ' <' . $this->senderEmail . '>'],
                    ['label' => 'Subject', 'value' => $this->messageSubject],
                    $this->messageId ? ['label' => 'Inbox Reference', 'value' => '#' . $this->messageId] : null,
                    ['label' => 'Received', 'value' => now()->format('d M Y, H:i') . ' UTC'],
                ])),
                'ctaUrl' => url('/musicdirector/contact-messages'),
            ]),
        );
    }

    protected function copy(): EmailCopy
    {
        return EmailTemplate::copy('contact_form_received', [
            'sender_name' => $this->senderName,
            'message_subject' => $this->messageSubject,
            'message' => $this->body,
        ]);
    }
}
