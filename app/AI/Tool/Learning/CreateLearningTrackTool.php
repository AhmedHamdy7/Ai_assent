<?php

namespace App\AI\Tool\Learning;

use App\AI\Learning\CurriculumBuilder;
use App\AI\Provider\ToolResult;
use App\Models\AiLearningLesson;
use App\Models\AiLearningTrack;
use Carbon\Carbon;

class CreateLearningTrackTool extends AbstractLearningTool
{
    public function __construct(
        private readonly CurriculumBuilder $curriculumBuilder,
    ) {}

    public function getName(): string
    {
        return 'create_learning_track';
    }

    public function eventAction(): string
    {
        return 'Creating a learning track';
    }

    public function getDescription(): string
    {
        return 'Create a structured study track with daily lessons and quizzes for a topic like Docker or English.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topic' => ['type' => 'string', 'description' => 'What to learn'],
                'goal' => ['type' => 'string', 'description' => 'Optional target outcome'],
                'duration_days' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 90],
                'daily_minutes' => ['type' => 'integer', 'minimum' => 10, 'maximum' => 180],
                'level' => ['type' => 'string', 'enum' => ['beginner', 'intermediate', 'advanced']],
            ],
            'required' => ['topic'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $topic = $this->cleanText((string) ($arguments['topic'] ?? ''), 255);
        if ($topic === null) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'topic is required']);
        }

        $durationDays = max(7, min((int) ($arguments['duration_days'] ?? 30), 90));
        $dailyMinutes = max(10, min((int) ($arguments['daily_minutes'] ?? 30), 180));
        $level = $this->normalizeLevel((string) ($arguments['level'] ?? 'beginner'));
        $goal = $this->cleanText(isset($arguments['goal']) ? (string) $arguments['goal'] : null, 500);

        $plan = $this->curriculumBuilder->build(
            topic: $topic,
            durationDays: $durationDays,
            level: $level,
            goal: $goal,
            dailyMinutes: $dailyMinutes,
        );

        $track = AiLearningTrack::query()->create([
            'ai_session_id' => $this->resolveSessionId($context),
            'topic' => $topic,
            'goal' => $goal,
            'level' => $level,
            'status' => AiLearningTrack::STATUS_ACTIVE,
            'duration_days' => $durationDays,
            'daily_minutes' => $dailyMinutes,
            'template' => $plan['template'],
            'summary' => $plan['summary'],
            'started_at' => Carbon::now(),
            'last_activity_at' => Carbon::now(),
        ]);

        foreach ($plan['lessons'] as $lesson) {
            AiLearningLesson::query()->create([
                'track_id' => $track->id,
                'day_number' => $lesson['day_number'],
                'title' => $lesson['title'],
                'objective' => $lesson['objective'],
                'lesson_body' => $lesson['lesson_body'],
                'practice_task' => $lesson['practice_task'],
                'resource_hint' => $lesson['resource_hint'],
                'quiz' => $lesson['quiz'],
                'status' => AiLearningLesson::STATUS_PENDING,
            ]);
        }

        $track->load('lessons');

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => "Learning track #{$track->id} created for {$track->topic}.",
            'track' => $this->serializeTrack($track),
            'preview_lessons' => $track->lessons
                ->sortBy('day_number')
                ->take(3)
                ->map(fn (AiLearningLesson $lesson) => $this->serializeLesson($lesson))
                ->values()
                ->all(),
        ]);
    }
}
