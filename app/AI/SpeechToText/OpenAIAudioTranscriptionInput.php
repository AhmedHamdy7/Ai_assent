<?php

namespace App\AI\SpeechToText;

use InvalidArgumentException;

class OpenAIAudioTranscriptionInput
{
    public const MAX_BYTES = 25 * 1024 * 1024;

    public static function supports(string $filePath, string $mimeType = 'audio/ogg'): bool
    {
        return self::supportedExtensionFromPath($filePath) !== null
            || self::supportedExtensionFromMimeType($mimeType) !== null;
    }

    /**
     * @return array{file_name: string, mime_type: string}
     */
    public static function prepare(string $filePath, string $mimeType = 'audio/ogg'): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new InvalidArgumentException("OpenAI speech-to-text could not read audio file [{$filePath}].");
        }

        $size = filesize($filePath);

        if ($size !== false && $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('OpenAI speech-to-text accepts files up to 25 MB.');
        }

        $extension = self::supportedExtensionFromPath($filePath)
            ?? self::supportedExtensionFromMimeType($mimeType);

        if ($extension === null) {
            $normalizedMimeType = self::normalizeMimeType($mimeType) ?? 'unknown';

            throw new InvalidArgumentException(
                "OpenAI speech-to-text does not support [{$normalizedMimeType}] inputs without transcoding. Supported formats: mp3, mp4, mpeg, mpga, m4a, wav, webm."
            );
        }

        return [
            'file_name' => self::fileName($filePath, $extension),
            'mime_type' => self::mimeTypeForExtension($extension),
        ];
    }

    private static function supportedExtensionFromPath(string $filePath): ?string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return self::supportedExtension($extension);
    }

    private static function supportedExtensionFromMimeType(string $mimeType): ?string
    {
        return match (self::normalizeMimeType($mimeType)) {
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/mp4', 'video/mp4' => 'mp4',
            'audio/x-m4a' => 'm4a',
            'audio/wav', 'audio/wave', 'audio/x-wav', 'audio/vnd.wave' => 'wav',
            'audio/webm' => 'webm',
            default => null,
        };
    }

    private static function supportedExtension(string $extension): ?string
    {
        return in_array($extension, ['mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm'], true)
            ? $extension
            : null;
    }

    private static function fileName(string $filePath, string $extension): string
    {
        $baseName = pathinfo($filePath, PATHINFO_BASENAME);

        if ($baseName !== '' && strtolower(pathinfo($baseName, PATHINFO_EXTENSION)) === $extension) {
            return $baseName;
        }

        $name = pathinfo($filePath, PATHINFO_FILENAME);
        $name = $name !== '' ? $name : 'audio-input';

        return $name . '.' . $extension;
    }

    private static function mimeTypeForExtension(string $extension): string
    {
        return match ($extension) {
            'mp3', 'mpeg', 'mpga' => 'audio/mpeg',
            'mp4', 'm4a' => 'audio/mp4',
            'wav' => 'audio/wav',
            'webm' => 'audio/webm',
            default => 'application/octet-stream',
        };
    }

    private static function normalizeMimeType(string $mimeType): ?string
    {
        $mimeType = strtolower(trim((string) strtok($mimeType, ';')));

        return $mimeType !== '' ? $mimeType : null;
    }
}
