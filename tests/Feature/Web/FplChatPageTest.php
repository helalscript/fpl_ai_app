<?php

use App\Models\FplChatConversation;
use App\Models\FplChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('authenticated user can open fpl assistant page', function () {
    $user = User::factory()->create(['fpl_team_id' => 9001]);

    $thread = FplChatConversation::query()->create([
        'user_id' => $user->id,
        'fpl_team_id' => 9001,
        'last_message_at' => now(),
    ]);

    FplChatMessage::query()->create([
        'user_id' => $user->id,
        'fpl_team_id' => 9001,
        'fpl_chat_conversation_id' => $thread->id,
        'role' => 'user',
        'content' => 'Captain pick?',
    ]);

    $this->actingAs($user)
        ->get(route('fpl.chat'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fpl-chat')
            ->has('initialMessages', 1)
            ->where('initialHasMoreOlder', false)
            ->where('initialThreadId', $thread->id)
            ->has('initialConversations', 1)
            ->has('promptSuggestions', 5)
            ->where('initialMessages.0.role', 'user')
            ->where('initialMessages.0.content', 'Captain pick?'));
});

test('guest is redirected from fpl assistant page', function () {
    $this->get(route('fpl.chat'))
        ->assertRedirect(route('login'));
});
