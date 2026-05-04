<?php

namespace App\AI\Audio;

use RuntimeException;

class AudioConversionException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly array $command = [],
        private readonly ?int $exitCode = null,
        private readonly string $stdout = '',
        private readonly string $stderr = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function command(): array
    {
        return $this->command;
    }

    public function exitCode(): ?int
    {
        return $this->exitCode;
    }

    public function stdout(): string
    {
        return $this->stdout;
    }

    public function stderr(): string
    {
        return $this->stderr;
    }
}
