<?php

namespace App\Providers;

use App\AI\Agent\BaseAgent;
use App\AI\Agent\DefaultChatAgent;
use App\AI\Audio\AudioConverter;
use App\AI\Audio\FfmpegInstaller;
use App\AI\Messaging\Telegram\TelegramService;
use App\AI\Provider\BaseProvider;
use App\AI\Provider\OllamaProvider;
use App\AI\SpeechToText\GroqSpeechToText;
use App\AI\SpeechToText\OpenAISpeechToText;
use App\AI\SpeechToText\SpeechToTextProvider;
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

        $this->app->singleton(SpeechToTextProvider::class, function ($app) {
            $config = $app['config']->get('services.telegram_voice', []);
            $enabled = (bool) ($config['enabled'] ?? false);
            $apiKey = trim((string) ($config['api_key'] ?? ''));

            if (! $enabled || $apiKey === '') {
                return null;
            }

            $apiUrl = (string) ($config['api_url'] ?? OpenAISpeechToText::DEFAULT_API_URL);
            $model = (string) ($config['model'] ?? OpenAISpeechToText::DEFAULT_MODEL);
            $language = $config['language'] ?? null;

            if (str_contains(strtolower($apiUrl), 'groq.com')) {
                return new GroqSpeechToText(
                    apiKey: $apiKey,
                    apiUrl: $apiUrl,
                    model: $model !== '' ? $model : 'whisper-large-v3',
                );
            }

            return new OpenAISpeechToText(
                apiKey: $apiKey,
                apiUrl: $apiUrl !== '' ? $apiUrl : OpenAISpeechToText::DEFAULT_API_URL,
                model: $model !== '' ? $model : OpenAISpeechToText::DEFAULT_MODEL,
                audioConverter: $this->makeAudioConverter($app),
                transcodeTargetFormat: 'mp3',
                language: is_string($language) && trim($language) !== '' ? $language : null,
            );
        });

        $this->app->singleton(TelegramService::class, function ($app) {
            $config = $app['config']->get('services.telegram');

            return new TelegramService(
                botToken: (string) ($config['bot_token'] ?? ''),
                webhookSecret: $config['webhook_secret'] ?? null,
                timeout: (int) ($config['timeout'] ?? 10),
                provider: $app->make(BaseProvider::class),
                agent: $app->make(BaseAgent::class),
                modelName: (string) $app['config']->get('services.ollama.model', 'minimax-m2.5:cloud'),
                speechToTextProvider: $app->make(SpeechToTextProvider::class),
                historyLimit: (int) ($config['history_limit'] ?? 30),
            );
        });
    }

    public function boot(): void
    {
        //
    }

    private function makeAudioConverter($app): ?AudioConverter
    {
        $ffmpegConfig = (array) $app['config']->get('talk2flow-agentic.ffmpeg', []);
        $binaryPaths = (array) ($ffmpegConfig['binary_paths'] ?? []);
        $ffmpegPath = trim((string) ($binaryPaths['ffmpeg'] ?? ''));
        $ffprobePath = trim((string) ($binaryPaths['ffprobe'] ?? ''));

        if (($ffmpegPath === '' || $ffprobePath === '') && $app->bound(FfmpegInstaller::class)) {
            try {
                $resolvedPaths = $app->make(FfmpegInstaller::class)->resolvedBinaryPaths();
                $ffmpegPath = $ffmpegPath !== '' ? $ffmpegPath : trim((string) ($resolvedPaths['ffmpeg'] ?? ''));
                $ffprobePath = $ffprobePath !== '' ? $ffprobePath : trim((string) ($resolvedPaths['ffprobe'] ?? ''));
            } catch (\Throwable) {
                return null;
            }
        }

        if ($ffmpegPath === '' || $ffprobePath === '') {
            return null;
        }

        return new AudioConverter(
            ffmpegBinaryPath: $ffmpegPath,
            ffprobeBinaryPath: $ffprobePath,
            timeout: (int) ($ffmpegConfig['timeout'] ?? 120),
            overwrite: (bool) ($ffmpegConfig['overwrite'] ?? true),
            tempDirectory: (string) ($ffmpegConfig['temp_directory'] ?? ''),
        );
    }
}
