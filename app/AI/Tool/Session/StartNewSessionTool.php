<?php

namespace App\AI\Tool\Session;

use App\AI\Provider\AiSession;
use App\AI\Provider\ToolResult;
use App\AI\Tool\BaseTool;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StartNewSessionTool extends BaseTool
{
    public function getName(): string
    {
        return 'start_new_session';
    }

    public function eventAction(): string
    {
        return 'Starting a new chat session';
    }

    public function getDescription(): string
    {
        return 'Start a fresh chat context from this point without deleting stored tasks, expenses, or old messages.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Optional title for the new session context',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $sessionId = Arr::get($context, 'ai_session_id');
        if (!is_numeric($sessionId)) {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => 'No active chat session found.',
            ]);
        }

        $session = AiSession::query()->find((int) $sessionId);
        if ($session === null) {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => 'Session not found.',
            ]);
        }

        $lastMessageId = $session->messages()->max('id');
        $messagesBeforeReset = $session->messages()->count();

        $title = trim((string) ($arguments['title'] ?? ''));
        if ($title !== '') {
            $session->title = Str::limit($title, 255, '...');
        }

        $session->token = (string) Str::uuid();
        $session->context_starts_after_message_id = $lastMessageId !== null ? (int) $lastMessageId : null;
        $session->compaction_count = ((int) ($session->compaction_count ?? 0)) + 1;
        $session->save();

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => 'Started a new session context without deleting stored data.',
            'session' => [
                'id' => (int) $session->id,
                'title' => $session->title,
                'token' => $session->token,
                'context_starts_after_message_id' => $session->context_starts_after_message_id,
                'messages_before_reset' => $messagesBeforeReset,
                'data_deleted' => false,
            ],
        ]);
    }
}
