<?php

use App\Http\Controllers\Telegram\TelegramWebhookController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post(config('services.telegram.webhook_path', 'telegram/webhook'), TelegramWebhookController::class)
    ->name('telegram.webhook');

Route::get('/cron/reminders/{secret}', function (string $secret) {
    $expected = (string) env('CRON_SECRET', '');
    if ($expected === '' || !hash_equals($expected, $secret)) {
        abort(404);
    }

    Artisan::call('reminders:send');

    return response()->json(['ok' => true, 'output' => Artisan::output()]);
});
