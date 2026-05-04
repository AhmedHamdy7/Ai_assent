<?php

namespace App\AI\Tool\Learning;

use App\AI\Provider\ToolResult;
use App\Models\AiLearningLesson;
use App\Models\AiLearningQuizAttempt;
use App\Models\AiLearningTrack;
use Carbon\Carbon;

class SubmitLearningQuizTool extends AbstractLearningTool
{
    public function getName(): string
    {
        return 'submit_learning_quiz';
    }

    public function eventAction(): string
    {
        return 'Submitting a quiz answer set';
    }

    public function getDescription(): string
    {
        return 'Grade a user quiz submission for a lesson, save the attempt, and update progress.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'lesson_id' => ['type' => 'integer', 'minimum' => 1],
                'answers' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Answers in the same order as the quiz questions',
                ],
            ],
            'required' => ['lesson_id', 'answers'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $lessonId = (int) ($arguments['lesson_id'] ?? 0);
        $answers = array_values(array_map(
            fn ($answer) => trim((string) $answer),
            is_array($arguments['answers'] ?? null) ? $arguments['answers'] : []
        ));

        if ($lessonId <= 0) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'lesson_id is required']);
        }

        if ($answers === []) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'answers are required']);
        }

        $lesson = AiLearningLesson::query()->with('track.lessons')->find($lessonId);
        if ($lesson === null) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'Lesson not found.']);
        }

        $track = $lesson->track;
        if ($track === null) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'Lesson track not found.']);
        }

        $sessionId = $this->resolveSessionId($context);
        if ($sessionId !== null && (int) $track->ai_session_id !== $sessionId) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'Lesson does not belong to this chat session.']);
        }

        $quiz = $lesson->quiz ?? [];
        $questions = is_array($quiz['questions'] ?? null) ? $quiz['questions'] : [];
        if ($questions === []) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'This lesson has no quiz to grade.']);
        }

        $graded = [];
        $correctCount = 0;

        foreach ($questions as $index => $question) {
            $expected = strtolower(trim((string) ($question['answer'] ?? '')));
            $answer = strtolower(trim((string) ($answers[$index] ?? '')));
            $isCorrect = $answer !== '' && $expected !== '' && str_contains($answer, $expected);

            if ($isCorrect) {
                $correctCount++;
            }

            $graded[] = [
                'question' => $question['prompt'] ?? ('Question ' . ($index + 1)),
                'expected_keyword' => $question['answer'] ?? null,
                'user_answer' => $answers[$index] ?? '',
                'correct' => $isCorrect,
                'feedback' => $isCorrect
                    ? 'Correct.'
                    : (string) ($question['explanation'] ?? 'Review this concept and try again.'),
            ];
        }

        $score = (int) round(($correctCount / max(count($questions), 1)) * 100);
        $passingScore = (int) ($quiz['passing_score'] ?? 60);
        $passed = $score >= $passingScore;

        AiLearningQuizAttempt::query()->create([
            'track_id' => $track->id,
            'lesson_id' => $lesson->id,
            'answers' => $answers,
            'score_percentage' => $score,
            'passed' => $passed,
            'feedback' => $passed ? 'Passed the lesson quiz.' : 'Quiz needs another attempt.',
        ]);

        $lesson->last_score = $score;
        if ($passed) {
            $lesson->status = AiLearningLesson::STATUS_COMPLETED;
            $lesson->completed_at = Carbon::now();
        }
        $lesson->save();

        $track->last_activity_at = Carbon::now();
        $remainingLessons = $track->lessons->where('id', '!=', $lesson->id)->where('status', '!=', 'completed')->count();
        if ($passed && $remainingLessons === 0) {
            $track->status = AiLearningTrack::STATUS_COMPLETED;
            $track->completed_at = Carbon::now();
        }
        $track->save();
        $track->refresh()->load('lessons');

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => $passed
                ? "Quiz passed with {$score}%."
                : "Quiz scored {$score}%. Review the weak answers and retry.",
            'track' => $this->serializeTrack($track),
            'lesson' => $this->serializeLesson($lesson, includeAnswers: false),
            'result' => [
                'score_percentage' => $score,
                'passed' => $passed,
                'graded_answers' => $graded,
            ],
        ]);
    }
}
