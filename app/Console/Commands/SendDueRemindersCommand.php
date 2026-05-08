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
                $response = Http::timeout(10)->post(
                    "https://api.telegram.org/bot{$botToken}/sendMessage",
                    [
                        'chat_id' => $reminder->telegram_chat_id,
                        'text'    => "🔔 تذكير: {$reminder->message}",
                    ]
                );

                $json     = $response->json();
                $telegramOk = $response->successful() && ($json['ok'] ?? false) === true;

                if (! $telegramOk) {
                    $errorCode   = $json['error_code'] ?? $response->status();
                    $description = $json['description'] ?? (string) $response->body();

                    Log::error('reminders.telegram_rejected', [
                        'reminder_id' => $reminder->id,
                        'error_code'  => $errorCode,
                        'description' => $description,
                    ]);

                    // Terminal errors (bot blocked, chat not found) — deactivate so we stop retrying.
                    if ($errorCode === 403 || ($errorCode === 400 && str_contains($description, 'chat not found'))) {
                        $reminder->is_active = false;
                        $reminder->save();
                    }

                    continue;
                }

                // Message delivered — once reminders are removed, recurring ones advance.
                if ($reminder->frequency === AiReminder::FREQUENCY_ONCE) {
                    $reminder->delete();
                    continue;
                }

                $reminder->last_sent_at = now();
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
