<?php

namespace App\Services\Ai;

use App\Contracts\AiNewsletterProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiNewsletterProvider implements AiNewsletterProvider
{
    public function generate(string $prompt, string $systemPrompt): string
    {
        $response = Http::timeout(60)
            ->withToken(config('services.ai.openai.key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.ai.openai.model'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI request failed: ' . $response->body());
        }

        return (string) ($response->json('choices.0.message.content') ?? '');
    }
}
