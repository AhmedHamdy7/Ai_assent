<?php

namespace App\AI\Tool\Task;

use App\AI\Tool\BaseTool;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

abstract class AbstractTaskTool extends BaseTool
{
    protected function resolveSessionId(array $context): ?int
    {
        $sessionId = Arr::get($context, 'ai_session_id');

        return is_numeric($sessionId) ? (int) $sessionId : null;
    }

    protected function parseDateTime(?string $value): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
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
}
