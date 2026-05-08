<?php

namespace App\Jobs;

use App\AI\Messaging\Telegram\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessTelegramUpdate implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;
    public int $tries = 1;

    public function __construct(private array $update) {}

    public function handle(TelegramService $telegram): void
    {
        $telegram->handleUpdate($this->update);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('telegram.job_failed', [
            'update_id' => $this->update['update_id'] ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
