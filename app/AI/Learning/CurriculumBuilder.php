<?php

namespace App\AI\Learning;

use Illuminate\Support\Str;

class CurriculumBuilder
{
    public function build(
        string $topic,
        int $durationDays,
        string $level = 'beginner',
        ?string $goal = null,
        int $dailyMinutes = 30,
    ): array {
        $normalizedTopic = trim($topic);
        $template = $this->resolveTemplate($normalizedTopic);
        $moduleCount = count($template['modules']);
        $durationDays = max(7, min($durationDays, 90));
        $level = $this->normalizeLevel($level);

        $lessons = [];

        for ($day = 1; $day <= $durationDays; $day++) {
            $moduleIndex = min(
                $moduleCount - 1,
                (int) floor((($day - 1) * $moduleCount) / $durationDays)
            );

            $module = $template['modules'][$moduleIndex];
            $lessons[] = [
                'day_number' => $day,
                'title' => sprintf('Day %d - %s', $day, $module['title']),
                'objective' => $module['objective'],
                'lesson_body' => $this->buildLessonBody(
                    topic: $normalizedTopic,
                    templateName: $template['name'],
                    level: $level,
                    module: $module,
                    dailyMinutes: $dailyMinutes,
                    day: $day,
                    durationDays: $durationDays,
                    goal: $goal
                ),
                'practice_task' => $this->buildPracticeTask($template['name'], $module, $day),
                'resource_hint' => $module['resource_hint'],
                'quiz' => $this->buildQuiz($template['name'], $module, $day),
            ];
        }

        return [
            'topic' => $normalizedTopic,
            'template' => $template['name'],
            'goal' => $goal,
            'duration_days' => $durationDays,
            'daily_minutes' => $dailyMinutes,
            'level' => $level,
            'summary' => $this->buildSummary($normalizedTopic, $template['name'], $goal, $durationDays, $dailyMinutes),
            'lessons' => $lessons,
        ];
    }

    private function normalizeLevel(string $level): string
    {
        $level = Str::lower(trim($level));

        return in_array($level, ['beginner', 'intermediate', 'advanced'], true)
            ? $level
            : 'beginner';
    }

    private function resolveTemplate(string $topic): array
    {
        $normalized = Str::lower($topic);

        if (str_contains($normalized, 'docker') || str_contains($normalized, 'container')) {
            return [
                'name' => 'docker',
                'modules' => [
                    ['title' => 'Docker foundations', 'objective' => 'Understand containers, images, registries, and Docker Desktop/Engine setup.', 'resource_hint' => 'Official Docker docs: Get Started', 'keywords' => ['container', 'image', 'registry']],
                    ['title' => 'CLI basics', 'objective' => 'Run containers, inspect logs, enter shells, and manage lifecycle commands.', 'resource_hint' => 'Docker CLI reference', 'keywords' => ['run', 'ps', 'logs']],
                    ['title' => 'Images and Dockerfiles', 'objective' => 'Build custom images and learn layers, caching, and tagging.', 'resource_hint' => 'Dockerfile best practices', 'keywords' => ['dockerfile', 'build', 'tag']],
                    ['title' => 'Volumes and bind mounts', 'objective' => 'Persist data safely and understand container filesystem boundaries.', 'resource_hint' => 'Storage docs', 'keywords' => ['volume', 'bind mount', 'persistence']],
                    ['title' => 'Networking', 'objective' => 'Connect services with bridges, ports, DNS, and host/container networking.', 'resource_hint' => 'Networking overview', 'keywords' => ['port', 'bridge', 'dns']],
                    ['title' => 'Compose workflows', 'objective' => 'Model multi-service apps with Docker Compose and environment files.', 'resource_hint' => 'Compose specification', 'keywords' => ['compose', 'services', 'env']],
                    ['title' => 'Debugging and optimization', 'objective' => 'Reduce image size, troubleshoot startup issues, and inspect container state.', 'resource_hint' => 'Slim images and troubleshooting docs', 'keywords' => ['debug', 'inspect', 'optimize']],
                    ['title' => 'Production habits', 'objective' => 'Apply security, secrets, CI, and deployment basics for real projects.', 'resource_hint' => 'Docker production guides', 'keywords' => ['security', 'secrets', 'deploy']],
                ],
            ];
        }

        if (
            str_contains($normalized, 'english')
            || str_contains($normalized, 'انجل')
            || str_contains($normalized, 'english speaking')
        ) {
            return [
                'name' => 'english',
                'modules' => [
                    ['title' => 'Core routine', 'objective' => 'Build a daily English habit with short listening, reading, and speaking blocks.', 'resource_hint' => 'Use graded input and short daily repetition', 'keywords' => ['habit', 'routine', 'input']],
                    ['title' => 'Vocabulary themes', 'objective' => 'Learn topic-based vocabulary and use it in simple sentences.', 'resource_hint' => 'Use flashcards with example sentences', 'keywords' => ['vocabulary', 'topic', 'sentence']],
                    ['title' => 'Grammar in context', 'objective' => 'Practice grammar through examples instead of isolated rules only.', 'resource_hint' => 'Short grammar drills with spoken output', 'keywords' => ['grammar', 'tense', 'question']],
                    ['title' => 'Listening skills', 'objective' => 'Catch key ideas, transitions, and pronunciation patterns in audio.', 'resource_hint' => 'Shadow short audio clips', 'keywords' => ['listening', 'shadowing', 'pronunciation']],
                    ['title' => 'Speaking fluency', 'objective' => 'Answer common questions and speak in structured short responses.', 'resource_hint' => 'Record yourself and compare', 'keywords' => ['speaking', 'fluency', 'response']],
                    ['title' => 'Writing clarity', 'objective' => 'Write short messages, summaries, and self-introductions with fewer mistakes.', 'resource_hint' => 'Keep daily writing to one paragraph', 'keywords' => ['writing', 'summary', 'clarity']],
                    ['title' => 'Conversation practice', 'objective' => 'Handle practical conversations like work, travel, and small talk.', 'resource_hint' => 'Role-play common scenarios', 'keywords' => ['conversation', 'scenario', 'confidence']],
                    ['title' => 'Revision and retention', 'objective' => 'Review weak points and combine vocabulary, grammar, and fluency.', 'resource_hint' => 'Weekly spaced review', 'keywords' => ['review', 'retention', 'revision']],
                ],
            ];
        }

        return [
            'name' => 'general',
            'modules' => [
                ['title' => 'Orientation', 'objective' => 'Clarify fundamentals, vocabulary, and the scope of the topic.', 'resource_hint' => 'Start with official docs or a trusted intro source', 'keywords' => ['basics', 'scope', 'vocabulary']],
                ['title' => 'Core concepts', 'objective' => 'Understand the main building blocks and mental models.', 'resource_hint' => 'Learn concepts before tools', 'keywords' => ['concept', 'mental model', 'structure']],
                ['title' => 'Hands-on practice', 'objective' => 'Apply the topic on a small, repeatable exercise.', 'resource_hint' => 'Prefer short practical work daily', 'keywords' => ['practice', 'exercise', 'application']],
                ['title' => 'Problem solving', 'objective' => 'Use the topic to solve realistic tasks and common edge cases.', 'resource_hint' => 'Write down why each step works', 'keywords' => ['problem solving', 'case', 'reasoning']],
                ['title' => 'Review and mastery', 'objective' => 'Review progress, fill gaps, and strengthen recall.', 'resource_hint' => 'End each week with recap and self-test', 'keywords' => ['review', 'mastery', 'recall']],
            ],
        ];
    }

    private function buildSummary(string $topic, string $templateName, ?string $goal, int $durationDays, int $dailyMinutes): string
    {
        $goalText = $goal !== null && trim($goal) !== '' ? trim($goal) : 'build a strong practical foundation';

        return sprintf(
            'A %d-day %s study track for %s, optimized for %d minutes per day.',
            $durationDays,
            $templateName,
            $goalText,
            $dailyMinutes
        );
    }

    private function buildLessonBody(
        string $topic,
        string $templateName,
        string $level,
        array $module,
        int $dailyMinutes,
        int $day,
        int $durationDays,
        ?string $goal,
    ): string {
        $goalText = $goal !== null && trim($goal) !== '' ? 'Goal focus: ' . trim($goal) . '. ' : '';

        return sprintf(
            '%sTopic: %s. Level: %s. Today covers %s. Objective: %s. Spend about %d minutes split into learn, practice, and review. This is day %d of %d in the %s track.',
            $goalText,
            $topic,
            $level,
            Str::lower($module['title']),
            $module['objective'],
            $dailyMinutes,
            $day,
            $durationDays,
            $templateName
        );
    }

    private function buildPracticeTask(string $templateName, array $module, int $day): string
    {
        if ($templateName === 'docker') {
            return sprintf(
                'Create a small Docker exercise for day %d using %s. Write the commands you used and one mistake you debugged.',
                $day,
                Str::lower($module['title'])
            );
        }

        if ($templateName === 'english') {
            return sprintf(
                'Use today\'s English focus to write 5 sentences and record a 60-second spoken response about your day %d topic.',
                $day
            );
        }

        return sprintf(
            'Complete one short practical exercise for %s and write a 3-line recap of what you learned on day %d.',
            Str::lower($module['title']),
            $day
        );
    }

    private function buildQuiz(string $templateName, array $module, int $day): array
    {
        $focus = $module['keywords'][0] ?? Str::lower($module['title']);
        $secondary = $module['keywords'][1] ?? $focus;
        $tertiary = $module['keywords'][2] ?? $secondary;

        if ($templateName === 'english') {
            return [
                'passing_score' => 60,
                'questions' => [
                    [
                        'id' => 'q1',
                        'prompt' => "Use the word '{$focus}' in a short English sentence.",
                        'type' => 'contains',
                        'answer' => $focus,
                        'explanation' => 'The answer should show you can use the target word naturally.',
                    ],
                    [
                        'id' => 'q2',
                        'prompt' => "Name one idea from today's lesson about {$secondary}.",
                        'type' => 'contains',
                        'answer' => $secondary,
                        'explanation' => 'Mentioning the lesson focus shows recall of the daily target.',
                    ],
                    [
                        'id' => 'q3',
                        'prompt' => "Write a short phrase that includes '{$tertiary}'.",
                        'type' => 'contains',
                        'answer' => $tertiary,
                        'explanation' => 'A correct response should contain the requested keyword.',
                    ],
                ],
            ];
        }

        return [
            'passing_score' => 60,
            'questions' => [
                [
                    'id' => 'q1',
                    'prompt' => "What is the main purpose of {$focus} in today's topic?",
                    'type' => 'contains',
                    'answer' => $focus,
                    'explanation' => 'The answer should mention the core concept directly.',
                ],
                [
                    'id' => 'q2',
                    'prompt' => "Name one command, concept, or action related to {$secondary}.",
                    'type' => 'contains',
                    'answer' => $secondary,
                    'explanation' => 'A correct answer must connect to the lesson focus.',
                ],
                [
                    'id' => 'q3',
                    'prompt' => "Give one practical use case for {$tertiary}.",
                    'type' => 'contains',
                    'answer' => $tertiary,
                    'explanation' => 'The answer should show you can apply the concept.',
                ],
            ],
        ];
    }
}
