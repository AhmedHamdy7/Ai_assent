<?php

namespace App\AI\Tool\Task;

use App\AI\Provider\ToolResult;
use App\Models\AiTask;

class CreateTaskTool extends AbstractTaskTool
{
    public function getName(): string
    {
        return 'create_task';
    }

    public function getDescription(): string
    {
        return 'Create a new task for the current chat session.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Task title'],
                'details' => ['type' => 'string', 'description' => 'Optional extra details'],
                'priority' => ['type' => 'string', 'enum' => [AiTask::PRIORITY_LOW, AiTask::PRIORITY_MEDIUM, AiTask::PRIORITY_HIGH]],
                'due_at' => ['type' => 'string', 'description' => 'Optional due date/time (ISO-8601 or natural date)'],
            ],
            'required' => ['title'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $title = $this->cleanText((string) ($arguments['title'] ?? ''), 255);
        if ($title === null) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'title is required']);
        }

        $priority = (string) ($arguments['priority'] ?? AiTask::PRIORITY_MEDIUM);
        if (!in_array($priority, [AiTask::PRIORITY_LOW, AiTask::PRIORITY_MEDIUM, AiTask::PRIORITY_HIGH], true)) {
            $priority = AiTask::PRIORITY_MEDIUM;
        }

        $dueAt = $this->parseDateTime(isset($arguments['due_at']) ? (string) $arguments['due_at'] : null);
        if (array_key_exists('due_at', $arguments) && $dueAt === null && trim((string) $arguments['due_at']) !== '') {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'Invalid due_at format']);
        }

        $task = AiTask::query()->create([
            'ai_session_id' => $this->resolveSessionId($context),
            'title' => $title,
            'details' => $this->cleanText(isset($arguments['details']) ? (string) $arguments['details'] : null, 4000),
            'status' => AiTask::STATUS_PENDING,
            'priority' => $priority,
            'due_at' => $dueAt,
        ]);

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => "Task #{$task->id} created: {$task->title}",
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
