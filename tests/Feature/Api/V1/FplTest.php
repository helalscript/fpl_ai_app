<?php

use App\Ai\Agents\FplChatAssistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('home page endpoint returns structured fpl card data', function () {
    Cache::flush();

    $bootstrap = [
        'element_types' => [
            ['id' => 1, 'singular_name_short' => 'GK', 'singular_name' => 'Goalkeeper'],
            ['id' => 2, 'singular_name_short' => 'DEF', 'singular_name' => 'Defender'],
            ['id' => 3, 'singular_name_short' => 'MID', 'singular_name' => 'Midfielder'],
            ['id' => 4, 'singular_name_short' => 'FWD', 'singular_name' => 'Forward'],
        ],
        'teams' => [
            ['id' => 1, 'name' => 'Liverpool', 'short_name' => 'LIV'],
            ['id' => 2, 'name' => 'Manchester City', 'short_name' => 'MCI'],
            ['id' => 3, 'name' => 'Arsenal', 'short_name' => 'ARS'],
            ['id' => 4, 'name' => 'Newcastle United', 'short_name' => 'NEW'],
            ['id' => 5, 'name' => 'Everton', 'short_name' => 'EVE'],
            ['id' => 6, 'name' => 'Chelsea', 'short_name' => 'CHE'],
            ['id' => 7, 'name' => 'Aston Villa', 'short_name' => 'AVL'],
        ],
        'events' => [
            ['id' => 12, 'name' => 'Gameweek 12', 'deadline_time' => '2026-04-21T18:30:00Z', 'is_current' => true, 'is_next' => false],
            ['id' => 13, 'name' => 'Gameweek 13', 'deadline_time' => '2026-04-28T18:30:00Z', 'is_current' => false, 'is_next' => true],
            ['id' => 14, 'name' => 'Gameweek 14', 'deadline_time' => '2026-05-05T18:30:00Z', 'is_current' => false, 'is_next' => false],
        ],
        'fixtures' => [
            ['event' => 12, 'team_h' => 2, 'team_a' => 3, 'difficulty' => 2, 'kickoff_time' => '2026-04-21T18:30:00Z'],
            ['event' => 12, 'team_h' => 1, 'team_a' => 4, 'difficulty' => 4, 'kickoff_time' => '2026-04-21T18:30:00Z'],
            ['event' => 12, 'team_h' => 6, 'team_a' => 5, 'difficulty' => 3, 'kickoff_time' => '2026-04-21T18:30:00Z'],
            ['event' => 13, 'team_h' => 4, 'team_a' => 1, 'difficulty' => 2, 'kickoff_time' => '2026-04-28T18:30:00Z'],
            ['event' => 13, 'team_h' => 3, 'team_a' => 2, 'difficulty' => 4, 'kickoff_time' => '2026-04-28T18:30:00Z'],
            ['event' => 13, 'team_h' => 7, 'team_a' => 6, 'difficulty' => 3, 'kickoff_time' => '2026-04-28T18:30:00Z'],
        ],
        'elements' => [
            ['id' => 1, 'photo' => '1', 'first_name' => 'Jordan', 'second_name' => 'Pickford', 'web_name' => 'Pickford', 'team' => 5, 'element_type' => 1, 'now_cost' => 50, 'form' => '3.8', 'points_per_game' => '4.2', 'minutes' => 2700, 'ict_index' => '34.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '24.0', 'total_points' => 145, 'goals_scored' => 0, 'assists' => 0],
            ['id' => 2, 'photo' => '2', 'first_name' => 'Gabriel', 'second_name' => 'Magalhaes', 'web_name' => 'Gabriel', 'team' => 3, 'element_type' => 2, 'now_cost' => 55, 'form' => '5.0', 'points_per_game' => '4.8', 'minutes' => 2600, 'ict_index' => '48.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '34.0', 'total_points' => 160, 'goals_scored' => 4, 'assists' => 1],
            ['id' => 3, 'photo' => '3', 'first_name' => 'William', 'second_name' => 'Saliba', 'web_name' => 'Saliba', 'team' => 3, 'element_type' => 2, 'now_cost' => 56, 'form' => '4.7', 'points_per_game' => '4.5', 'minutes' => 2600, 'ict_index' => '42.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '31.0', 'total_points' => 150, 'goals_scored' => 2, 'assists' => 0],
            ['id' => 4, 'photo' => '4', 'first_name' => 'Virgil', 'second_name' => 'van Dijk', 'web_name' => 'Van Dijk', 'team' => 1, 'element_type' => 2, 'now_cost' => 60, 'form' => '4.4', 'points_per_game' => '4.1', 'minutes' => 2650, 'ict_index' => '39.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '22.0', 'total_points' => 148, 'goals_scored' => 3, 'assists' => 1],
            ['id' => 5, 'photo' => '5', 'first_name' => 'Mohamed', 'second_name' => 'Salah', 'web_name' => 'Salah', 'team' => 1, 'element_type' => 3, 'now_cost' => 135, 'form' => '8.8', 'points_per_game' => '7.9', 'minutes' => 2800, 'ict_index' => '120.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '68.0', 'total_points' => 240, 'goals_scored' => 16, 'assists' => 8],
            ['id' => 6, 'photo' => '6', 'first_name' => 'Phil', 'second_name' => 'Foden', 'web_name' => 'Foden', 'team' => 2, 'element_type' => 3, 'now_cost' => 90, 'form' => '6.9', 'points_per_game' => '6.0', 'minutes' => 2200, 'ict_index' => '92.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '40.0', 'total_points' => 180, 'goals_scored' => 11, 'assists' => 7],
            ['id' => 7, 'photo' => '7', 'first_name' => 'Cole', 'second_name' => 'Palmer', 'web_name' => 'Palmer', 'team' => 6, 'element_type' => 3, 'now_cost' => 105, 'form' => '7.3', 'points_per_game' => '6.3', 'minutes' => 2500, 'ict_index' => '101.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '55.0', 'total_points' => 200, 'goals_scored' => 14, 'assists' => 9],
            ['id' => 8, 'photo' => '8', 'first_name' => 'Bukayo', 'second_name' => 'Saka', 'web_name' => 'Saka', 'team' => 3, 'element_type' => 3, 'now_cost' => 100, 'form' => '7.1', 'points_per_game' => '6.4', 'minutes' => 2450, 'ict_index' => '98.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '49.0', 'total_points' => 192, 'goals_scored' => 13, 'assists' => 10],
            ['id' => 9, 'photo' => '9', 'first_name' => 'Erling', 'second_name' => 'Haaland', 'web_name' => 'Haaland', 'team' => 2, 'element_type' => 4, 'now_cost' => 155, 'form' => '9.1', 'points_per_game' => '8.4', 'minutes' => 2500, 'ict_index' => '132.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '72.0', 'total_points' => 255, 'goals_scored' => 27, 'assists' => 4],
            ['id' => 10, 'photo' => '10', 'first_name' => 'Ollie', 'second_name' => 'Watkins', 'web_name' => 'Watkins', 'team' => 7, 'element_type' => 4, 'now_cost' => 90, 'form' => '6.4', 'points_per_game' => '5.8', 'minutes' => 2550, 'ict_index' => '86.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '38.0', 'total_points' => 170, 'goals_scored' => 18, 'assists' => 9],
            ['id' => 11, 'photo' => '11', 'first_name' => 'Eddie', 'second_name' => 'Nketiah', 'web_name' => 'Nketiah', 'team' => 3, 'element_type' => 4, 'now_cost' => 50, 'form' => '2.0', 'points_per_game' => '2.1', 'minutes' => 650, 'ict_index' => '24.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '6.0', 'total_points' => 55, 'goals_scored' => 4, 'assists' => 1],
            ['id' => 12, 'photo' => '12', 'first_name' => 'Emiliano', 'second_name' => 'Martinez', 'web_name' => 'Martinez', 'team' => 7, 'element_type' => 1, 'now_cost' => 50, 'form' => '4.0', 'points_per_game' => '4.0', 'minutes' => 2700, 'ict_index' => '31.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '20.0', 'total_points' => 140, 'goals_scored' => 0, 'assists' => 0],
            ['id' => 13, 'photo' => '13', 'first_name' => 'Kieran', 'second_name' => 'Trippier', 'web_name' => 'Trippier', 'team' => 4, 'element_type' => 2, 'now_cost' => 65, 'form' => '3.2', 'points_per_game' => '3.8', 'minutes' => 2100, 'ict_index' => '36.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '18.0', 'total_points' => 120, 'goals_scored' => 1, 'assists' => 6],
            ['id' => 14, 'photo' => '14', 'first_name' => 'Anthony', 'second_name' => 'Gordon', 'web_name' => 'Gordon', 'team' => 4, 'element_type' => 3, 'now_cost' => 75, 'form' => '5.3', 'points_per_game' => '5.1', 'minutes' => 2200, 'ict_index' => '71.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '28.0', 'total_points' => 155, 'goals_scored' => 10, 'assists' => 8],
            ['id' => 15, 'photo' => '15', 'first_name' => 'Yoane', 'second_name' => 'Wissa', 'web_name' => 'Wissa', 'team' => 4, 'element_type' => 4, 'now_cost' => 70, 'form' => '4.6', 'points_per_game' => '4.9', 'minutes' => 2050, 'ict_index' => '64.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '12.0', 'total_points' => 138, 'goals_scored' => 11, 'assists' => 3],
            ['id' => 16, 'photo' => '16', 'first_name' => 'Alexander', 'second_name' => 'Isak', 'web_name' => 'Isak', 'team' => 4, 'element_type' => 4, 'now_cost' => 75, 'form' => '8.0', 'points_per_game' => '7.2', 'minutes' => 2150, 'ict_index' => '110.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '44.0', 'total_points' => 195, 'goals_scored' => 20, 'assists' => 5],
        ],
    ];

    $team = ['id' => 1, 'name' => 'Test FC'];
    $picks = [
        'active_chip' => null,
        'entry_history' => [
            'bank' => 25,
            'value' => 1015,
            'event_transfers' => 1,
            'points' => 71,
            'total_points' => 1123,
        ],
        'picks' => [
            ['element' => 1, 'position' => 1, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 2, 'position' => 2, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 3, 'position' => 3, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 4, 'position' => 4, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 5, 'position' => 5, 'multiplier' => 1, 'is_captain' => true, 'is_vice_captain' => false],
            ['element' => 6, 'position' => 6, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 7, 'position' => 7, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 8, 'position' => 8, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 9, 'position' => 9, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 10, 'position' => 10, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 11, 'position' => 11, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 12, 'position' => 12, 'multiplier' => 0, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 13, 'position' => 13, 'multiplier' => 0, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 14, 'position' => 14, 'multiplier' => 0, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 15, 'position' => 15, 'multiplier' => 0, 'is_captain' => false, 'is_vice_captain' => false],
        ],
    ];
    $fixtures = $bootstrap['fixtures'];
    $summaries = [
        9 => ['history' => [['round' => 12, 'total_points' => 15]], 'fixtures' => []],
    ];

    Http::fake(function (HttpRequest $request) use ($bootstrap, $team, $picks, $fixtures, $summaries) {
        $url = $request->url();

        if (str_contains($url, '/bootstrap-static/')) {
            return Http::response($bootstrap);
        }

        if (str_contains($url, '/fixtures/')) {
            return Http::response($fixtures);
        }

        if (str_contains($url, '/entry/1/event/12/picks/')) {
            return Http::response($picks);
        }

        if (str_contains($url, '/entry/1/')) {
            return Http::response($team);
        }

        if (preg_match('#/element-summary/(\d+)/#', $url, $matches)) {
            $playerId = (int) $matches[1];

            return Http::response($summaries[$playerId] ?? ['history' => [], 'fixtures' => []]);
        }

        return Http::response([], 404);
    });

    $response = $this->getJson('/api/fpl/home-page?team_id=1');

    $response->assertOk();
    $response->assertJsonPath('status', true);
    $response->assertJsonPath('data.gameweek_details.current_gameweek', 12);
    $response->assertJsonPath('data.captaincy_suggestion.best_captain.name', 'Erling Haaland');
    $response->assertJsonPath('data.transfer_strategy.recommendations.0.player_in.name', 'Alexander Isak');
    $response->assertJsonPath('data.captaincy_suggestion.best_captain.photo_url', 'https://resources.premierleague.com/premierleague/photos/players/40x40/p9.png');
    $response->assertJsonPath('data.captaincy_suggestion.best_captain.jersey_url', url('/api/fpl/assets/jersey/2.svg'));
    $response->assertJsonStructure([
        'data' => [
            'team',
            'gameweek_details',
            'transfer_strategy',
            'captaincy_suggestion',
            'squad_recommendation',
            'bench_players',
            'player_search' => [
                'players',
            ],
        ],
    ]);
});

test('optimizer endpoints return single card payloads for current and recommended views', function () {
    Cache::flush();

    $bootstrap = [
        'element_types' => [
            ['id' => 1, 'singular_name_short' => 'GK', 'singular_name' => 'Goalkeeper'],
            ['id' => 2, 'singular_name_short' => 'DEF', 'singular_name' => 'Defender'],
            ['id' => 3, 'singular_name_short' => 'MID', 'singular_name' => 'Midfielder'],
            ['id' => 4, 'singular_name_short' => 'FWD', 'singular_name' => 'Forward'],
        ],
        'teams' => [
            ['id' => 1, 'name' => 'Liverpool', 'short_name' => 'LIV'],
            ['id' => 2, 'name' => 'Manchester City', 'short_name' => 'MCI'],
            ['id' => 3, 'name' => 'Arsenal', 'short_name' => 'ARS'],
            ['id' => 4, 'name' => 'Newcastle United', 'short_name' => 'NEW'],
            ['id' => 5, 'name' => 'Everton', 'short_name' => 'EVE'],
            ['id' => 6, 'name' => 'Chelsea', 'short_name' => 'CHE'],
            ['id' => 7, 'name' => 'Aston Villa', 'short_name' => 'AVL'],
        ],
        'events' => [
            ['id' => 12, 'name' => 'Gameweek 12', 'deadline_time' => '2026-04-21T18:30:00Z', 'is_current' => true, 'is_next' => false],
            ['id' => 13, 'name' => 'Gameweek 13', 'deadline_time' => '2026-04-28T18:30:00Z', 'is_current' => false, 'is_next' => true],
        ],
        'fixtures' => [
            ['event' => 12, 'team_h' => 2, 'team_a' => 3, 'difficulty' => 2, 'kickoff_time' => '2026-04-21T18:30:00Z'],
            ['event' => 12, 'team_h' => 1, 'team_a' => 4, 'difficulty' => 4, 'kickoff_time' => '2026-04-21T18:30:00Z'],
            ['event' => 12, 'team_h' => 6, 'team_a' => 5, 'difficulty' => 3, 'kickoff_time' => '2026-04-21T18:30:00Z'],
        ],
        'elements' => [
            ['id' => 1, 'photo' => '1', 'first_name' => 'Jordan', 'second_name' => 'Pickford', 'web_name' => 'Pickford', 'team' => 5, 'element_type' => 1, 'now_cost' => 50, 'form' => '3.8', 'points_per_game' => '4.2', 'minutes' => 2700, 'ict_index' => '34.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '24.0', 'total_points' => 145, 'goals_scored' => 0, 'assists' => 0],
            ['id' => 2, 'photo' => '2', 'first_name' => 'Gabriel', 'second_name' => 'Magalhaes', 'web_name' => 'Gabriel', 'team' => 3, 'element_type' => 2, 'now_cost' => 55, 'form' => '5.0', 'points_per_game' => '4.8', 'minutes' => 2600, 'ict_index' => '48.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '34.0', 'total_points' => 160, 'goals_scored' => 4, 'assists' => 1],
            ['id' => 3, 'photo' => '3', 'first_name' => 'William', 'second_name' => 'Saliba', 'web_name' => 'Saliba', 'team' => 3, 'element_type' => 2, 'now_cost' => 56, 'form' => '4.7', 'points_per_game' => '4.5', 'minutes' => 2600, 'ict_index' => '42.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '31.0', 'total_points' => 150, 'goals_scored' => 2, 'assists' => 0],
            ['id' => 4, 'photo' => '4', 'first_name' => 'Virgil', 'second_name' => 'van Dijk', 'web_name' => 'Van Dijk', 'team' => 1, 'element_type' => 2, 'now_cost' => 60, 'form' => '4.4', 'points_per_game' => '4.1', 'minutes' => 2650, 'ict_index' => '39.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '22.0', 'total_points' => 148, 'goals_scored' => 3, 'assists' => 1],
            ['id' => 5, 'photo' => '5', 'first_name' => 'Mohamed', 'second_name' => 'Salah', 'web_name' => 'Salah', 'team' => 1, 'element_type' => 3, 'now_cost' => 135, 'form' => '8.8', 'points_per_game' => '7.9', 'minutes' => 2800, 'ict_index' => '120.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '68.0', 'total_points' => 240, 'goals_scored' => 16, 'assists' => 8],
            ['id' => 6, 'photo' => '6', 'first_name' => 'Phil', 'second_name' => 'Foden', 'web_name' => 'Foden', 'team' => 2, 'element_type' => 3, 'now_cost' => 90, 'form' => '6.9', 'points_per_game' => '6.0', 'minutes' => 2200, 'ict_index' => '92.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '40.0', 'total_points' => 180, 'goals_scored' => 11, 'assists' => 7],
            ['id' => 7, 'photo' => '7', 'first_name' => 'Cole', 'second_name' => 'Palmer', 'web_name' => 'Palmer', 'team' => 6, 'element_type' => 3, 'now_cost' => 105, 'form' => '7.3', 'points_per_game' => '6.3', 'minutes' => 2500, 'ict_index' => '101.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '55.0', 'total_points' => 200, 'goals_scored' => 14, 'assists' => 9],
            ['id' => 8, 'photo' => '8', 'first_name' => 'Bukayo', 'second_name' => 'Saka', 'web_name' => 'Saka', 'team' => 3, 'element_type' => 3, 'now_cost' => 100, 'form' => '7.1', 'points_per_game' => '6.4', 'minutes' => 2450, 'ict_index' => '98.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '49.0', 'total_points' => 192, 'goals_scored' => 13, 'assists' => 10],
            ['id' => 9, 'photo' => '9', 'first_name' => 'Erling', 'second_name' => 'Haaland', 'web_name' => 'Haaland', 'team' => 2, 'element_type' => 4, 'now_cost' => 155, 'form' => '9.1', 'points_per_game' => '8.4', 'minutes' => 2500, 'ict_index' => '132.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '72.0', 'total_points' => 255, 'goals_scored' => 27, 'assists' => 4],
            ['id' => 10, 'photo' => '10', 'first_name' => 'Ollie', 'second_name' => 'Watkins', 'web_name' => 'Watkins', 'team' => 7, 'element_type' => 4, 'now_cost' => 90, 'form' => '6.4', 'points_per_game' => '5.8', 'minutes' => 2550, 'ict_index' => '86.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '38.0', 'total_points' => 170, 'goals_scored' => 18, 'assists' => 9],
            ['id' => 11, 'photo' => '11', 'first_name' => 'Eddie', 'second_name' => 'Nketiah', 'web_name' => 'Nketiah', 'team' => 3, 'element_type' => 4, 'now_cost' => 50, 'form' => '2.0', 'points_per_game' => '2.1', 'minutes' => 650, 'ict_index' => '24.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '6.0', 'total_points' => 55, 'goals_scored' => 4, 'assists' => 1],
            ['id' => 12, 'photo' => '12', 'first_name' => 'Emiliano', 'second_name' => 'Martinez', 'web_name' => 'Martinez', 'team' => 7, 'element_type' => 1, 'now_cost' => 50, 'form' => '4.0', 'points_per_game' => '4.0', 'minutes' => 2700, 'ict_index' => '31.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '20.0', 'total_points' => 140, 'goals_scored' => 0, 'assists' => 0],
            ['id' => 13, 'photo' => '13', 'first_name' => 'Kieran', 'second_name' => 'Trippier', 'web_name' => 'Trippier', 'team' => 4, 'element_type' => 2, 'now_cost' => 65, 'form' => '3.2', 'points_per_game' => '3.8', 'minutes' => 2100, 'ict_index' => '36.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '18.0', 'total_points' => 120, 'goals_scored' => 1, 'assists' => 6],
            ['id' => 14, 'photo' => '14', 'first_name' => 'Anthony', 'second_name' => 'Gordon', 'web_name' => 'Gordon', 'team' => 4, 'element_type' => 3, 'now_cost' => 75, 'form' => '5.3', 'points_per_game' => '5.1', 'minutes' => 2200, 'ict_index' => '71.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '28.0', 'total_points' => 155, 'goals_scored' => 10, 'assists' => 8],
            ['id' => 15, 'photo' => '15', 'first_name' => 'Yoane', 'second_name' => 'Wissa', 'web_name' => 'Wissa', 'team' => 4, 'element_type' => 4, 'now_cost' => 70, 'form' => '4.6', 'points_per_game' => '4.9', 'minutes' => 2050, 'ict_index' => '64.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '12.0', 'total_points' => 138, 'goals_scored' => 11, 'assists' => 3],
            ['id' => 16, 'photo' => '16', 'first_name' => 'Alexander', 'second_name' => 'Isak', 'web_name' => 'Isak', 'team' => 4, 'element_type' => 4, 'now_cost' => 75, 'form' => '8.0', 'points_per_game' => '7.2', 'minutes' => 2150, 'ict_index' => '110.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '44.0', 'total_points' => 195, 'goals_scored' => 20, 'assists' => 5],
        ],
    ];

    $team = ['id' => 1, 'name' => 'Test FC'];
    $picks = [
        'active_chip' => null,
        'entry_history' => [
            'bank' => 25,
            'value' => 1015,
            'event_transfers' => 1,
            'points' => 71,
            'total_points' => 1123,
        ],
        'picks' => [
            ['element' => 1, 'position' => 1, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 2, 'position' => 2, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 3, 'position' => 3, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 4, 'position' => 4, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 5, 'position' => 5, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 6, 'position' => 6, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 7, 'position' => 7, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 8, 'position' => 8, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 9, 'position' => 9, 'multiplier' => 1, 'is_captain' => true, 'is_vice_captain' => false],
            ['element' => 10, 'position' => 10, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 11, 'position' => 11, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 12, 'position' => 12, 'multiplier' => 0, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 13, 'position' => 13, 'multiplier' => 0, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 14, 'position' => 14, 'multiplier' => 0, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 15, 'position' => 15, 'multiplier' => 0, 'is_captain' => false, 'is_vice_captain' => false],
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

        if (str_contains($url, '/entry/1/event/12/picks/')) {
            return Http::response($picks);
        }

        if (str_contains($url, '/entry/1/')) {
            return Http::response($team);
        }

        return Http::response([], 404);
    });

    $recommendedCaptain = $this->getJson('/api/fpl/optimizer/recommended-captain?team_id=1');
    $recommendedCaptain->assertOk();
    $recommendedCaptain->assertJsonPath('data.player.name', 'Erling Haaland');

    $currentStartingXi = $this->getJson('/api/fpl/optimizer/starting-xi?team_id=1&view=current');
    $currentStartingXi->assertOk();
    $currentStartingXi->assertJsonPath('data.view', 'current');
    expect(collect($currentStartingXi->json('data.starting_xi'))->pluck('name')->all())->toContain('Eddie Nketiah');

    $recommendedStartingXi = $this->getJson('/api/fpl/optimizer/starting-xi?team_id=1&view=recommended');
    $recommendedStartingXi->assertOk();
    $recommendedStartingXi->assertJsonPath('data.view', 'recommended');
    expect(collect($recommendedStartingXi->json('data.starting_xi'))->pluck('name')->all())->not->toEqual(
        collect($currentStartingXi->json('data.starting_xi'))->pluck('name')->all()
    );

    $currentBench = $this->getJson('/api/fpl/optimizer/bench?team_id=1&view=current');
    $currentBench->assertOk();
    $currentBench->assertJsonPath('data.view', 'current');
    $currentBench->assertJsonPath('data.players.0.name', 'Emiliano Martinez');

    $recommendedBench = $this->getJson('/api/fpl/optimizer/bench?team_id=1&view=recommended');
    $recommendedBench->assertOk();
    $recommendedBench->assertJsonPath('data.view', 'recommended');
    expect($recommendedBench->json('data.players.0.name'))->not->toBe('Emiliano Martinez');

    $insights = $this->getJson('/api/fpl/optimizer/insights?team_id=1&view=recommended');
    $insights->assertOk();
    $insights->assertJsonPath('data.view', 'recommended');
    expect($insights->json('data.headline'))->toContain('Recommended XI projects');
    expect((float) $insights->json('data.projected_gain'))->toBeGreaterThan(0);
});

test('transfer page endpoint returns card-based strategy payloads', function () {
    Cache::flush();

    $bootstrap = [
        'element_types' => [
            ['id' => 1, 'singular_name_short' => 'GK', 'singular_name' => 'Goalkeeper'],
            ['id' => 2, 'singular_name_short' => 'DEF', 'singular_name' => 'Defender'],
            ['id' => 3, 'singular_name_short' => 'MID', 'singular_name' => 'Midfielder'],
            ['id' => 4, 'singular_name_short' => 'FWD', 'singular_name' => 'Forward'],
        ],
        'teams' => [
            ['id' => 1, 'name' => 'Liverpool', 'short_name' => 'LIV'],
            ['id' => 2, 'name' => 'Manchester City', 'short_name' => 'MCI'],
            ['id' => 3, 'name' => 'Arsenal', 'short_name' => 'ARS'],
            ['id' => 4, 'name' => 'Newcastle United', 'short_name' => 'NEW'],
            ['id' => 5, 'name' => 'Everton', 'short_name' => 'EVE'],
            ['id' => 6, 'name' => 'Chelsea', 'short_name' => 'CHE'],
            ['id' => 7, 'name' => 'Aston Villa', 'short_name' => 'AVL'],
        ],
        'events' => [
            ['id' => 12, 'name' => 'Gameweek 12', 'deadline_time' => '2026-04-21T18:30:00Z', 'is_current' => true, 'is_next' => false],
            ['id' => 13, 'name' => 'Gameweek 13', 'deadline_time' => '2026-04-28T18:30:00Z', 'is_current' => false, 'is_next' => true],
        ],
        'fixtures' => [
            ['event' => 12, 'team_h' => 2, 'team_a' => 3, 'difficulty' => 2, 'kickoff_time' => '2026-04-21T18:30:00Z'],
            ['event' => 12, 'team_h' => 1, 'team_a' => 4, 'difficulty' => 4, 'kickoff_time' => '2026-04-21T18:30:00Z'],
            ['event' => 12, 'team_h' => 6, 'team_a' => 5, 'difficulty' => 3, 'kickoff_time' => '2026-04-21T18:30:00Z'],
            ['event' => 13, 'team_h' => 4, 'team_a' => 1, 'difficulty' => 2, 'kickoff_time' => '2026-04-28T18:30:00Z'],
            ['event' => 13, 'team_h' => 3, 'team_a' => 2, 'difficulty' => 4, 'kickoff_time' => '2026-04-28T18:30:00Z'],
        ],
        'elements' => [
            ['id' => 1, 'photo' => '1', 'first_name' => 'Jordan', 'second_name' => 'Pickford', 'web_name' => 'Pickford', 'team' => 5, 'element_type' => 1, 'now_cost' => 50, 'form' => '3.8', 'points_per_game' => '4.2', 'minutes' => 2700, 'ict_index' => '34.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '24.0', 'total_points' => 145, 'goals_scored' => 0, 'assists' => 0],
            ['id' => 2, 'photo' => '2', 'first_name' => 'Gabriel', 'second_name' => 'Magalhaes', 'web_name' => 'Gabriel', 'team' => 3, 'element_type' => 2, 'now_cost' => 55, 'form' => '5.0', 'points_per_game' => '4.8', 'minutes' => 2600, 'ict_index' => '48.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '34.0', 'total_points' => 160, 'goals_scored' => 4, 'assists' => 1],
            ['id' => 3, 'photo' => '3', 'first_name' => 'William', 'second_name' => 'Saliba', 'web_name' => 'Saliba', 'team' => 3, 'element_type' => 2, 'now_cost' => 56, 'form' => '4.7', 'points_per_game' => '4.5', 'minutes' => 2600, 'ict_index' => '42.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '31.0', 'total_points' => 150, 'goals_scored' => 2, 'assists' => 0],
            ['id' => 4, 'photo' => '4', 'first_name' => 'Virgil', 'second_name' => 'van Dijk', 'web_name' => 'Van Dijk', 'team' => 1, 'element_type' => 2, 'now_cost' => 60, 'form' => '4.4', 'points_per_game' => '4.1', 'minutes' => 2650, 'ict_index' => '39.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '22.0', 'total_points' => 148, 'goals_scored' => 3, 'assists' => 1],
            ['id' => 5, 'photo' => '5', 'first_name' => 'Mohamed', 'second_name' => 'Salah', 'web_name' => 'Salah', 'team' => 1, 'element_type' => 3, 'now_cost' => 135, 'form' => '8.8', 'points_per_game' => '7.9', 'minutes' => 2800, 'ict_index' => '120.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '68.0', 'total_points' => 240, 'goals_scored' => 16, 'assists' => 8],
            ['id' => 6, 'photo' => '6', 'first_name' => 'Phil', 'second_name' => 'Foden', 'web_name' => 'Foden', 'team' => 2, 'element_type' => 3, 'now_cost' => 90, 'form' => '6.9', 'points_per_game' => '6.0', 'minutes' => 2200, 'ict_index' => '92.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '40.0', 'total_points' => 180, 'goals_scored' => 11, 'assists' => 7],
            ['id' => 7, 'photo' => '7', 'first_name' => 'Cole', 'second_name' => 'Palmer', 'web_name' => 'Palmer', 'team' => 6, 'element_type' => 3, 'now_cost' => 105, 'form' => '7.3', 'points_per_game' => '6.3', 'minutes' => 2500, 'ict_index' => '101.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '55.0', 'total_points' => 200, 'goals_scored' => 14, 'assists' => 9],
            ['id' => 8, 'photo' => '8', 'first_name' => 'Bukayo', 'second_name' => 'Saka', 'web_name' => 'Saka', 'team' => 3, 'element_type' => 3, 'now_cost' => 100, 'form' => '7.1', 'points_per_game' => '6.4', 'minutes' => 2450, 'ict_index' => '98.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '49.0', 'total_points' => 192, 'goals_scored' => 13, 'assists' => 10],
            ['id' => 9, 'photo' => '9', 'first_name' => 'Erling', 'second_name' => 'Haaland', 'web_name' => 'Haaland', 'team' => 2, 'element_type' => 4, 'now_cost' => 155, 'form' => '9.1', 'points_per_game' => '8.4', 'minutes' => 2500, 'ict_index' => '132.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '72.0', 'total_points' => 255, 'goals_scored' => 27, 'assists' => 4],
            ['id' => 10, 'photo' => '10', 'first_name' => 'Ollie', 'second_name' => 'Watkins', 'web_name' => 'Watkins', 'team' => 7, 'element_type' => 4, 'now_cost' => 90, 'form' => '6.4', 'points_per_game' => '5.8', 'minutes' => 2550, 'ict_index' => '86.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '38.0', 'total_points' => 170, 'goals_scored' => 18, 'assists' => 9],
            ['id' => 11, 'photo' => '11', 'first_name' => 'Eddie', 'second_name' => 'Nketiah', 'web_name' => 'Nketiah', 'team' => 3, 'element_type' => 4, 'now_cost' => 50, 'form' => '2.0', 'points_per_game' => '2.1', 'minutes' => 650, 'ict_index' => '24.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '6.0', 'total_points' => 55, 'goals_scored' => 4, 'assists' => 1],
            ['id' => 12, 'photo' => '12', 'first_name' => 'Emiliano', 'second_name' => 'Martinez', 'web_name' => 'Martinez', 'team' => 7, 'element_type' => 1, 'now_cost' => 50, 'form' => '4.0', 'points_per_game' => '4.0', 'minutes' => 2700, 'ict_index' => '31.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '20.0', 'total_points' => 140, 'goals_scored' => 0, 'assists' => 0],
            ['id' => 13, 'photo' => '13', 'first_name' => 'Kieran', 'second_name' => 'Trippier', 'web_name' => 'Trippier', 'team' => 4, 'element_type' => 2, 'now_cost' => 65, 'form' => '3.2', 'points_per_game' => '3.8', 'minutes' => 2100, 'ict_index' => '36.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '18.0', 'total_points' => 120, 'goals_scored' => 1, 'assists' => 6],
            ['id' => 14, 'photo' => '14', 'first_name' => 'Anthony', 'second_name' => 'Gordon', 'web_name' => 'Gordon', 'team' => 4, 'element_type' => 3, 'now_cost' => 75, 'form' => '5.3', 'points_per_game' => '5.1', 'minutes' => 2200, 'ict_index' => '71.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '28.0', 'total_points' => 155, 'goals_scored' => 10, 'assists' => 8],
            ['id' => 15, 'photo' => '15', 'first_name' => 'Yoane', 'second_name' => 'Wissa', 'web_name' => 'Wissa', 'team' => 4, 'element_type' => 4, 'now_cost' => 70, 'form' => '4.6', 'points_per_game' => '4.9', 'minutes' => 2050, 'ict_index' => '64.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '12.0', 'total_points' => 138, 'goals_scored' => 11, 'assists' => 3],
            ['id' => 16, 'photo' => '16', 'first_name' => 'Alexander', 'second_name' => 'Isak', 'web_name' => 'Isak', 'team' => 4, 'element_type' => 4, 'now_cost' => 75, 'form' => '8.0', 'points_per_game' => '7.2', 'minutes' => 2150, 'ict_index' => '110.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '44.0', 'total_points' => 195, 'goals_scored' => 20, 'assists' => 5],
        ],
    ];
    $team = ['id' => 1, 'name' => 'Test FC'];
    $picks = [
        'active_chip' => null,
        'entry_history' => [
            'bank' => 25,
            'value' => 1015,
            'event_transfers' => 1,
            'points' => 71,
            'total_points' => 1123,
        ],
        'picks' => [
            ['element' => 1, 'position' => 1, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 2, 'position' => 2, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 3, 'position' => 3, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 4, 'position' => 4, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 5, 'position' => 5, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 6, 'position' => 6, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 7, 'position' => 7, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 8, 'position' => 8, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 9, 'position' => 9, 'multiplier' => 1, 'is_captain' => true, 'is_vice_captain' => false],
            ['element' => 10, 'position' => 10, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 11, 'position' => 11, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 12, 'position' => 12, 'multiplier' => 0, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 13, 'position' => 13, 'multiplier' => 0, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 14, 'position' => 14, 'multiplier' => 0, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 15, 'position' => 15, 'multiplier' => 0, 'is_captain' => false, 'is_vice_captain' => false],
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

        if (str_contains($url, '/entry/1/event/12/picks/')) {
            return Http::response($picks);
        }

        if (str_contains($url, '/entry/1/')) {
            return Http::response($team);
        }

        return Http::response([], 404);
    });

    $response = $this->getJson('/api/fpl/transfer-page?team_id=1');

    $response->assertOk();
    $response->assertJsonPath('status', true);
    $response->assertJsonPath('data.primary_transfer.player_in.name', 'Alexander Isak');
    $response->assertJsonPath('data.primary_transfer.label', 'Apply Transfer');
    $response->assertJsonCount(3, 'data.transfer_options');
    $response->assertJsonPath('data.transfer_options.0.title', 'Best 2 Transfer Combination');
    $response->assertJsonPath('data.transfer_options.1.title', 'Safe Transfer Option');
    $response->assertJsonPath('data.transfer_options.2.title', 'Aggressive/Differential Option');
    $response->assertJsonStructure([
        'data' => [
            'team',
            'current_gameweek',
            'summary' => [
                'headline',
                'subheadline',
                'available_transfers',
                'bank',
                'team_value',
                'projected_gain',
            ],
            'primary_transfer' => [
                'label',
                'player_out',
                'player_in',
                'points_gain',
                'reason',
                'status',
                'badge',
                'meta',
            ],
            'transfer_note',
            'transfer_options',
            'insights' => [
                'headline',
                'bullets',
            ],
        ],
    ]);
});

test('fixtures page endpoint returns grouped fixtures and fdr matrix payloads', function () {
    Cache::flush();

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
            ['id' => 3, 'name' => 'Bournemouth', 'short_name' => 'BOU'],
            ['id' => 4, 'name' => 'Brentford', 'short_name' => 'BRE'],
        ],
        'events' => [
            ['id' => 30, 'name' => 'Gameweek 30', 'deadline_time' => '2026-03-14T10:30:00Z', 'is_current' => true, 'is_next' => false],
            ['id' => 31, 'name' => 'Gameweek 31', 'deadline_time' => '2026-03-21T10:30:00Z', 'is_current' => false, 'is_next' => true],
            ['id' => 32, 'name' => 'Gameweek 32', 'deadline_time' => '2026-03-28T10:30:00Z', 'is_current' => false, 'is_next' => false],
        ],
        'fixtures' => [
            ['event' => 30, 'team_h' => 1, 'team_a' => 3, 'difficulty' => 2, 'kickoff_time' => '2026-03-14T12:00:00Z'],
            ['event' => 30, 'team_h' => 2, 'team_a' => 4, 'difficulty' => 4, 'kickoff_time' => '2026-03-14T15:00:00Z'],
            ['event' => 30, 'team_h' => 4, 'team_a' => 1, 'difficulty' => 3, 'kickoff_time' => '2026-03-15T13:00:00Z'],
            ['event' => 31, 'team_h' => 3, 'team_a' => 1, 'difficulty' => 3, 'kickoff_time' => '2026-03-21T13:00:00Z'],
            ['event' => 32, 'team_h' => 4, 'team_a' => 2, 'difficulty' => 5, 'kickoff_time' => '2026-03-28T17:30:00Z'],
        ],
        'elements' => [],
    ];

    Http::fake(function (HttpRequest $request) use ($bootstrap) {
        $url = $request->url();

        if (str_contains($url, '/bootstrap-static/')) {
            return Http::response($bootstrap);
        }

        if (str_contains($url, '/fixtures/?event=30')) {
            return Http::response(array_values(array_filter($bootstrap['fixtures'], fn (array $fixture): bool => (int) $fixture['event'] === 30)));
        }

        if (str_contains($url, '/fixtures/?event=31')) {
            return Http::response(array_values(array_filter($bootstrap['fixtures'], fn (array $fixture): bool => (int) $fixture['event'] === 31)));
        }

        if (str_contains($url, '/fixtures/?event=32')) {
            return Http::response(array_values(array_filter($bootstrap['fixtures'], fn (array $fixture): bool => (int) $fixture['event'] === 32)));
        }

        if (str_contains($url, '/fixtures/')) {
            return Http::response($bootstrap['fixtures']);
        }

        return Http::response([], 404);
    });

    $fixturesResponse = $this->getJson('/api/fpl/fixtures-page?view=fixtures&gameweek=30');
    $fixturesResponse->assertOk();
    $fixturesResponse->assertJsonPath('data.view', 'fixtures');
    $fixturesResponse->assertJsonPath('data.navigation.selected_gameweek', 30);
    $fixturesResponse->assertJsonCount(2, 'data.fixtures');
    $fixturesResponse->assertJsonPath('data.fixtures.0.date', 'Sat 14 Mar');
    $fixturesResponse->assertJsonPath('data.fixtures.0.fixtures.0.home_team.short_name', 'ARS');
    $fixturesResponse->assertJsonPath('data.fixtures.0.fixtures.0.away_team.short_name', 'BOU');
    $fixturesResponse->assertJsonPath('data.fixtures.0.fixtures.0.kickoff_time_formatted', '12:00');

    $fdrResponse = $this->getJson('/api/fpl/fixtures-page?view=fdr&gameweek=30');
    $fdrResponse->assertOk();
    $fdrResponse->assertJsonPath('data.view', 'fdr');
    $fdrResponse->assertJsonPath('data.fdr.selected_gameweek', 30);
    $fdrResponse->assertJsonCount(3, 'data.fdr.columns');
    $fdrResponse->assertJsonCount(4, 'data.fdr.rows');
    $fdrResponse->assertJsonPath('data.fdr.rows.0.team.short_name', 'ARS');
    $fdrResponse->assertJsonPath('data.fdr.rows.0.fixtures.0.label', 'BOU (H)');
    $fdrResponse->assertJsonPath('data.fdr.rows.0.fixtures.0.difficulty', 2);
    $fdrResponse->assertJsonCount(5, 'data.fdr_key');
});

test('chat endpoint uses authenticated user context and streams on demand', function () {
    Cache::flush();

    $user = User::factory()->make([
        'id' => 91,
        'name' => 'Alex Mercer',
        'email' => 'alex@example.com',
        'fpl_team_id' => 30,
    ]);

    Sanctum::actingAs($user);

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
            ['id' => 3, 'name' => 'Bournemouth', 'short_name' => 'BOU'],
            ['id' => 4, 'name' => 'Brentford', 'short_name' => 'BRE'],
        ],
        'events' => [
            ['id' => 30, 'name' => 'Gameweek 30', 'deadline_time' => '2026-03-14T10:30:00Z', 'is_current' => true, 'is_next' => false],
        ],
        'fixtures' => [
            ['event' => 30, 'team_h' => 1, 'team_a' => 3, 'difficulty' => 2, 'kickoff_time' => '2026-03-14T12:00:00Z'],
        ],
        'elements' => [
            ['id' => 1, 'photo' => '1', 'first_name' => 'Jordan', 'second_name' => 'Pickford', 'web_name' => 'Pickford', 'team' => 5, 'element_type' => 1, 'now_cost' => 50, 'form' => '3.8', 'points_per_game' => '4.2', 'minutes' => 2700, 'ict_index' => '34.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '24.0', 'total_points' => 145, 'goals_scored' => 0, 'assists' => 0],
            ['id' => 5, 'photo' => '5', 'first_name' => 'Mohamed', 'second_name' => 'Salah', 'web_name' => 'Salah', 'team' => 1, 'element_type' => 3, 'now_cost' => 135, 'form' => '8.8', 'points_per_game' => '7.9', 'minutes' => 2800, 'ict_index' => '120.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '68.0', 'total_points' => 240, 'goals_scored' => 16, 'assists' => 8],
            ['id' => 9, 'photo' => '9', 'first_name' => 'Erling', 'second_name' => 'Haaland', 'web_name' => 'Haaland', 'team' => 2, 'element_type' => 4, 'now_cost' => 155, 'form' => '9.1', 'points_per_game' => '8.4', 'minutes' => 2500, 'ict_index' => '132.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '72.0', 'total_points' => 255, 'goals_scored' => 27, 'assists' => 4],
            ['id' => 10, 'photo' => '10', 'first_name' => 'Ollie', 'second_name' => 'Watkins', 'web_name' => 'Watkins', 'team' => 7, 'element_type' => 4, 'now_cost' => 90, 'form' => '6.4', 'points_per_game' => '5.8', 'minutes' => 2550, 'ict_index' => '86.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '38.0', 'total_points' => 170, 'goals_scored' => 18, 'assists' => 9],
        ],
    ];
    $team = ['id' => 30, 'name' => 'Alex FC'];
    $picks = [
        'active_chip' => null,
        'entry_history' => [
            'bank' => 25,
            'value' => 1015,
            'event_transfers' => 1,
        ],
        'picks' => [
            ['element' => 5, 'position' => 1, 'multiplier' => 1, 'is_captain' => true, 'is_vice_captain' => false],
            ['element' => 9, 'position' => 2, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
            ['element' => 10, 'position' => 3, 'multiplier' => 1, 'is_captain' => false, 'is_vice_captain' => false],
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

        if (str_contains($url, '/entry/30/event/30/picks/')) {
            return Http::response($picks);
        }

        if (str_contains($url, '/entry/30/')) {
            return Http::response($team);
        }

        return Http::response([], 404);
    });

    FplChatAssistant::fake(function (string $prompt) {
        expect($prompt)->toContain('Alex Mercer');
        expect($prompt)->toContain('"team_id": 30');
        expect($prompt)->toContain('"fpl_team_id": 30');

        return 'You should captain Salah and bench Pickford.';
    });

    $jsonResponse = $this->postJson('/api/fpl/chat', [
        'message' => 'Who should I captain?',
    ]);

    $jsonResponse->assertOk();
    $jsonResponse->assertJsonPath('status', true);
    $jsonResponse->assertJsonPath('data.user.name', 'Alex Mercer');
    $jsonResponse->assertJsonPath('data.team_id', 30);
    expect($jsonResponse->json('data.message'))->toContain('You should captain Salah');

    $streamResponse = $this->post('/api/fpl/chat/stream', [
        'message' => 'Who should I captain?',
    ]);

    $streamResponse->assertOk();
    expect($streamResponse->headers->get('content-type'))->toContain('text/event-stream');
    expect($streamResponse->streamedContent())->toContain('data:');
    expect($streamResponse->streamedContent())->toContain('"type":"text_delta"');
    expect($streamResponse->streamedContent())->toContain('"delta":" Salah"');
});

test('player search endpoint filters results and marks owned players', function () {
    Cache::flush();

    $bootstrap = [
        'element_types' => [
            ['id' => 1, 'singular_name_short' => 'GK', 'singular_name' => 'Goalkeeper'],
            ['id' => 2, 'singular_name_short' => 'DEF', 'singular_name' => 'Defender'],
            ['id' => 3, 'singular_name_short' => 'MID', 'singular_name' => 'Midfielder'],
            ['id' => 4, 'singular_name_short' => 'FWD', 'singular_name' => 'Forward'],
        ],
        'teams' => [
            ['id' => 1, 'name' => 'Liverpool', 'short_name' => 'LIV'],
            ['id' => 2, 'name' => 'Manchester City', 'short_name' => 'MCI'],
            ['id' => 3, 'name' => 'Arsenal', 'short_name' => 'ARS'],
            ['id' => 4, 'name' => 'Newcastle United', 'short_name' => 'NEW'],
            ['id' => 5, 'name' => 'Everton', 'short_name' => 'EVE'],
            ['id' => 6, 'name' => 'Chelsea', 'short_name' => 'CHE'],
            ['id' => 7, 'name' => 'Aston Villa', 'short_name' => 'AVL'],
        ],
        'events' => [
            ['id' => 12, 'name' => 'Gameweek 12', 'deadline_time' => '2026-04-21T18:30:00Z', 'is_current' => true, 'is_next' => false],
        ],
        'fixtures' => [],
        'elements' => [
            ['id' => 5, 'photo' => '5', 'first_name' => 'Mohamed', 'second_name' => 'Salah', 'web_name' => 'Salah', 'team' => 1, 'element_type' => 3, 'now_cost' => 135, 'form' => '8.8', 'points_per_game' => '7.9', 'minutes' => 2800, 'ict_index' => '120.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '68.0', 'total_points' => 240, 'goals_scored' => 16, 'assists' => 8],
            ['id' => 9, 'photo' => '9', 'first_name' => 'Erling', 'second_name' => 'Haaland', 'web_name' => 'Haaland', 'team' => 2, 'element_type' => 4, 'now_cost' => 155, 'form' => '9.1', 'points_per_game' => '8.4', 'minutes' => 2500, 'ict_index' => '132.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '72.0', 'total_points' => 255, 'goals_scored' => 27, 'assists' => 4],
        ],
    ];
    $team = ['id' => 1, 'name' => 'Test FC'];
    $picks = [
        'active_chip' => null,
        'entry_history' => ['bank' => 25, 'value' => 1015, 'event_transfers' => 1],
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
            return Http::response([]);
        }

        if (str_contains($url, '/entry/1/event/12/picks/')) {
            return Http::response($picks);
        }

        if (str_contains($url, '/entry/1/')) {
            return Http::response($team);
        }

        return Http::response([], 404);
    });

    $response = $this->getJson('/api/fpl/players?team_id=1&search=Salah&limit=5');

    $response->assertOk();
    $response->assertJsonPath('status', true);
    $response->assertJsonPath('data.count', 1);
    $response->assertJsonPath('data.players.0.name', 'Mohamed Salah');
    $response->assertJsonPath('data.players.0.photo_url', 'https://resources.premierleague.com/premierleague/photos/players/40x40/p5.png');
    $response->assertJsonPath('data.players.0.is_owned_by_team', true);
});

test('players endpoint paginates and applies search price and team filters', function () {
    Cache::flush();

    $bootstrap = [
        'element_types' => [
            ['id' => 1, 'singular_name_short' => 'GK', 'singular_name' => 'Goalkeeper'],
            ['id' => 2, 'singular_name_short' => 'DEF', 'singular_name' => 'Defender'],
            ['id' => 3, 'singular_name_short' => 'MID', 'singular_name' => 'Midfielder'],
            ['id' => 4, 'singular_name_short' => 'FWD', 'singular_name' => 'Forward'],
        ],
        'teams' => [
            ['id' => 1, 'name' => 'Liverpool', 'short_name' => 'LIV'],
            ['id' => 2, 'name' => 'Manchester City', 'short_name' => 'MCI'],
        ],
        'events' => [
            ['id' => 12, 'name' => 'Gameweek 12', 'deadline_time' => '2026-04-21T18:30:00Z', 'is_current' => true, 'is_next' => false],
        ],
        'fixtures' => [],
        'elements' => [
            ['id' => 5, 'photo' => '5', 'first_name' => 'Mohamed', 'second_name' => 'Salah', 'web_name' => 'Salah', 'team' => 1, 'element_type' => 3, 'now_cost' => 135, 'form' => '8.8', 'points_per_game' => '7.9', 'minutes' => 2800, 'ict_index' => '120.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '68.0', 'total_points' => 240, 'goals_scored' => 16, 'assists' => 8],
            ['id' => 6, 'photo' => '6', 'first_name' => 'Luis', 'second_name' => 'Diaz', 'web_name' => 'Diaz', 'team' => 1, 'element_type' => 3, 'now_cost' => 78, 'form' => '6.1', 'points_per_game' => '5.7', 'minutes' => 2200, 'ict_index' => '88.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '25.0', 'total_points' => 170, 'goals_scored' => 12, 'assists' => 7],
            ['id' => 9, 'photo' => '9', 'first_name' => 'Erling', 'second_name' => 'Haaland', 'web_name' => 'Haaland', 'team' => 2, 'element_type' => 4, 'now_cost' => 155, 'form' => '9.1', 'points_per_game' => '8.4', 'minutes' => 2500, 'ict_index' => '132.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '72.0', 'total_points' => 255, 'goals_scored' => 27, 'assists' => 4],
        ],
    ];
    $team = ['id' => 1, 'name' => 'Test FC'];
    $picks = [
        'active_chip' => null,
        'entry_history' => ['bank' => 25, 'value' => 1015, 'event_transfers' => 1],
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
            return Http::response([]);
        }

        if (str_contains($url, '/entry/1/event/12/picks/')) {
            return Http::response($picks);
        }

        if (str_contains($url, '/entry/1/')) {
            return Http::response($team);
        }

        return Http::response([], 404);
    });

    $pageOne = $this->getJson('/api/fpl/players?team_id=1&per_page=1&page=1&max_price=14.0');
    $pageOne->assertOk();
    $pageOne->assertJsonPath('data.meta.current_page', 1);
    $pageOne->assertJsonPath('data.meta.per_page', 1);
    $pageOne->assertJsonPath('data.meta.total', 2);
    $pageOne->assertJsonPath('data.meta.last_page', 2);
    $pageOne->assertJsonPath('data.players.0.name', 'Mohamed Salah');

    $pageTwo = $this->getJson('/api/fpl/players?team_id=1&per_page=1&page=2&max_price=14.0');
    $pageTwo->assertOk();
    $pageTwo->assertJsonPath('data.meta.current_page', 2);
    $pageTwo->assertJsonPath('data.players.0.name', 'Luis Diaz');
    $pageTwo->assertJsonPath('data.players.0.team.short_name', 'LIV');
});

test('player detail endpoint returns the player summary payload', function () {
    Cache::flush();

    $bootstrap = [
        'element_types' => [
            ['id' => 1, 'singular_name_short' => 'GK', 'singular_name' => 'Goalkeeper'],
            ['id' => 2, 'singular_name_short' => 'DEF', 'singular_name' => 'Defender'],
            ['id' => 3, 'singular_name_short' => 'MID', 'singular_name' => 'Midfielder'],
            ['id' => 4, 'singular_name_short' => 'FWD', 'singular_name' => 'Forward'],
        ],
        'teams' => [
            ['id' => 1, 'name' => 'Liverpool', 'short_name' => 'LIV'],
            ['id' => 2, 'name' => 'Manchester City', 'short_name' => 'MCI'],
            ['id' => 3, 'name' => 'Arsenal', 'short_name' => 'ARS'],
            ['id' => 4, 'name' => 'Newcastle United', 'short_name' => 'NEW'],
            ['id' => 5, 'name' => 'Everton', 'short_name' => 'EVE'],
            ['id' => 6, 'name' => 'Chelsea', 'short_name' => 'CHE'],
            ['id' => 7, 'name' => 'Aston Villa', 'short_name' => 'AVL'],
        ],
        'events' => [
            ['id' => 12, 'name' => 'Gameweek 12', 'deadline_time' => '2026-04-21T18:30:00Z', 'is_current' => true, 'is_next' => false],
        ],
        'fixtures' => [],
        'elements' => [
            ['id' => 9, 'photo' => '9', 'first_name' => 'Erling', 'second_name' => 'Haaland', 'web_name' => 'Haaland', 'team' => 2, 'element_type' => 4, 'now_cost' => 155, 'form' => '9.1', 'points_per_game' => '8.4', 'minutes' => 2500, 'ict_index' => '132.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '72.0', 'total_points' => 255, 'goals_scored' => 27, 'assists' => 4],
        ],
    ];
    $team = ['id' => 1, 'name' => 'Test FC'];
    $picks = ['active_chip' => null, 'entry_history' => ['bank' => 25, 'value' => 1015, 'event_transfers' => 1], 'picks' => []];
    $summary = [
        'history' => [
            [
                'round' => 12,
                'total_points' => 15,
                'minutes' => 90,
                'starts' => 1,
                'bonus' => 3,
            ],
        ],
        'fixtures' => [
            ['event' => 12, 'team_h' => 2, 'team_a' => 1, 'difficulty' => 2, 'kickoff_time' => '2026-04-21T18:30:00Z'],
            ['event' => 13, 'team_h' => 3, 'team_a' => 2, 'difficulty' => 4, 'kickoff_time' => '2026-04-28T18:30:00Z'],
            ['event' => 14, 'team_h' => 2, 'team_a' => 4, 'difficulty' => 3, 'kickoff_time' => '2026-05-05T18:30:00Z'],
        ],
        'history_past' => [
            ['season_name' => '2025/26', 'minutes' => 2500, 'starts' => 28],
        ],
    ];

    Http::fake(function (HttpRequest $request) use ($bootstrap, $team, $picks, $summary) {
        $url = $request->url();

        if (str_contains($url, '/bootstrap-static/')) {
            return Http::response($bootstrap);
        }

        if (str_contains($url, '/fixtures/')) {
            return Http::response([]);
        }

        if (str_contains($url, '/entry/1/event/12/picks/')) {
            return Http::response($picks);
        }

        if (str_contains($url, '/entry/1/')) {
            return Http::response($team);
        }

        if (str_contains($url, '/element-summary/9/')) {
            return Http::response($summary);
        }

        return Http::response([], 404);
    });

    $response = $this->getJson('/api/fpl/player/9');

    $response->assertOk();
    $response->assertJsonPath('status', true);
    $response->assertJsonPath('data.player.name', 'Erling Haaland');
    $response->assertJsonPath('data.player.photo_url', 'https://resources.premierleague.com/premierleague/photos/players/40x40/p9.png');
    $response->assertJsonPath('data.player.jersey_url', url('/api/fpl/assets/jersey/2.svg'));
    $response->assertJsonPath('data.team.name', 'Manchester City');
    $response->assertJsonPath('data.summary.history.0.total_points', 15);
    $response->assertJsonPath('data.player_page.summary_card.name', 'Erling Haaland');
    $response->assertJsonPath('data.player_page.summary_card.current_price_display', '£15.5m');
    $response->assertJsonPath('data.player_page.summary_card.expected_mins', 100);
    $response->assertJsonPath('data.player_page.summary_card.expected_mins_label', '100%');
    $response->assertJsonPath('data.player_page.prediction.series.0.gameweek_label', 'GW12');
    $response->assertJsonPath('data.player_page.upcoming_fixtures.0.opponent_team.short_name', 'LIV');
    $response->assertJsonPath('data.player_page.profile.appearances', 1);
    $response->assertJsonPath('data.player_page.career_totals.points', 255);
    $response->assertJsonStructure([
        'data' => [
            'player_page' => [
                'summary_card',
                'prediction' => [
                    'title',
                    'series',
                    'total_projection',
                ],
                'upcoming_fixtures',
                'profile',
                'career_totals',
                'insights',
            ],
        ],
    ]);
});

test('player detail endpoint keeps working when summary host is unavailable', function () {
    Cache::flush();

    $bootstrap = [
        'element_types' => [
            ['id' => 1, 'singular_name_short' => 'GK', 'singular_name' => 'Goalkeeper'],
            ['id' => 2, 'singular_name_short' => 'DEF', 'singular_name' => 'Defender'],
            ['id' => 3, 'singular_name_short' => 'MID', 'singular_name' => 'Midfielder'],
            ['id' => 4, 'singular_name_short' => 'FWD', 'singular_name' => 'Forward'],
        ],
        'teams' => [
            ['id' => 1, 'name' => 'Liverpool', 'short_name' => 'LIV'],
            ['id' => 2, 'name' => 'Manchester City', 'short_name' => 'MCI'],
        ],
        'events' => [
            ['id' => 12, 'name' => 'Gameweek 12', 'deadline_time' => '2026-04-21T18:30:00Z', 'is_current' => true, 'is_next' => false],
        ],
        'fixtures' => [],
        'elements' => [
            ['id' => 9, 'photo' => '9', 'first_name' => 'Erling', 'second_name' => 'Haaland', 'web_name' => 'Haaland', 'team' => 2, 'element_type' => 4, 'now_cost' => 155, 'form' => '9.1', 'points_per_game' => '8.4', 'minutes' => 2500, 'ict_index' => '132.0', 'status' => 'a', 'chance_of_playing_next_round' => 100, 'selected_by_percent' => '72.0', 'total_points' => 255, 'goals_scored' => 27, 'assists' => 4],
        ],
    ];
    $team = ['id' => 1, 'name' => 'Test FC'];
    $picks = ['active_chip' => null, 'entry_history' => ['bank' => 25, 'value' => 1015, 'event_transfers' => 1], 'picks' => []];

    Http::fake(function (HttpRequest $request) use ($bootstrap, $team, $picks) {
        $url = $request->url();

        if (str_contains($url, '/bootstrap-static/')) {
            return Http::response($bootstrap);
        }

        if (str_contains($url, '/fixtures/')) {
            return Http::response([]);
        }

        if (str_contains($url, '/entry/1/event/12/picks/')) {
            return Http::response($picks);
        }

        if (str_contains($url, '/entry/1/')) {
            return Http::response($team);
        }

        if (str_contains($url, '/element-summary/9/')) {
            return Http::response([], 500);
        }

        return Http::response([], 404);
    });

    $response = $this->getJson('/api/fpl/player/9');

    $response->assertOk();
    $response->assertJsonPath('status', true);
    $response->assertJsonPath('data.player.name', 'Erling Haaland');
    $response->assertJsonPath('data.summary', null);
    $response->assertJsonPath('data.player_page.summary_card.name', 'Erling Haaland');
    $response->assertJsonPath('data.player_page.upcoming_fixtures', []);
    expect($response->json('data.summary_error'))->toContain('HTTP request returned status code 500');
});

test('team badge and jersey svg endpoints return accessible images', function () {
    $response = $this->get('/api/fpl/assets/badge/16.svg');

    $response->assertOk();
    $response->assertHeader('content-type', 'image/svg+xml; charset=UTF-8');
    expect($response->getContent())->toContain('<svg');
    expect($response->getContent())->toContain('NFO');

    $jerseyResponse = $this->get('/api/fpl/assets/jersey/16.svg');

    $jerseyResponse->assertOk();
    $jerseyResponse->assertHeader('content-type', 'image/svg+xml; charset=UTF-8');
    expect($jerseyResponse->getContent())->toContain('<svg');
    expect($jerseyResponse->getContent())->toContain('NFO');
});
