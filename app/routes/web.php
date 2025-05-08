<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Telegram\Bot\Laravel\Facades\Telegram;

use App\Http\Controllers\TelegramController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])->name('telegram.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::get('/telegram/set-webhook', [TelegramController::class, 'setWebhook'])->name('telegram.set-webhook');
