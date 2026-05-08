<?php

namespace App\Http\Controllers\Telegram;

use App\AI\Messaging\Telegram\TelegramService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(private TelegramService $telegram) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if (!$this->telegram->verifySecret($secret)) {
            abort(403, 'Invalid secret token');
        }

        $update = $request->all();
        $updateId = $update['update_id'] ?? null;

        if ($updateId === null) {
            return response()->json(['ok' => true]);
        }

        // Atomic dedup — first request claims the update, retries are skipped fast.
        if (!Cache::add("telegram:update:{$updateId}", 1, 600)) {
            return response()->json(['ok' => true]);
        }

        try {
            $this->telegram->handleUpdate($update);
        } catch (\Throwable $e) {
            Log::error('telegram.webhook_handler_failed', [
                'update_id' => $updateId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
