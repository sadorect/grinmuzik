<?php

namespace App\Notifications\Artist;

use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ArtistActivityNotification extends Notification
{
    use BuildsBrandedMail;
    use Queueable;

    /**
     * @param  array<int, array{label:string, value:string}>  $highlights
     * @param  array<int, string>  $messageLines
     */
    public function __construct(
        public string $subject,
        public string $eyebrow,
        public string $title,
        public array $messageLines = [],
        public array $highlights = [],
        public ?string $ctaLabel = null,
        public ?string $ctaUrl = null,
        public ?string $secondaryCtaLabel = null,
        public ?string $secondaryCtaUrl = null,
        public ?string $preheader = null,
        public ?string $intro = null,
        public ?string $footerNote = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail([
            'subject' => $this->subject,
            'preheader' => $this->preheader,
            'recipientName' => $notifiable->name ?? null,
            'eyebrow' => $this->eyebrow,
            'title' => $this->title,
            'intro' => $this->intro,
            'messageLines' => $this->messageLines,
            'highlights' => $this->highlights,
            'ctaLabel' => $this->ctaLabel,
            'ctaUrl' => $this->ctaUrl,
            'secondaryCtaLabel' => $this->secondaryCtaLabel,
            'secondaryCtaUrl' => $this->secondaryCtaUrl,
            'footerNote' => $this->footerNote,
        ]);
    }
}
