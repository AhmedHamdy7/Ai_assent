<?php

namespace App\AI\Tool\Reminder;

use App\AI\Provider\ToolResult;
use App\Models\AiReminder;

class ListRemindersTool extends AbstractReminderTool
{
    public function getName(): string
    {
        return 'list_reminders';
    }

    public function eventAction(): string
    {
        return 'Listing reminders';
    }

    public function getDescription(): string
    {
        return 'List all active reminders for the current session.';
    }

    public function getParameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => new \stdClass(),
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $sessionId = $this->resolveSessionId($context);

        $reminders = AiReminder::query()
            ->forSession($sessionId)
            ->active()
            ->orderBy('remind_at')
            ->get()
            ->map(fn (AiReminder $r) => $this->serializeReminder($r))
            ->values()
            ->all();

        return ToolResult::fromPayload([
            'ok'        => true,
            'count'     => count($reminders),
            'reminders' => $reminders,
        ]);
    }
}
