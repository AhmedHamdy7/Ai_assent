<?php

namespace App\AI\Provider;

class AiToolCall
{
    public function __construct(
        public string $name,
        public array $arguments,
        public ?string $id = null,
    ) {}

    public static function fromOllama(array $data): self
    {
        return new self(
            $data['function']['name'],
            (array) ($data['function']['arguments'] ?? [])
        );
    }

    public static function fromOpenAI(array $data): self
    {
        $arguments = $data['function']['arguments'] ?? [];

        if (is_string($arguments)) {
            $decoded = json_decode($arguments, true);
            $arguments = is_array($decoded) ? $decoded : [];
        }

        return new self(
            (string) ($data['function']['name'] ?? ''),
            is_array($arguments) ? $arguments : [],
            isset($data['id']) ? (string) $data['id'] : null,
        );
    }
}
