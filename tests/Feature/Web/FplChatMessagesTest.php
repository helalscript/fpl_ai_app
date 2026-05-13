<?php

use App\Models\FplChatConversation;
use App\Models\FplChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can list fpl chat messages with pagination meta', function () {
    $user = User::factory()->create(['fpl_team_id' => 100]);

    $thread = FplChatConversation::query()->create([
        'user_id' => $user->id,
        'fpl_team_id' => 100,
        'last_message_at' => now(),
    ]);

    FplChatMessage::query()->create([
        'user_id' => $user->id,
        'fpl_team_id' => 100,
        'fpl_chat_conversation_id' => $thread->id,
        'role' => 'user',
        'content' => 'Hello',
    ]);

    $this->actingAs($user)
        ->getJson(route('fpl.chat.messages.index', [
            'team_id' => 100,
            'thread_id' => $thread->id,
        ]))
        ->assertOk()
        ->assertJsonPath('has_more_older', false)
        ->assertJsonPath('has_more_newer', false)
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.content', 'Hello');
});

test('fpl chat messages returns older page when before_id is set', function () {
    $user = User::factory()->create(['fpl_team_id' => 100]);

    $thread = FplChatConversation::query()->create([
        'user_id' => $user->id,
        'fpl_team_id' => 100,
        'last_message_at' => now(),
    ]);

    $ids = [];
    for ($i = 1; $i <= 45; $i++) {
        $row = FplChatMessage::query()->create([
            'user_id' => $user->id,
            'fpl_team_id' => 100,
            'fpl_chat_conversation_id' => $thread->id,
            'role' => 'user',
            'content' => "m{$i}",
        ]);
        $ids[] = $row->id;
    }

    $this->actingAs($user)
        ->getJson(route('fpl.chat.messages.index', [
            'team_id' => 100,
            'thread_id' => $thread->id,
            'per_page' => 40,
        ]))
        ->assertOk()
        ->assertJsonPath('has_more_older', true)
        ->assertJsonCount(40, 'messages')
        ->assertJsonPath('messages.0.content', 'm6');

    $minOnPage = min(array_slice($ids, -40));

    $this->actingAs($user)
        ->getJson(route('fpl.chat.messages.index', [
            'team_id' => 100,
            'thread_id' => $thread->id,
            'per_page' => 40,
            'before_id' => $minOnPage,
        ]))
        ->assertOk()
        ->assertJsonPath('has_more_older', false)
        ->assertJsonCount(5, 'messages')
        ->assertJsonPath('messages.0.content', 'm1');
});

test('fpl chat messages rejects before_id and after_id together', function () {
    $user = User::factory()->create(['fpl_team_id' => 100]);

    $this->actingAs($user)
        ->getJson(route('fpl.chat.messages.index', [
            'team_id' => 100,
            'thread_id' => 1,
            'before_id' => 2,
            'after_id' => 1,
        ]))
        ->assertStatus(422);
});

test('listing fpl chat messages requires team id and thread id', function () {
    $user = User::factory()->create(['fpl_team_id' => null]);

    $this->actingAs($user)->getJson(route('fpl.chat.messages.index'))->assertStatus(422);
});

test('listing fpl chat messages requires thread id when team is known', function () {
    $user = User::factory()->create(['fpl_team_id' => 100]);

    $this->actingAs($user)
        ->getJson(route('fpl.chat.messages.index', ['team_id' => 100]))
        ->assertStatus(422);
});

test('fpl chat messages rejects team id that does not match profile', function () {
    $user = User::factory()->create(['fpl_team_id' => 100]);

    $this->actingAs($user)
        ->getJson(route('fpl.chat.messages.index', ['team_id' => 200]))
        ->assertStatus(422);
});

test('authenticated user can clear fpl chat messages', function () {
    $user = User::factory()->create(['fpl_team_id' => 200]);

    $thread = FplChatConversation::query()->create([
        'user_id' => $user->id,
        'fpl_team_id' => 200,
        'last_message_at' => now(),
    ]);

    FplChatMessage::query()->create([
        'user_id' => $user->id,
        'fpl_team_id' => 200,
        'fpl_chat_conversation_id' => $thread->id,
        'role' => 'user',
        'content' => 'Hello',
    ]);

    $this->actingAs($user)
        ->deleteJson(route('fpl.chat.messages.destroy', [
            'team_id' => 200,
            'thread_id' => $thread->id,
        ]))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(FplChatMessage::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('authenticated user can list fpl chat prompt suggestions', function () {
    $user = User::factory()->create(['fpl_team_id' => 300]);

    $this->actingAs($user)
        ->getJson(route('fpl.chat.prompt-suggestions'))
        ->assertOk()
        ->assertJsonStructure(['suggestions'])
        ->assertJsonCount(5, 'suggestions')
        ->assertJsonPath('suggestions.0.body', 'Who should I captain?');
});

test('authenticated user can list and create fpl chat conversations', function () {
    $user = User::factory()->create(['fpl_team_id' => 400]);

    $this->actingAs($user)
        ->getJson(route('fpl.chat.conversations.index', ['team_id' => 400]))
        ->assertOk()
        ->assertJsonCount(0, 'conversations');

    $this->actingAs($user)
        ->postJson(route('fpl.chat.conversations.store'), ['team_id' => 400])
        ->assertCreated()
        ->assertJsonPath('conversation.id', fn ($id) => is_int($id) && $id > 0);

    $this->actingAs($user)
        ->getJson(route('fpl.chat.conversations.index', ['team_id' => 400]))
        ->assertOk()
        ->assertJsonCount(1, 'conversations');
});
