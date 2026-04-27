<?php

namespace App\Console\Commands;

use App\AI\Messaging\Telegram\TelegramService;
use Illuminate\Console\Command;

class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook
        {action : One of: set, delete, info}
        {--url= : Public URL to use for "set" (defaults to APP_URL + webhook path)}';

    protected $description = 'Manage the Telegram bot webhook (set, delete, info)';

    public function handle(TelegramService $telegram): int
    {
        $action = (string) $this->argument('action');

        return match ($action) {
            'set'    => $this->set($telegram),
            'delete' => $this->delete($telegram),
            'info'   => $this->info_($telegram),
            default  => $this->bail("Unknown action [{$action}]. Use one of: set, delete, info."),
        };
    }

    private function set(TelegramService $telegram): int
    {
        $url = (string) ($this->option('url') ?: $this->defaultUrl());

        if ($url === '') {
            return $this->bail('No URL given and APP_URL is empty. Pass --url=https://your-host/telegram/webhook');
        }

        $this->line("Setting webhook → {$url}");
        $response = $telegram->setWebhook($url);
        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function delete(TelegramService $telegram): int
    {
        $response = $telegram->deleteWebhook();
        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function info_(TelegramService $telegram): int
    {
        $response = $telegram->getWebhookInfo();
        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function defaultUrl(): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $path = trim((string) config('services.telegram.webhook_path', 'telegram/webhook'), '/');

        return $appUrl !== '' ? "{$appUrl}/{$path}" : '';
    }

    private function bail(string $message): int
    {
        $this->error($message);
        return self::FAILURE;
    }
}
