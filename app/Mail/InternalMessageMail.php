<?php

namespace App\Mail;

use App\Models\InternalMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InternalMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly InternalMessage $broadcast,
        public readonly User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->broadcast->interpolate($this->broadcast->subject, $this->recipient),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.internal-message',
        );
    }
}
