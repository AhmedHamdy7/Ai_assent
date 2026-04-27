<?php

namespace App\Http\Controllers\Telegram;

use App\AI\Messaging\Telegram\TelegramService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
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

        try {
            $this->telegram->handleUpdate($request->all());
        } catch (Throwable $e) {
            Log::error('telegram.webhook_handler_failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
