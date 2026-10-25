<?php

namespace App\Mail;

use App\Models\ArtistProfile;
use App\Models\EmailTemplate;
use App\Notifications\Concerns\BuildsBrandedMail;
use App\Support\Mail\EmailCopy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DormantArtistWarningMail extends Mailable
{
    use BuildsBrandedMail, Queueable, SerializesModels;

    public function __construct(
        public readonly ArtistProfile $artist,
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
                'recipientName' => $this->artist->display_name,
                'ctaUrl' => route('artist.upload-track'),
            ]),
        );
    }

    protected function copy(): EmailCopy
    {
        return EmailTemplate::copy('dormant_artist_warning', [
            'recipient_name' => $this->artist->display_name,
            'stage_name' => $this->artist->display_name,
        ]);
    }
}
