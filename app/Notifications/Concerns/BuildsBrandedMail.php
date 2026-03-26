<?php

namespace App\Notifications\Concerns;

use App\Models\SiteSetting;
use App\Support\ThemePalette;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Schema;
use Throwable;

trait BuildsBrandedMail
{
    /**
     * @return array{siteName:string,siteUrl:string,primaryColor:string,secondaryColor:string,contactUrl:string}
     */
    protected function brandContext(): array
    {
        try {
            $settings = Schema::hasTable('site_settings')
                ? SiteSetting::current()
                : null;
        } catch (Throwable) {
            $settings = null;
        }

        return [
            'siteName' => $settings?->effective_site_name ?? config('app.name', 'GrinMuzik'),
            'siteUrl' => url('/'),
            'primaryColor' => $settings?->effective_primary_color ?? ThemePalette::DEFAULT_PRIMARY,
            'secondaryColor' => $settings?->effective_secondary_color ?? ThemePalette::DEFAULT_SECONDARY,
            'contactUrl' => route('contact'),
        ];
    }

    protected function brandedMail(array $payload): MailMessage
    {
        return (new MailMessage)
            ->subject($payload['subject'])
            ->view(
                [
                    'html' => 'emails.branded-message',
                    'text' => 'emails.branded-message-text',
                ],
                array_merge($this->brandContext(), [
                    'subject' => $payload['subject'],
                    'preheader' => $payload['preheader'] ?? $payload['subject'],
                    'recipientName' => $payload['recipientName'] ?? null,
                    'eyebrow' => $payload['eyebrow'] ?? null,
                    'title' => $payload['title'],
                    'intro' => $payload['intro'] ?? null,
                    'messageLines' => $payload['messageLines'] ?? [],
                    'highlights' => $payload['highlights'] ?? [],
                    'ctaLabel' => $payload['ctaLabel'] ?? null,
                    'ctaUrl' => $payload['ctaUrl'] ?? null,
                    'secondaryCtaLabel' => $payload['secondaryCtaLabel'] ?? null,
                    'secondaryCtaUrl' => $payload['secondaryCtaUrl'] ?? null,
                    'footerNote' => $payload['footerNote'] ?? null,
                ]),
            );
    }
}
