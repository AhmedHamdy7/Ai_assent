<?php

namespace App\AI\Tool\Reminder;

use App\AI\Tool\BaseTool;
use App\Models\AiReminder;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

abstract class AbstractReminderTool extends BaseTool
{
    protected function resolveSessionId(array $context): ?int
    {
        $sessionId = Arr::get($context, 'ai_session_id');

        return is_numeric($sessionId) ? (int) $sessionId : null;
    }

    protected function resolveChatId(array $context): ?string
    {
        $chatId = Arr::get($context, 'telegram_chat_id');

        return is_string($chatId) && $chatId !== '' ? $chatId : null;
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

    protected function nextRemindAt(string $frequency, ?string $timeOfDay, ?int $dayOfWeek): Carbon
    {
        $now = now();

        if ($frequency === AiReminder::FREQUENCY_DAILY && $timeOfDay !== null) {
            [$hour, $minute] = array_map('intval', explode(':', $timeOfDay));
            $next = $now->copy()->setTime($hour, $minute, 0);

            return $next->lte($now) ? $next->addDay() : $next;
        }

        if ($frequency === AiReminder::FREQUENCY_WEEKLY && $timeOfDay !== null && $dayOfWeek !== null) {
            [$hour, $minute] = array_map('intval', explode(':', $timeOfDay));
            $next = $now->copy()->next($dayOfWeek)->setTime($hour, $minute, 0);

            // If today is the right day but time hasn't passed, use today
            if ($now->dayOfWeek === $dayOfWeek) {
                $today = $now->copy()->setTime($hour, $minute, 0);
                if ($today->gt($now)) {
                    return $today;
                }
            }

            return $next;
        }

        return $now->addMinute();
    }

    protected function serializeReminder(AiReminder $reminder): array
    {
        return [
            'id'          => $reminder->id,
            'message'     => $reminder->message,
            'frequency'   => $reminder->frequency,
            'time_of_day' => $reminder->time_of_day,
            'day_of_week' => $reminder->day_of_week,
            'remind_at'   => $reminder->remind_at->toIso8601String(),
            'is_active'   => $reminder->is_active,
        ];
    }
}
