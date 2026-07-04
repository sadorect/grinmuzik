<?php

namespace App\Services\Ai;

use App\Contracts\AiNewsletterProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicNewsletterProvider implements AiNewsletterProvider
{
    public function generate(string $prompt, string $systemPrompt): string
    {
        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key' => config('services.ai.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.ai.anthropic.model'),
                'max_tokens' => 2048,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic request failed: ' . $response->body());
        }

        return (string) ($response->json('content.0.text') ?? '');
    }
}
