<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ip_geolocation' => [
        'enabled' => env('IP_GEOLOCATION_ENABLED', true),
        'base_url' => env('IP_GEOLOCATION_BASE_URL', 'https://ipwho.is'),
        'timeout' => env('IP_GEOLOCATION_TIMEOUT', 3),
    ],

    'ai' => [
        'provider' => env('NEWSLETTER_AI_PROVIDER', 'anthropic'),
        'anthropic' => [
            'key'   => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        ],
        'openai' => [
            'key'   => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
        ],
        'ollama' => [
            'url'   => env('OLLAMA_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3'),
        ],
    ],

];
