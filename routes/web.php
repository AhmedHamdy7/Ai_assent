<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Telegram\TelegramWebhookController;
use App\Http\Middleware\DashboardAccess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::post(config('services.telegram.webhook_path', 'telegram/webhook'), TelegramWebhookController::class)
    ->name('telegram.webhook');

Route::middleware(DashboardAccess::class)->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/dashboard/sessions/{id}/messages', [DashboardController::class, 'sessionMessages'])
        ->whereNumber('id')
        ->name('dashboard.session.messages');
    Route::post('/dashboard/sessions/new', [DashboardController::class, 'newSession'])->name('dashboard.session.new');
    Route::post('/dashboard/chat', [DashboardController::class, 'chatSend'])->name('dashboard.chat.send');
    Route::post('/dashboard/chat/voice', [DashboardController::class, 'chatVoice'])->name('dashboard.chat.voice');
});

Route::get('/cron/reminders/{secret}', function (string $secret) {
    $expected = (string) env('CRON_SECRET', '');
    if ($expected === '' || !hash_equals($expected, $secret)) {
        abort(404);
    }

    Artisan::call('reminders:send');

    return response()->json(['ok' => true, 'output' => Artisan::output()]);
});
