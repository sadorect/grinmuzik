<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Notifications\Concerns\BuildsBrandedMail;
use App\Support\Mail\EmailCopy;
use App\Support\Mail\EmailTemplateCatalog;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A template's saved wording, filled with sample data, sent to an admin to check. */
class EmailTemplateTestMail extends Mailable
{
    use BuildsBrandedMail;

    public function __construct(
        public readonly string $templateKey,
        public readonly string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Test] ' . ($this->copy()->subject ?: EmailTemplateCatalog::get($this->templateKey)['name']));
    }

    public function content(): Content
    {
        $copy = $this->copy();
        $definition = EmailTemplateCatalog::get($this->templateKey);
        $framingOnly = ! in_array('body', EmailTemplateCatalog::fields($this->templateKey), true);

        return new Content(
            view: 'emails.branded-message',
            text: 'emails.branded-message-text',
            with: $this->brandedViewData([
                ...$copy->brandedPayload(),
                'subject' => $copy->subject ?: $definition['name'],
                'title' => $copy->title ?: 'The subject the admin writes appears here',
                'messageLines' => $framingOnly ? ['The message the admin writes appears here.'] : $copy->lines,
                'recipientName' => $this->recipientName,
                'ctaUrl' => $copy->ctaLabel ? url('/') : null,
                'highlights' => [['label' => 'Test of', 'value' => $definition['name'] . ' (sample data)']],
            ]),
        );
    }

    protected function copy(): EmailCopy
    {
        return EmailTemplate::copy($this->templateKey, [
            ...EmailTemplateCatalog::get($this->templateKey)['sample'],
            'recipient_name' => $this->recipientName,
        ]);
    }
}
