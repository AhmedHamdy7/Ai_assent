<?php

namespace App\AI\Messaging\Telegram;

use RuntimeException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class TelegramVoiceTranscriber
{
    public function __construct(
        private readonly bool $enabled,
        private readonly ?string $apiKey,
        private readonly string $apiUrl,
        private readonly string $model,
        private readonly int $timeoutSeconds = 45,
        private readonly ?string $language = null,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function transcribe(string $binaryAudio, string $filename = 'voice.ogg'): string
    {
        if (! $this->enabled) {
            throw new RuntimeException('Voice transcription is disabled');
        }

        if (trim((string) $this->apiKey) === '') {
            throw new RuntimeException('Voice transcription API key is missing');
        }

        $payload = [
            'model' => $this->model,
            'response_format' => 'json',
        ];

        if (is_string($this->language) && trim($this->language) !== '') {
            $payload['language'] = trim($this->language);
        }

        $response = Http::withToken((string) $this->apiKey)
            ->acceptJson()
            ->timeout($this->timeoutSeconds)
            ->attach('file', $binaryAudio, $filename)
            ->post($this->apiUrl, $payload);

        try {
            $response->throw();
        } catch (RequestException $e) {
            $body = trim((string) $e->response?->body());
            $details = $body !== '' ? $body : $e->getMessage();
            throw new RuntimeException('Voice transcription request failed: ' . $details, previous: $e);
        }

        $json = $response->json();
        $text = trim((string) ($json['text'] ?? ''));

        if ($text === '') {
            throw new RuntimeException('Transcription returned empty text');
        }

        return $text;
    }
}
