<?php

namespace App\AI\Messaging\Telegram;

use RuntimeException;

class TelegramApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $errorCode = 0,
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $errorCode);
    }

    public function errorCode(): int
    {
        return $this->errorCode;
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function isParseError(): bool
    {
        return $this->errorCode === 400 && str_contains(strtolower($this->getMessage()), "can't parse");
    }

    public function isNotModified(): bool
    {
        return $this->errorCode === 400 && str_contains(strtolower($this->getMessage()), 'message is not modified');
    }

    public function isRetryable(): bool
    {
        return $this->errorCode === 429 || $this->errorCode >= 500;
    }
}
