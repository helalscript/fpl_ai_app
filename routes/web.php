<?php

use App\Http\Controllers\Web\FplChatConversationsController;
use App\Http\Controllers\Web\FplChatMessagesController;
use App\Http\Controllers\Web\FplChatPageController;
use App\Http\Controllers\Web\FplChatPromptSuggestionsController;
use App\Http\Controllers\Web\FplChatStreamController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('fpl-chat', FplChatPageController::class)->name('fpl.chat');
    Route::get('fpl/chat/conversations', [FplChatConversationsController::class, 'index'])->name('fpl.chat.conversations.index');
    Route::post('fpl/chat/conversations', [FplChatConversationsController::class, 'store'])->name('fpl.chat.conversations.store');
    Route::get('fpl/chat/prompt-suggestions', FplChatPromptSuggestionsController::class)->name('fpl.chat.prompt-suggestions');
    Route::get('fpl/chat/messages', [FplChatMessagesController::class, 'index'])->name('fpl.chat.messages.index');
    Route::delete('fpl/chat/messages', [FplChatMessagesController::class, 'destroy'])->name('fpl.chat.messages.destroy');
    Route::post('fpl/chat/stream', FplChatStreamController::class)->name('fpl.chat.stream');
});

require __DIR__.'/settings.php';
