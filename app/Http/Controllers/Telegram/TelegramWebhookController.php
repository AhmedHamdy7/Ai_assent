<?php

namespace App\Http\Controllers\Telegram;

use App\AI\Messaging\Telegram\TelegramService;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessTelegramUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        if (isset($update['update_id'])) {
            ProcessTelegramUpdate::dispatch($update)->onQueue('telegram');
        }

        return response()->json(['ok' => true]);
    }
}
