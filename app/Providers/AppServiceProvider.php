<?php

namespace App\Providers;

use App\AI\Agent\BaseAgent;
use App\AI\Agent\DefaultChatAgent;
use App\AI\Messaging\Telegram\TelegramService;
use App\AI\Messaging\Telegram\TelegramVoiceTranscriber;
use App\AI\Provider\BaseProvider;
use App\AI\Provider\OllamaProvider;
use App\AI\Tool\ToolRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BaseProvider::class, function ($app) {
            $config = $app['config']->get('services.ollama');

            return new OllamaProvider(
                apiKey: (string) ($config['api_key'] ?? ''),
                apiUrl: (string) ($config['api_url'] ?? 'http://localhost:11434/api/chat'),
                timeout: (int) ($config['timeout'] ?? 60),
            );
        });

        $this->app->singleton(ToolRegistry::class, function ($app) {
            $config = $app['config']->get('services.ai_tools', []);

            return new ToolRegistry(
                webSearchTimeoutSeconds: (int) ($config['web_search_timeout'] ?? 12),
                webSearchMaxResults: (int) ($config['web_search_max_results'] ?? 5),
                webFetchTimeoutSeconds: (int) ($config['web_fetch_timeout'] ?? 15),
                webFetchMaxContentChars: (int) ($config['web_fetch_max_chars'] ?? 12000),
            );
        });

        $this->app->singleton(BaseAgent::class, fn ($app) => new DefaultChatAgent(
            toolRegistry: $app->make(ToolRegistry::class),
        ));

        $this->app->singleton(TelegramService::class, function ($app) {
            $config = $app['config']->get('services.telegram');
            $voiceConfig = $app['config']->get('services.telegram_voice', []);

            return new TelegramService(
                botToken: (string) ($config['bot_token'] ?? ''),
                webhookSecret: $config['webhook_secret'] ?? null,
                timeout: (int) ($config['timeout'] ?? 10),
                voiceTranscriber: new TelegramVoiceTranscriber(
                    enabled: (bool) ($voiceConfig['enabled'] ?? false),
                    apiKey: (string) ($voiceConfig['api_key'] ?? ''),
                    apiUrl: (string) ($voiceConfig['api_url'] ?? 'https://api.openai.com/v1/audio/transcriptions'),
                    model: (string) ($voiceConfig['model'] ?? 'whisper-1'),
                    timeoutSeconds: (int) ($voiceConfig['timeout'] ?? 45),
                    language: $voiceConfig['language'] ?? null,
                ),
                provider: $app->make(BaseProvider::class),
                agent: $app->make(BaseAgent::class),
                modelName: (string) $app['config']->get('services.ollama.model', 'llama3.2'),
                historyLimit: (int) ($config['history_limit'] ?? 30),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
