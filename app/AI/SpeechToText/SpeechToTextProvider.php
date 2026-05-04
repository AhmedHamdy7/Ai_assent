<?php

namespace App\AI\SpeechToText;

interface SpeechToTextProvider
{
    public function transcribe(string $filePath, string $mimeType = 'audio/ogg'): string;
}
