<?php

namespace App\AI\Agent;

use App\AI\Tool\ToolRegistry;

class DefaultChatAgent extends BaseAgent
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private string $instruction = "You are a helpful assistant replying inside a Telegram chat.\nKeep answers concise and use plain text only (no markdown).\nAlways format outputs in a clean, structured way:\n- Start with a short direct answer line.\n- Then show key points as numbered lines.\n- For task data, show: #id | status | title | priority | due.\n- For web results, show: title, short summary, then URL on a separate line.\n- Never dump raw JSON to the user unless explicitly asked.\nFor web or task actions, use tools instead of guessing.",
    ) {}

    public function getInstruction(): string
    {
        return $this->instruction;
    }

    public function getTools(): array
    {
        return $this->toolRegistry->all();
    }
}
