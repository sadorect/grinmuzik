<?php

namespace App\Services;

use App\Contracts\AiNewsletterProvider;
use Illuminate\Support\Collection;

class NewsletterAiService
{
    public const SYSTEM_PROMPT = 'You are a newsletter writer for GrinMuzik, an independent African music streaming platform. Write engaging, warm newsletters that celebrate artists and music.';

    public function __construct(private readonly AiNewsletterProvider $provider) {}

    public function generate(array $variables, array $data): string
    {
        return $this->provider->generate($this->buildPrompt($variables, $data), self::SYSTEM_PROMPT);
    }

    protected function buildPrompt(array $variables, array $data): string
    {
        $sections = [
            "Write a newsletter email body for the period: {$variables['period']}.",
            "Tone: {$variables['tone']}.",
            "The email subject line will be: \"{$variables['subject']}\".",
        ];

        if (! empty($data['charts'])) {
            $sections[] = "Top tracks this period:\n" . Collection::make($data['charts'])
                ->map(fn (array $track, int $i) => sprintf('%d. "%s" by %s (%s plays)', $i + 1, $track['title'], $track['artist'], number_format($track['plays'])))
                ->implode("\n");
        }

        if (! empty($data['new_artists'])) {
            $sections[] = "New artists who joined this period:\n" . Collection::make($data['new_artists'])
                ->map(fn (array $artist) => "- {$artist['name']}")
                ->implode("\n");
        }

        if (! empty($data['stats'])) {
            $sections[] = 'Platform stats to optionally mention: ' . Collection::make($data['stats'])
                ->map(fn ($value, $key) => "{$key}: {$value}")
                ->implode(', ');
        }

        if (! empty($variables['custom_note'])) {
            $sections[] = "Weave in this personal note from the team naturally: \"{$variables['custom_note']}\"";
        }

        $sections[] = 'Write the full newsletter body as email-friendly HTML (paragraphs, no <html>/<body> wrapper) with a warm greeting and sign-off. Do not repeat the subject line in the body.';

        return implode("\n\n", $sections);
    }
}
