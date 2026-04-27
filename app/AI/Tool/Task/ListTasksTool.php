<?php

namespace App\AI\Tool\Task;

use App\AI\Provider\ToolResult;
use App\Models\AiTask;

class ListTasksTool extends AbstractTaskTool
{
    public function getName(): string
    {
        return 'list_tasks';
    }

    public function getDescription(): string
    {
        return 'List tasks for the current chat session, optionally filtered by status.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => [AiTask::STATUS_PENDING, AiTask::STATUS_IN_PROGRESS, AiTask::STATUS_DONE, AiTask::STATUS_CANCELLED, 'all'],
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $status = (string) ($arguments['status'] ?? 'all');
        $limit = (int) ($arguments['limit'] ?? 20);
        $limit = max(1, min($limit, 50));

        $query = AiTask::query()
            ->forSession($this->resolveSessionId($context))
            ->orderByRaw("case when status = 'done' then 1 when status = 'cancelled' then 2 else 0 end")
            ->orderBy('due_at')
            ->orderByDesc('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $tasks = $query->limit($limit)->get();

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => $tasks->isEmpty()
                ? 'No tasks found.'
                : 'Found ' . $tasks->count() . ' task(s).',
            'tasks' => $tasks->map(fn (AiTask $task) => $this->serializeTask($task))->values()->all(),
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
