<?php

namespace App\AI\Tool\Task;

use App\AI\Provider\ToolResult;
use App\Models\AiTask;
use Carbon\Carbon;

class UpdateTaskTool extends AbstractTaskTool
{
    public function getName(): string
    {
        return 'update_task';
    }

    public function getDescription(): string
    {
        return 'Update task fields like title, details, status, priority, or due date.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'minimum' => 1],
                'title' => ['type' => 'string'],
                'details' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => [AiTask::STATUS_PENDING, AiTask::STATUS_IN_PROGRESS, AiTask::STATUS_DONE, AiTask::STATUS_CANCELLED]],
                'priority' => ['type' => 'string', 'enum' => [AiTask::PRIORITY_LOW, AiTask::PRIORITY_MEDIUM, AiTask::PRIORITY_HIGH]],
                'due_at' => ['type' => 'string', 'description' => 'ISO-8601/natural date; use empty string to clear'],
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

        $updated = false;

        if (array_key_exists('title', $arguments)) {
            $title = $this->cleanText((string) $arguments['title'], 255);
            if ($title === null) {
                return ToolResult::fromPayload(['ok' => false, 'message' => 'title cannot be empty']);
            }
            $task->title = $title;
            $updated = true;
        }

        if (array_key_exists('details', $arguments)) {
            $task->details = $this->cleanText((string) $arguments['details'], 4000);
            $updated = true;
        }

        if (array_key_exists('priority', $arguments)) {
            $priority = (string) $arguments['priority'];
            if (!in_array($priority, [AiTask::PRIORITY_LOW, AiTask::PRIORITY_MEDIUM, AiTask::PRIORITY_HIGH], true)) {
                return ToolResult::fromPayload(['ok' => false, 'message' => 'Invalid priority']);
            }
            $task->priority = $priority;
            $updated = true;
        }

        if (array_key_exists('status', $arguments)) {
            $status = (string) $arguments['status'];
            if (!in_array($status, [AiTask::STATUS_PENDING, AiTask::STATUS_IN_PROGRESS, AiTask::STATUS_DONE, AiTask::STATUS_CANCELLED], true)) {
                return ToolResult::fromPayload(['ok' => false, 'message' => 'Invalid status']);
            }

            $task->status = $status;
            $task->completed_at = $status === AiTask::STATUS_DONE ? Carbon::now() : null;
            $updated = true;
        }

        if (array_key_exists('due_at', $arguments)) {
            $rawDueAt = trim((string) $arguments['due_at']);
            if ($rawDueAt === '') {
                $task->due_at = null;
                $updated = true;
            } else {
                $dueAt = $this->parseDateTime($rawDueAt);
                if ($dueAt === null) {
                    return ToolResult::fromPayload(['ok' => false, 'message' => 'Invalid due_at format']);
                }
                $task->due_at = $dueAt;
                $updated = true;
            }
        }

        if (! $updated) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'No valid fields to update']);
        }

        $task->save();

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => "Task #{$task->id} updated.",
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
