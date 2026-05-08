<?php

namespace App\AI\Tool\Reminder;

use App\AI\Provider\ToolResult;
use App\Models\AiReminder;

class CreateReminderTool extends AbstractReminderTool
{
    public function getName(): string
    {
        return 'create_reminder';
    }

    public function eventAction(): string
    {
        return 'Creating a reminder';
    }

    public function getDescription(): string
    {
        return 'Create a reminder. The system will send a real Telegram message to the user at the specified time. Always call this tool when the user asks to be reminded of anything.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'The reminder text that will be sent to the user',
                ],
                'frequency' => [
                    'type' => 'string',
                    'enum' => [AiReminder::FREQUENCY_ONCE, AiReminder::FREQUENCY_DAILY, AiReminder::FREQUENCY_WEEKLY],
                    'description' => 'once = single one-time reminder, daily = every day at the same time, weekly = every week on the same day',
                ],
                'remind_at' => [
                    'type' => 'string',
                    'description' => 'For frequency=once: the exact Egypt local datetime in "YYYY-MM-DD HH:MM" format (e.g. "2026-05-08 16:05"). Compute this from the current date/time shown in the system prompt.',
                ],
                'time_of_day' => [
                    'type' => 'string',
                    'description' => 'For frequency=daily or weekly: time in HH:MM format (e.g. "09:00")',
                ],
                'day_of_week' => [
                    'type'        => 'integer',
                    'enum'        => [0, 1, 2, 3, 4, 5, 6],
                    'description' => 'For frequency=weekly: 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday',
                ],
            ],
            'required' => ['message', 'frequency'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $sessionId = $this->resolveSessionId($context);
        $chatId    = $this->resolveChatId($context);

        if ($chatId === null) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'Reminders only work in Telegram sessions']);
        }

        $message = $this->cleanText((string) ($arguments['message'] ?? ''), 1000);
        if ($message === null) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'message is required']);
        }

        $frequency = (string) ($arguments['frequency'] ?? AiReminder::FREQUENCY_ONCE);
        if (!in_array($frequency, [AiReminder::FREQUENCY_ONCE, AiReminder::FREQUENCY_DAILY, AiReminder::FREQUENCY_WEEKLY], true)) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'Invalid frequency']);
        }

        $timeOfDay  = null;
        $dayOfWeek  = null;
        $remindAt   = null;

        if ($frequency === AiReminder::FREQUENCY_ONCE) {
            $remindAt = $this->parseDateTime(isset($arguments['remind_at']) ? (string) $arguments['remind_at'] : null);
            if ($remindAt === null) {
                return ToolResult::fromPayload(['ok' => false, 'message' => 'remind_at is required for once reminders (e.g. "2026-05-07 09:00" or "tomorrow at 9am")']);
            }

            if ($remindAt->isPast()) {
                return ToolResult::fromPayload(['ok' => false, 'message' => 'remind_at must be in the future']);
            }
        } else {
            $rawTime = trim((string) ($arguments['time_of_day'] ?? ''));
            if (!preg_match('/^\d{1,2}:\d{2}$/', $rawTime)) {
                return ToolResult::fromPayload(['ok' => false, 'message' => 'time_of_day is required in HH:MM format (e.g. "09:00")']);
            }
            $timeOfDay = $rawTime;

            if ($frequency === AiReminder::FREQUENCY_WEEKLY) {
                if (!isset($arguments['day_of_week']) || !is_numeric($arguments['day_of_week'])) {
                    return ToolResult::fromPayload(['ok' => false, 'message' => 'day_of_week is required for weekly reminders (0=Sun, 1=Mon, ..., 6=Sat)']);
                }
                $dayOfWeek = (int) $arguments['day_of_week'];
                if ($dayOfWeek < 0 || $dayOfWeek > 6) {
                    return ToolResult::fromPayload(['ok' => false, 'message' => 'day_of_week must be 0–6']);
                }
            }

            $remindAt = $this->nextRemindAt($frequency, $timeOfDay, $dayOfWeek);
        }

        $reminder = AiReminder::query()->create([
            'ai_session_id'   => $sessionId,
            'telegram_chat_id' => $chatId,
            'message'         => $message,
            'frequency'       => $frequency,
            'time_of_day'     => $timeOfDay,
            'day_of_week'     => $dayOfWeek,
            'remind_at'       => $remindAt,
            'is_active'       => true,
        ]);

        $dayNames = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

        $confirmMsg = match ($frequency) {
            AiReminder::FREQUENCY_ONCE    => "تمام! هفكّرك بـ \"{$message}\" في {$remindAt->format('Y-m-d H:i')}",
            AiReminder::FREQUENCY_DAILY   => "تمام! هفكّرك بـ \"{$message}\" كل يوم الساعة {$timeOfDay}",
            AiReminder::FREQUENCY_WEEKLY  => "تمام! هفكّرك بـ \"{$message}\" كل يوم " . ($dayNames[$dayOfWeek] ?? $dayOfWeek) . " الساعة {$timeOfDay}",
        };

        return ToolResult::fromPayload([
            'ok'       => true,
            'message'  => $confirmMsg,
            'reminder' => $this->serializeReminder($reminder),
        ]);
    }
}
