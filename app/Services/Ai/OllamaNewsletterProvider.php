<?php

namespace App\Services\Ai;

use App\Contracts\AiNewsletterProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Local dev only — the VPS cannot reach localhost:11434, so never set
 * NEWSLETTER_AI_PROVIDER=ollama in production.
 */
class OllamaNewsletterProvider implements AiNewsletterProvider
{
    public function generate(string $prompt, string $systemPrompt): string
    {
        $baseUrl = rtrim((string) config('services.ai.ollama.url'), '/');

        $response = Http::timeout(120)
            ->post("{$baseUrl}/api/chat", [
                'model' => config('services.ai.ollama.model'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'stream' => false,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Ollama request failed: ' . $response->body());
        }

        return (string) ($response->json('message.content') ?? '');
    }
}
