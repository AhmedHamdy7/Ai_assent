<?php

namespace App\AI\Agent;

class DefaultChatAgent extends BaseAgent
{
    public function __construct(
        private string $instruction = 'You are a helpful assistant replying inside a Telegram chat. Keep answers concise and use plain text (no markdown).',
    ) {}

    public function getInstruction(): string
    {
        return $this->instruction;
    }

    public function getTools(): array
    {
        return [];
    }
}
