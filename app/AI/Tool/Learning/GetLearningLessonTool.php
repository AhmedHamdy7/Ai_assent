<?php

namespace App\AI\Tool\Learning;

use App\AI\Provider\ToolResult;

class GetLearningLessonTool extends AbstractLearningTool
{
    public function getName(): string
    {
        return 'get_learning_lesson';
    }

    public function getDescription(): string
    {
        return 'Get the next or a specific daily lesson from a study track.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'track_id' => ['type' => 'integer', 'minimum' => 1],
                'day_number' => ['type' => 'integer', 'minimum' => 1],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $track = $this->resolveTrack(
            isset($arguments['track_id']) ? (int) $arguments['track_id'] : null,
            $context
        );

        if ($track === null) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'Learning track not found.']);
        }

        $lessons = $track->lessons->sortBy('day_number');
        $dayNumber = isset($arguments['day_number']) ? (int) $arguments['day_number'] : null;

        $lesson = $dayNumber !== null
            ? $lessons->firstWhere('day_number', $dayNumber)
            : $lessons->firstWhere('status', 'pending');

        $lesson ??= $lessons->last();

        if ($lesson === null) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'No lessons found for this track.']);
        }

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => "Lesson for day {$lesson->day_number} is ready.",
            'track' => $this->serializeTrack($track),
            'lesson' => $this->serializeLesson($lesson),
        ]);
    }
}
