<?php

namespace App\Mail;

use App\Models\ArtistProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DormantArtistWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ArtistProfile $artist,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your artist status on " . config('app.name') . " is at risk",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dormant-artist-warning',
            with: [
                'uploadUrl' => config('app.url') . '/dashboard/tracks/create',
            ],
        );
    }
}
