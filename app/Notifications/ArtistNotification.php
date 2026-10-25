<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\EmailTemplate;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ArtistNotification extends Notification implements ShouldQueue
{
    use BuildsBrandedMail;
    use Queueable;

    public function __construct(
        public readonly string $subject,
        public readonly string $body,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $copy = EmailTemplate::copy('artist_admin_notice', ['recipient_name' => $notifiable->name ?? null]);

        return $this->brandedMail([
            'subject' => $this->subject,
            'title' => $this->subject,
            'eyebrow' => $copy->eyebrow,
            'recipientName' => $notifiable->name ?? null,
            'messageLines' => [$this->body],
            'footerNote' => $copy->footerNote,
        ]);
    }
}
