<?php

namespace App\AI\SpeechToText;

use Illuminate\Support\Facades\Http;
use App\AI\Audio\AudioConverter;
use RuntimeException;

class OpenAISpeechToText implements SpeechToTextProvider
{
    public const DEFAULT_API_URL = 'https://api.openai.com/v1/audio/transcriptions';

    public const DEFAULT_MODEL = 'gpt-4o-transcribe';

    public function __construct(
        private string $apiKey,
        private string $apiUrl = self::DEFAULT_API_URL,
        private string $model = self::DEFAULT_MODEL,
        private ?AudioConverter $audioConverter = null,
        private string $transcodeTargetFormat = 'mp3',
        private ?string $language = 'ar',
    ) {}

    public function transcribe(string $filePath, string $mimeType = 'audio/ogg'): string
    {
        $preparedInput = $this->prepareInputForTranscription($filePath, $mimeType);
        $stream = null;

        try {
            $upload = OpenAIAudioTranscriptionInput::prepare($preparedInput['file_path'], $preparedInput['mime_type']);
            $stream = fopen($preparedInput['file_path'], 'r');

            if ($stream === false) {
                throw new RuntimeException("Unable to open audio file [{$preparedInput['file_path']}] for transcription.");
            }

            $payload = array_filter([
                'model' => $this->model,
                'language' => $this->normalizedLanguage(),
            ], static fn (mixed $value): bool => $value !== null);

            $response = Http::withToken($this->apiKey)
                ->timeout(120)
                ->attach('file', $stream, $upload['file_name'], [
                    'Content-Type' => $upload['mime_type'],
                ])
                ->post($this->apiUrl, $payload);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            $this->cleanupTemporaryFile($preparedInput['cleanup_path']);
        }

        $response->throw();

        return trim((string) ($response->json('text') ?? ''));
    }

    /**
     * @return array{file_path: string, mime_type: string, cleanup_path: ?string}
     */
    private function prepareInputForTranscription(string $filePath, string $mimeType): array
    {
        if (OpenAIAudioTranscriptionInput::supports($filePath, $mimeType)) {
            return [
                'file_path' => $filePath,
                'mime_type' => $mimeType,
                'cleanup_path' => null,
            ];
        }

        if ($this->audioConverter === null) {
            OpenAIAudioTranscriptionInput::prepare($filePath, $mimeType);
        }

        $targetFormat = strtolower(trim($this->transcodeTargetFormat));
        $convertedPath = $this->audioConverter?->convert($filePath, $targetFormat);

        if (! is_string($convertedPath) || $convertedPath === '') {
            throw new RuntimeException("Unable to transcode audio file [{$filePath}] for OpenAI speech-to-text.");
        }

        return [
            'file_path' => $convertedPath,
            'mime_type' => $this->mimeTypeForFormat($targetFormat),
            'cleanup_path' => $convertedPath,
        ];
    }

    private function mimeTypeForFormat(string $format): string
    {
        return match ($format) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'mp4', 'm4a' => 'audio/mp4',
            'webm' => 'audio/webm',
            default => 'application/octet-stream',
        };
    }

    private function normalizedLanguage(): ?string
    {
        $language = trim((string) $this->language);

        return $language !== '' ? $language : null;
    }

    private function cleanupTemporaryFile(?string $path): void
    {
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }
}
