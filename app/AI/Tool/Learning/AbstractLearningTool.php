<?php

namespace App\AI\Tool\Learning;

use App\AI\Tool\BaseTool;
use App\Models\AiLearningTrack;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

abstract class AbstractLearningTool extends BaseTool
{
    protected function resolveSessionId(array $context): ?int
    {
        $sessionId = Arr::get($context, 'ai_session_id');

        return is_numeric($sessionId) ? (int) $sessionId : null;
    }

    protected function cleanText(?string $value, int $limit = 2000): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $clean = trim($value);
        if ($clean === '') {
            return null;
        }

        return Str::limit($clean, $limit, '...');
    }

    protected function normalizeLevel(?string $level): string
    {
        $level = Str::lower(trim((string) $level));

        return in_array($level, ['beginner', 'intermediate', 'advanced'], true)
            ? $level
            : 'beginner';
    }

    protected function resolveTrack(?int $trackId, array $context): ?AiLearningTrack
    {
        $sessionId = $this->resolveSessionId($context);

        $query = AiLearningTrack::query()->with('lessons');
        if ($sessionId !== null) {
            $query->where('ai_session_id', $sessionId);
        }

        if ($trackId !== null) {
            return $query->whereKey($trackId)->first();
        }

        return $query
            ->orderByRaw("case when status = 'active' then 0 when status = 'paused' then 1 else 2 end")
            ->latest('id')
            ->first();
    }

    protected function serializeTrack(AiLearningTrack $track): array
    {
        $completedLessons = $track->lessons->where('status', 'completed')->count();
        $totalLessons = $track->lessons->count();
        $nextLesson = $track->lessons
            ->where('status', '!=', 'completed')
            ->sortBy('day_number')
            ->first();

        return [
            'id' => (int) $track->id,
            'topic' => $track->topic,
            'goal' => $track->goal,
            'level' => $track->level,
            'status' => $track->status,
            'duration_days' => (int) $track->duration_days,
            'daily_minutes' => (int) $track->daily_minutes,
            'summary' => $track->summary,
            'completed_lessons' => $completedLessons,
            'total_lessons' => $totalLessons,
            'progress_percent' => $totalLessons > 0 ? (int) round(($completedLessons / $totalLessons) * 100) : 0,
            'next_day' => $nextLesson?->day_number,
            'started_at' => optional($track->started_at)->toIso8601String(),
            'completed_at' => optional($track->completed_at)->toIso8601String(),
        ];
    }

    protected function serializeLesson(\App\Models\AiLearningLesson $lesson, bool $includeAnswers = false): array
    {
        $quiz = $lesson->quiz ?? [];
        if (! $includeAnswers && isset($quiz['questions']) && is_array($quiz['questions'])) {
            $quiz['questions'] = array_map(function (array $question): array {
                unset($question['answer']);

                return $question;
            }, $quiz['questions']);
        }

        return [
            'id' => (int) $lesson->id,
            'day_number' => (int) $lesson->day_number,
            'title' => $lesson->title,
            'objective' => $lesson->objective,
            'lesson_body' => $lesson->lesson_body,
            'practice_task' => $lesson->practice_task,
            'resource_hint' => $lesson->resource_hint,
            'status' => $lesson->status,
            'last_score' => $lesson->last_score,
            'quiz' => $quiz,
            'completed_at' => optional($lesson->completed_at)->toIso8601String(),
        ];
    }
}
