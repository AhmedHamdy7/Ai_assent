<?php

namespace App\AI\Tool\Task;

use App\AI\Provider\ToolResult;
use App\Models\AiTask;

class DeleteTaskTool extends AbstractTaskTool
{
    public function getName(): string
    {
        return 'delete_task';
    }

    public function getDescription(): string
    {
        return 'Delete a task by id from the current chat session.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['id'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $taskId = (int) ($arguments['id'] ?? 0);
        if ($taskId <= 0) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'id is required']);
        }

        $task = AiTask::query()
            ->forSession($this->resolveSessionId($context))
            ->whereKey($taskId)
            ->first();

        if ($task === null) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'Task not found']);
        }

        $title = $task->title;
        $task->delete();

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => "Task #{$taskId} deleted: {$title}",
        ]);
    }
}
