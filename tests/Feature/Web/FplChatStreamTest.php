<?php

use App\Ai\Agents\FplChatAssistant;
use App\Models\FplChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

test('guests cannot access web fpl chat stream', function () {
    $response = $this->post('/fpl/chat/stream', [
        'message' => 'Hello',
    ]);

    $response->assertRedirect(route('login'));
});

test('authenticated web user can stream fpl chat with team context', function () {
    Carbon::setTestNow('2026-05-13 12:00:00');

    Cache::flush();

    $user = User::factory()->create([
        'id' => 92,
        'name' => 'Web Manager',
        'email' => 'web@example.com',
        'fpl_team_id' => 31,
    ]);

    $bootstrap = [
        'element_types' => [
            ['id' => 1, 'singular_name_short' => 'GK', 'singular_name' => 'Goalkeeper'],
            ['id' => 2, 'singular_name_short' => 'DEF', 'singular_name' => 'Defender'],
            ['id' => 3, 'singular_name_short' => 'MID', 'singular_name' => 'Midfielder'],
            ['id' => 4, 'singular_name_short' => 'FWD', 'singular_name' => 'Forward'],
        ],
        'teams' => [
            ['id' => 1, 'name' => 'Arsenal', 'short_name' => 'ARS'],
            ['id' => 2, 'name' => 'Aston Villa', 'short_name' => 'AVL'],
        ],
        'events' => [
            ['id' => 30, 'name' => 'Gameweek 30', 'deadline_time' => '2026-03-14T10:30:00Z', 'is_current' => true, 'is_next' => false],
        ],
        'fixtures' => [
            ['event' => 30, 'team_h' => 1, 'team_a' => 2, 'difficulty' => 2, 'kickoff_time' => '2026-03-14T12:00:00Z'],
        ],
        'elements' => [
            ['id' => 5, 'photo' => '5', 'first_name' => 'Mohamed', 'second_name' => 'Salah', 'web_name' => 'Salah', 'team' => 1, 'element_type' => 3, 'now_cost' => 135, 'form' => '8.8', 'points_per_game' => '7.9', 'minutes' => 2800, 'ict_index' => '120.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '68.0', 'total_points' => 240, 'goals_scored' => 16, 'assists' => 8],
        ],
    ];
    $team = ['id' => 31, 'name' => 'Web FC'];
    $picks = [
        'active_chip' => null,
        'entry_history' => [
            'bank' => 25,
            'value' => 1015,
            'event_transfers' => 1,
        ],
        'picks' => [
            ['element' => 5, 'position' => 1, 'multiplier' => 1, 'is_captain' => true, 'is_vice_captain' => false],
        ],
    ];

    Http::fake(function (HttpRequest $request) use ($bootstrap, $team, $picks) {
        $url = $request->url();

        if (str_contains($url, '/bootstrap-static/')) {
            return Http::response($bootstrap);
        }

        if (str_contains($url, '/fixtures/')) {
            return Http::response($bootstrap['fixtures']);
        }

        if (str_contains($url, '/entry/31/event/30/picks/')) {
            return Http::response($picks);
        }

        if (str_contains($url, '/entry/31/')) {
            return Http::response($team);
        }

        return Http::response([], 404);
    });

    FplChatAssistant::fake(function (string $prompt) {
        expect($prompt)->toContain('Web Manager');
        expect($prompt)->toContain('"team_id": 31');
        expect($prompt)->toContain('"is_first_user_message_today": true');

        return 'Captain Salah this week.';
    });

    $this->actingAs($user);

    $streamResponse = $this->post('/fpl/chat/stream', [
        'message' => 'Who should I captain?',
    ]);

    $streamResponse->assertOk();
    expect($streamResponse->headers->get('content-type'))->toContain('text/event-stream');
    expect($streamResponse->streamedContent())->toContain('data:');
    expect($streamResponse->streamedContent())->toContain('"type":"text_delta"');

    expect(FplChatMessage::query()->where('user_id', $user->id)->where('fpl_team_id', 31)->count())->toBe(2);

    FplChatAssistant::fake(function (string $prompt) {
        expect($prompt)->toContain('"is_first_user_message_today": false');

        return 'Second reply.';
    });

    $second = $this->post('/fpl/chat/stream', [
        'message' => 'Bench order?',
    ]);

    $second->assertOk();
    expect($second->streamedContent())->toContain('"type":"text_delta"');
    expect(FplChatMessage::query()->where('user_id', $user->id)->where('fpl_team_id', 31)->count())->toBe(4);
});

test('web fpl chat stream requires team id when user has no fpl team', function () {
    Cache::flush();

    $user = User::factory()->create([
        'fpl_team_id' => null,
    ]);

    $this->actingAs($user);

    $response = $this->postJson('/fpl/chat/stream', [
        'message' => 'Hello',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('status', false);
    $response->assertJsonPath('message', 'team_id is required.');
});

test('web fpl chat stream rejects team id that does not match profile', function () {
    $user = User::factory()->create([
        'fpl_team_id' => 31,
    ]);

    $this->actingAs($user);

    $response = $this->postJson('/fpl/chat/stream', [
        'message' => 'Hello',
        'team_id' => 999,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('status', false);
    $response->assertJsonPath('message', 'The team_id must match your FPL entry ID saved on your profile.');
});
