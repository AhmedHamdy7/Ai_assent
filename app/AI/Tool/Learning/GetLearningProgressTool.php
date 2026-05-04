<?php

namespace App\AI\Tool\Learning;

use App\AI\Provider\ToolResult;

class GetLearningProgressTool extends AbstractLearningTool
{
    public function getName(): string
    {
        return 'get_learning_progress';
    }

    public function eventAction(): string
    {
        return 'Checking learning progress';
    }

    public function getDescription(): string
    {
        return 'Summarize progress for a study track including completion percent and next lesson.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'track_id' => ['type' => 'integer', 'minimum' => 1],
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

        $track->load(['lessons', 'quizAttempts' => fn ($query) => $query->latest('id')->limit(5)]);
        $nextLesson = $track->lessons->where('status', '!=', 'completed')->sortBy('day_number')->first();

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => "Progress loaded for {$track->topic}.",
            'track' => $this->serializeTrack($track),
            'next_lesson' => $nextLesson !== null ? $this->serializeLesson($nextLesson) : null,
            'recent_attempts' => $track->quizAttempts->map(fn ($attempt) => [
                'lesson_id' => (int) $attempt->lesson_id,
                'score_percentage' => (int) $attempt->score_percentage,
                'passed' => (bool) $attempt->passed,
                'feedback' => $attempt->feedback,
                'created_at' => optional($attempt->created_at)->toIso8601String(),
            ])->values()->all(),
        ]);
    }
}
