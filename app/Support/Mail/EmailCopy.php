<?php

namespace App\Support\Mail;

/** One email's wording, with placeholders already filled in. */
final class EmailCopy
{
    /** @param list<string> $lines Body paragraphs */
    public function __construct(
        public readonly string $subject,
        public readonly ?string $preheader,
        public readonly ?string $eyebrow,
        public readonly string $title,
        public readonly ?string $intro,
        public readonly array $lines,
        public readonly ?string $ctaLabel,
        public readonly ?string $footerNote,
    ) {}

    /** The branded-layout payload keys this copy fills. */
    public function brandedPayload(): array
    {
        return [
            'subject' => $this->subject,
            'preheader' => $this->preheader ?: $this->subject,
            'eyebrow' => $this->eyebrow,
            'title' => $this->title,
            'intro' => $this->intro,
            'messageLines' => $this->lines,
            'ctaLabel' => $this->ctaLabel,
            'footerNote' => $this->footerNote,
        ];
    }
}
