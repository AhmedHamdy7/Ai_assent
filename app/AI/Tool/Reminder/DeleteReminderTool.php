<?php

namespace App\AI\Tool\Reminder;

use App\AI\Provider\ToolResult;
use App\Models\AiReminder;

class DeleteReminderTool extends AbstractReminderTool
{
    public function getName(): string
    {
        return 'delete_reminder';
    }

    public function eventAction(): string
    {
        return 'Deleting a reminder';
    }

    public function getDescription(): string
    {
        return 'Cancel and delete an active reminder by its ID.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reminder_id' => [
                    'type'        => 'integer',
                    'description' => 'The ID of the reminder to delete',
                ],
            ],
            'required'             => ['reminder_id'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $sessionId  = $this->resolveSessionId($context);
        $reminderId = isset($arguments['reminder_id']) ? (int) $arguments['reminder_id'] : 0;

        $reminder = AiReminder::query()
            ->forSession($sessionId)
            ->where('id', $reminderId)
            ->first();

        if ($reminder === null) {
            return ToolResult::fromPayload(['ok' => false, 'message' => "Reminder #{$reminderId} not found"]);
        }

        $reminder->delete();

        return ToolResult::fromPayload([
            'ok'      => true,
            'message' => "Reminder #{$reminderId} deleted successfully",
        ]);
    }
}
