<?php

namespace App\AI\Provider;

class AiModel
{
    /**
     * @param AiToolCall[] $toolCalls
     */
    public function __construct(
        public string $model,
        public string $role,
        public string $content,
        public array $toolCalls,
        public bool $done,
    ) {}

    public static function fromOllama(array $data): self
    {
        $toolCalls = array_map(
            fn (array $tc) => AiToolCall::fromOllama($tc),
            $data['message']['tool_calls'] ?? []
        );

        return new self(
            $data['model'],
            $data['message']['role'],
            $data['message']['content'] ?? '',
            $toolCalls,
            $data['done'] ?? false
        );
    }

    public static function fromOpenAI(array $data): self
    {
        $choice = $data['choices'][0] ?? [];
        $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];
        $toolCalls = array_map(
            fn (array $tc) => AiToolCall::fromOpenAI($tc),
            is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : []
        );

        return new self(
            (string) ($data['model'] ?? ''),
            (string) ($message['role'] ?? 'assistant'),
            self::contentToString($message['content'] ?? ''),
            $toolCalls,
            isset($choice['finish_reason'])
        );
    }

    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    public function isComplete(): bool
    {
        return $this->done;
    }

    private static function contentToString(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return '';
        }

        $parts = [];

        foreach ($content as $part) {
            if (! is_array($part)) {
                continue;
            }

            $text = $part['text'] ?? null;

            if (is_string($text) && trim($text) !== '') {
                $parts[] = $text;
            }
        }

        return implode("\n", $parts);
    }
}
