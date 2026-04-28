<?php

namespace Tests\Feature\AI;

use App\AI\Learning\CurriculumBuilder;
use App\AI\Provider\AiSession;
use App\AI\Tool\Learning\CreateLearningTrackTool;
use App\AI\Tool\Learning\GetLearningLessonTool;
use App\AI\Tool\Learning\GetLearningProgressTool;
use App\AI\Tool\Learning\SubmitLearningQuizTool;
use App\Models\AiLearningLesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_learning_track_with_daily_lessons(): void
    {
        $session = AiSession::query()->create(['title' => 'Test session']);
        $tool = new CreateLearningTrackTool(new CurriculumBuilder());

        $result = $tool->execute([
            'topic' => 'Docker',
            'goal' => 'Deploy a small app with Compose',
            'duration_days' => 30,
            'daily_minutes' => 45,
            'level' => 'beginner',
        ], [
            'ai_session_id' => $session->id,
        ]);

        $payload = $result->payload;

        $this->assertTrue($payload['ok']);
        $this->assertSame('Docker', $payload['track']['topic']);
        $this->assertSame(30, $payload['track']['duration_days']);
        $this->assertCount(3, $payload['preview_lessons']);
        $this->assertDatabaseCount('ai_learning_tracks', 1);
        $this->assertDatabaseCount('ai_learning_lessons', 30);
    }

    public function test_it_returns_next_lesson_and_grades_quiz_attempts(): void
    {
        $session = AiSession::query()->create(['title' => 'Learning session']);
        $createTool = new CreateLearningTrackTool(new CurriculumBuilder());
        $createTool->execute([
            'topic' => 'English',
            'duration_days' => 30,
        ], [
            'ai_session_id' => $session->id,
        ]);

        $lesson = AiLearningLesson::query()->orderBy('day_number')->firstOrFail();

        $lessonTool = new GetLearningLessonTool();
        $lessonPayload = $lessonTool->execute([
            'track_id' => $lesson->track_id,
        ], [
            'ai_session_id' => $session->id,
        ])->payload;

        $this->assertTrue($lessonPayload['ok']);
        $this->assertSame(1, $lessonPayload['lesson']['day_number']);

        $quiz = $lesson->quiz;
        $answers = array_map(
            fn (array $question) => 'My answer includes ' . $question['answer'],
            $quiz['questions']
        );

        $submitTool = new SubmitLearningQuizTool();
        $submitPayload = $submitTool->execute([
            'lesson_id' => $lesson->id,
            'answers' => $answers,
        ], [
            'ai_session_id' => $session->id,
        ])->payload;

        $this->assertTrue($submitPayload['ok']);
        $this->assertTrue($submitPayload['result']['passed']);
        $this->assertSame(100, $submitPayload['result']['score_percentage']);

        $progressTool = new GetLearningProgressTool();
        $progressPayload = $progressTool->execute([
            'track_id' => $lesson->track_id,
        ], [
            'ai_session_id' => $session->id,
        ])->payload;

        $this->assertTrue($progressPayload['ok']);
        $this->assertSame(1, $progressPayload['track']['completed_lessons']);
        $this->assertNotNull($progressPayload['next_lesson']);
        $this->assertDatabaseCount('ai_learning_quiz_attempts', 1);
    }
}
