<?php

namespace App\AI\SpeechToText;

class GroqSpeechToText implements SpeechToTextProvider
{
    public function __construct(
        private string $apiKey,
        private string $apiUrl = 'https://api.groq.com/openai/v1/audio/transcriptions',
        private string $model = 'whisper-large-v3',
    ) {}

    public function transcribe(string $filePath, string $mimeType = 'audio/ogg'): string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => [
                'file'  => new \CURLFile($filePath, $mimeType, 'voice.ogg'),
                'model' => $this->model,
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        return trim($result['text'] ?? '');
    }
}
