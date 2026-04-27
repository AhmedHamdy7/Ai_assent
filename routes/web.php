<?php

use App\Http\Controllers\Telegram\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post(config('services.telegram.webhook_path', 'telegram/webhook'), TelegramWebhookController::class)
    ->name('telegram.webhook');
