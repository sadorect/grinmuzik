<?php

namespace App\Contracts;

interface AiNewsletterProvider
{
    public function generate(string $prompt, string $systemPrompt): string;
}
