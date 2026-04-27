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

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'webhook_path' => env('TELEGRAM_WEBHOOK_PATH', 'telegram/webhook'),
        'timeout' => (int) env('TELEGRAM_HTTP_TIMEOUT', 10),
        'history_limit' => (int) env('TELEGRAM_HISTORY_LIMIT', 30),
    ],

    'ollama' => [
        'api_key' => env('OLLAMA_API_KEY', ''),
        'api_url' => env('OLLAMA_API_URL', 'http://localhost:11434/api/chat'),
        'model' => env('OLLAMA_MODEL', 'llama3.2'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 60),
    ],

    'ai_tools' => [
        'web_search_timeout' => (int) env('AI_WEB_SEARCH_TIMEOUT', 12),
        'web_search_max_results' => (int) env('AI_WEB_SEARCH_MAX_RESULTS', 5),
        'web_fetch_timeout' => (int) env('AI_WEB_FETCH_TIMEOUT', 15),
        'web_fetch_max_chars' => (int) env('AI_WEB_FETCH_MAX_CHARS', 12000),
        'expense_currency' => env('AI_EXPENSE_CURRENCY', 'EGP'),
    ],

];
