<?php

namespace App\Console\Commands;

use App\Models\AiReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendDueRemindersCommand extends Command
{
    protected $signature   = 'reminders:send';
    protected $description = 'Send all due reminders via Telegram';

    public function handle(): int
    {
        $botToken = config('services.telegram.bot_token');

        if (empty($botToken)) {
            $this->error('TELEGRAM_BOT_TOKEN is not set');
            return self::FAILURE;
        }

        $due = AiReminder::query()->active()->due()->get();

        foreach ($due as $reminder) {
            try {
                Http::timeout(10)->post(
                    "https://api.telegram.org/bot{$botToken}/sendMessage",
                    [
                        'chat_id' => $reminder->telegram_chat_id,
                        'text'    => "🔔 تذكير: {$reminder->message}",
                    ]
                );

                $reminder->last_sent_at = now();

                if ($reminder->frequency === AiReminder::FREQUENCY_ONCE) {
                    $reminder->is_active = false;
                    $reminder->save();
                    continue;
                }

                $reminder->remind_at = $reminder->frequency === AiReminder::FREQUENCY_DAILY
                    ? $reminder->remind_at->addDay()
                    : $reminder->remind_at->addWeek();

                $reminder->save();
            } catch (\Throwable $e) {
                Log::error('reminders.send_failed', [
                    'reminder_id' => $reminder->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
