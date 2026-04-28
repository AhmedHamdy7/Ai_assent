<?php

namespace App\AI\Tool\Learning;

use App\AI\Provider\ToolResult;
use App\Models\AiLearningTrack;

class ListLearningTracksTool extends AbstractLearningTool
{
    public function getName(): string
    {
        return 'list_learning_tracks';
    }

    public function getDescription(): string
    {
        return 'List the current study tracks for this chat, including progress and next lesson.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['active', 'paused', 'completed', 'all']],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $status = (string) ($arguments['status'] ?? 'all');
        $sessionId = $this->resolveSessionId($context);

        $query = AiLearningTrack::query()->with('lessons')->latest('id');
        if ($sessionId !== null) {
            $query->where('ai_session_id', $sessionId);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $tracks = $query->get();

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => $tracks->isEmpty()
                ? 'No learning tracks found.'
                : 'Found ' . $tracks->count() . ' learning track(s).',
            'tracks' => $tracks->map(fn (AiLearningTrack $track) => $this->serializeTrack($track))->values()->all(),
        ]);
    }
}
