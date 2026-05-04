<?php

namespace App\AI\Tool\Task;

use App\AI\Provider\ToolResult;
use App\Models\AiTask;

class GetTaskTool extends AbstractTaskTool
{
    public function getName(): string
    {
        return 'get_task';
    }

    public function eventAction(): string
    {
        return 'Loading a task';
    }

    public function getDescription(): string
    {
        return 'Get a single task by id from the current chat session.';
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

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => "Task #{$task->id} loaded.",
            'task' => $this->serializeTask($task),
        ]);
    }

    private function serializeTask(AiTask $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'details' => $task->details,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_at' => optional($task->due_at)->toIso8601String(),
            'completed_at' => optional($task->completed_at)->toIso8601String(),
        ];
    }
}
