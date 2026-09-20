<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatController::class, 'index'])->name('chat.index');
Route::get('/c/{conversation}', [ChatController::class, 'show'])
    ->whereUlid('conversation')
    ->name('chat.show');

// Sending a message calls the AI provider, so it carries the tighter limit.
Route::post('/chat/messages', [ChatController::class, 'store'])
    ->middleware('throttle:chat')
    ->name('chat.store');

Route::middleware('throttle:chat-ui')->group(function (): void {
    Route::post('/chat/conversations', [ChatController::class, 'startNew'])->name('chat.conversations.store');
    Route::post('/chat/model', [ChatController::class, 'chooseModel'])->name('chat.model.store');
    Route::delete('/chat/conversations/{conversation}', [ChatController::class, 'destroy'])
        ->whereUlid('conversation')
        ->name('chat.conversations.destroy');
});

/*
| Accounts are optional: they exist so a visitor's history follows them to
| another browser instead of living in one session.
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');

    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
        Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    });
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
