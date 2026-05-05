<?php

namespace App\Console\Commands;

use App\AI\Messaging\Telegram\TelegramService;
use App\Models\AiReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDueRemindersCommand extends Command
{
    protected $signature   = 'reminders:send';
    protected $description = 'Send all due reminders via Telegram';

    public function handle(TelegramService $telegram): int
    {
        $due = AiReminder::query()
            ->active()
            ->due()
            ->get();

        foreach ($due as $reminder) {
            try {
                $telegram->sendMessage($reminder->telegram_chat_id, "🔔 تذكير: {$reminder->message}");

                $reminder->last_sent_at = now();

                if ($reminder->frequency === AiReminder::FREQUENCY_ONCE) {
                    $reminder->is_active = false;
                    $reminder->save();
                    continue;
                }

                // Advance remind_at to next occurrence
                $reminder->remind_at = $this->nextOccurrence($reminder);
                $reminder->save();
            } catch (\Throwable $e) {
                Log::error('reminders.send_failed', [
                    'reminder_id' => $reminder->id,
                    'chat_id'     => $reminder->telegram_chat_id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->info("Processed {$due->count()} reminder(s).");

        return self::SUCCESS;
    }

    private function nextOccurrence(AiReminder $reminder): \Carbon\Carbon
    {
        $current = $reminder->remind_at;

        if ($reminder->frequency === AiReminder::FREQUENCY_DAILY) {
            return $current->addDay();
        }

        if ($reminder->frequency === AiReminder::FREQUENCY_WEEKLY) {
            return $current->addWeek();
        }

        return now()->addDay();
    }
}
