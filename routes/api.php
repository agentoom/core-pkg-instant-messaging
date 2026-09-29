<?php

use Agentoom\InstantMessaging\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhook/telegram-bot/{botId}', [WebhookController::class, 'handleTelegram']);

// Meta verification handshake, then delivery.
Route::get('webhook/{service}/{agentId}', [WebhookController::class, 'verify']);
Route::post('webhook/{service}/{agentId}', [WebhookController::class, 'handle']);
