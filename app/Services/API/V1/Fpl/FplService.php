<?php

namespace App\Services\API\V1\Fpl;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FplService
{
    protected string $baseUrl = 'https://fantasy.premierleague.com/api';

    public function getBootstrapStatic(): array
    {
        return Cache::remember('fpl_bootstrap_static', 3600, function (): array {
            return Http::get("{$this->baseUrl}/bootstrap-static/")->throw()->json();
        });
    }

    public function getTeam(int $teamId): array
    {
        return Cache::remember("fpl_team_{$teamId}", 300, function () use ($teamId): array {
            return Http::get("{$this->baseUrl}/entry/{$teamId}/")->throw()->json();
        });
    }

    public function getPicks(int $teamId, ?int $gameweek = null): array
    {
        $gw = $gameweek ?? $this->getCurrentGameweek();
        $cacheKey = sprintf('fpl_picks_%d_%s', $teamId, $gw ?? 'current');

        return Cache::remember($cacheKey, 120, function () use ($teamId, $gw): array {
            $event = $gw ?? $this->getCurrentGameweek();

            return Http::get("{$this->baseUrl}/entry/{$teamId}/event/{$event}/picks/")->throw()->json();
        });
    }

    public function getFixtures(?int $gameweek = null): array
    {
        $url = $gameweek
            ? "{$this->baseUrl}/fixtures/?event={$gameweek}"
            : "{$this->baseUrl}/fixtures/";
        $cacheKey = sprintf('fpl_fixtures_%s', $gameweek ?? 'all');

        return Cache::remember($cacheKey, 600, function () use ($url): array {
            return Http::get($url)->throw()->json();
        });
    }

    public function getCurrentGameweek(): ?int
    {
        $currentEvent = $this->getCurrentEvent();

        return $currentEvent['id'] ?? null;
    }

    public function getCurrentEvent(): ?array
    {
        $data = $this->getBootstrapStatic();

        foreach ($data['events'] ?? [] as $event) {
            if ($event['is_current'] ?? false) {
                return $event;
            }
        }

        foreach ($data['events'] ?? [] as $event) {
            if ($event['is_next'] ?? false) {
                return $event;
            }
        }

        return null;
    }

    public function getFullTeamData(int $teamId): array
    {
        $context = $this->getTeamContext($teamId);

        return [
            'static' => $context['bootstrap'],
            'team' => $context['team'],
            'picks' => $context['picks'],
            'fixtures' => $context['fixtures'],
            'current_gw' => $context['current_gameweek'],
            'current_event' => $context['current_event'],
            'team_context' => $context,
            'current_squad' => $context['current_squad'],
            'bench_players' => $context['bench_players'],
            'transfer_suggestions' => $this->getTransferSuggestionsFromContext($context),
            'captaincy_suggestion' => $this->getCaptaincySuggestionFromContext($context),
        ];
    }

    public function getPlayerData(int $playerId): array
    {
        $bootstrap = $this->getBootstrapStatic();
        $player = collect($bootstrap['elements'] ?? [])->firstWhere('id', $playerId);

        if (! $player) {
            throw new \RuntimeException('Player not found.');
        }

        $team = $this->resolveTeamNameById((int) ($player['team'] ?? 0), $bootstrap);
        $summary = null;
        $summaryError = null;

        try {
            $summary = Http::get("{$this->baseUrl}/element-summary/{$playerId}/")->throw()->json();
        } catch (\Throwable $throwable) {
            $summaryError = $throwable->getMessage();
        }

        return [
            'player' => $this->formatPlayer($player, $bootstrap, []),
            'team' => $team,
            'summary' => $summary,
            'summary_error' => $summaryError,
            'player_page' => $this->buildPlayerPageData($player, $bootstrap, $summary),
        ];
    }

    /**
     * Build the payload used by the single player detail page.
     */
    protected function buildPlayerPageData(array $player, array $bootstrap, ?array $summary): array
    {
        $summary = $summary ?? [];
        $team = $this->resolveTeamNameById((int) ($player['team'] ?? 0), $bootstrap);
        $playerCard = $this->formatPlayer($player, $bootstrap, []);
        $summaryFixtures = $summary['fixtures'] ?? [];
        $upcomingFixtures = $this->buildPlayerUpcomingFixtures($player, $bootstrap, $summaryFixtures);
        $history = is_array($summary['history'] ?? null) ? $summary['history'] : [];

        return [
            'summary_card' => $this->buildPlayerSummaryCard($playerCard, $player),
            'prediction' => $this->buildPlayerPredictionChart($playerCard, $upcomingFixtures),
            'upcoming_fixtures' => $upcomingFixtures,
            'profile' => $this->buildPlayerProfile($playerCard, $player, $summary, $team, $history),
            'career_totals' => $this->buildPlayerCareerTotals($playerCard, $player, $summary, $history),
            'insights' => $this->buildPlayerInsights($playerCard, $upcomingFixtures, $history),
        ];
    }

    /**
     * Build the summary card shown at the top of the player page.
     */
    protected function buildPlayerSummaryCard(array $playerCard, array $player): array
    {
        $expectedMinutes = (int) ($player['minutes'] ?? 0);
        $availability = (int) ($player['chance_of_playing_next_round'] ?? 100);

        return [
            'name' => $playerCard['name'],
            'team' => $playerCard['team'],
            'position' => [
                'code' => $playerCard['position_code'],
                'name' => $playerCard['position_name'],
            ],
            'current_price' => $playerCard['cost'],
            'current_price_display' => sprintf('£%sm', number_format($playerCard['cost'], 1)),
            'total_points' => $playerCard['total_points'],
            'recent_form' => $playerCard['form'],
            'expected_mins' => $availability,
            'expected_mins_label' => $availability.'%',
            'expected_minutes' => $expectedMinutes > 0 ? $expectedMinutes : null,
            'selected_by_percent' => $playerCard['selected_by_percent'],
            'points_per_game' => $playerCard['points_per_game'],
            'status' => $playerCard['status'],
            'photo_url' => $playerCard['photo_url'],
            'jersey_url' => $playerCard['jersey_url'],
        ];
    }

    /**
     * Build the small prediction chart for the next three gameweeks.
     */
    protected function buildPlayerPredictionChart(array $playerCard, array $upcomingFixtures): array
    {
        $points = [];

        foreach (array_slice($upcomingFixtures, 0, 3) as $fixture) {
            $points[] = [
                'gameweek' => $fixture['event'] ?? null,
                'gameweek_label' => isset($fixture['event']) ? 'GW'.(int) $fixture['event'] : null,
                'fixture_label' => $fixture['label'] ?? null,
                'projected_points' => $fixture['projected_points'] ?? 0.0,
            ];
        }

        return [
            'title' => 'The Prediction (Next 3 GWs)',
            'series' => $points,
            'total_projection' => round(collect($points)->sum('projected_points'), 1),
        ];
    }

    /**
     * Build the next fixtures block for the single player page.
     */
    protected function buildPlayerUpcomingFixtures(array $player, array $bootstrap, array $summaryFixtures): array
    {
        $playerTeamId = (int) ($player['team'] ?? 0);
        $teamsById = collect($bootstrap['teams'] ?? [])->keyBy('id');
        $currentGameweek = $this->getCurrentGameweek();
        $fixtures = collect($summaryFixtures);

        if ($fixtures->isEmpty()) {
            $fixtures = collect($bootstrap['fixtures'] ?? [])
                ->filter(function (array $fixture) use ($playerTeamId, $currentGameweek): bool {
                    $event = (int) ($fixture['event'] ?? 0);

                    if ($currentGameweek !== null && $event !== 0 && $event < $currentGameweek) {
                        return false;
                    }

                    return (int) ($fixture['team_h'] ?? 0) === $playerTeamId
                        || (int) ($fixture['team_a'] ?? 0) === $playerTeamId;
                });
        }

        return $fixtures
            ->sortBy(function (array $fixture): array {
                return [
                    (int) ($fixture['event'] ?? 0),
                    $fixture['kickoff_time'] ?? '',
                ];
            })
            ->map(function (array $fixture) use ($player, $teamsById, $bootstrap): array {
                $playerTeamId = (int) ($player['team'] ?? 0);
                $homeTeamId = (int) ($fixture['team_h'] ?? 0);
                $awayTeamId = (int) ($fixture['team_a'] ?? 0);
                $isHome = (bool) ($fixture['is_home'] ?? ($fixture['was_home'] ?? $homeTeamId === $playerTeamId));
                $opponentTeamId = (int) ($fixture['opponent_team'] ?? 0);

                if ($opponentTeamId === 0) {
                    $opponentTeamId = $isHome ? $awayTeamId : $homeTeamId;
                }

                $opponentTeam = $teamsById->get($opponentTeamId) ?? $this->resolveTeamNameById($opponentTeamId, $bootstrap);
                $projectedPoints = $this->estimatePlayerFixturePoints($player, $fixture, $isHome);

                return [
                    'event' => (int) ($fixture['event'] ?? 0),
                    'gameweek_label' => isset($fixture['event']) ? 'GW'.(int) $fixture['event'] : null,
                    'kickoff_time' => $fixture['kickoff_time'] ?? null,
                    'kickoff_time_formatted' => $this->formatShortTime($fixture['kickoff_time'] ?? null),
                    'date_label' => $this->fixtureDateLabel($fixture['kickoff_time'] ?? null),
                    'label' => sprintf(
                        'GW %d  %s',
                        (int) ($fixture['event'] ?? 0),
                        sprintf('%s (%s)', $opponentTeam['short_name'] ?? $opponentTeam['name'] ?? '-', $isHome ? 'H' : 'A')
                    ),
                    'opponent_team' => $opponentTeam,
                    'is_home' => $isHome,
                    'difficulty' => (int) ($fixture['difficulty'] ?? 3),
                    'difficulty_label' => $this->difficultyLabel((int) ($fixture['difficulty'] ?? 3)),
                    'color' => $this->difficultyColor((int) ($fixture['difficulty'] ?? 3)),
                    'projected_points' => $projectedPoints,
                    'projected_points_label' => number_format($projectedPoints, 1),
                ];
            })
            ->take(3)
            ->values()
            ->all();
    }

    /**
     * Build the profile panel used on the player page.
     */
    protected function buildPlayerProfile(array $playerCard, array $player, array $summary, array $team, array $history): array
    {
        return [
            'player' => [
                'id' => $playerCard['id'],
                'name' => $playerCard['name'],
                'web_name' => $playerCard['web_name'],
                'photo_url' => $playerCard['photo_url'],
                'jersey_url' => $playerCard['jersey_url'],
            ],
            'team' => $team,
            'position' => [
                'code' => $playerCard['position_code'],
                'name' => $playerCard['position_name'],
            ],
            'nationality' => $player['nationality'] ?? null,
            'preferred_foot' => $player['preferred_foot'] ?? null,
            'date_of_birth' => $player['date_of_birth'] ?? $player['birthday'] ?? null,
            'appearances' => count($history),
            'starts' => $this->sumHistoryField($history, 'starts'),
            'minutes' => (int) ($playerCard['minutes'] ?? 0),
            'goals_scored' => (int) ($playerCard['goals_scored'] ?? 0),
            'assists' => (int) ($playerCard['assists'] ?? 0),
            'clean_sheets' => (int) ($playerCard['clean_sheets'] ?? 0),
            'yellow_cards' => (int) ($playerCard['yellow_cards'] ?? 0),
            'red_cards' => (int) ($playerCard['red_cards'] ?? 0),
            'bonus' => $this->sumHistoryField($history, 'bonus'),
            'history_past' => $summary['history_past'] ?? [],
        ];
    }

    /**
     * Build the career summary metrics block.
     */
    protected function buildPlayerCareerTotals(array $playerCard, array $player, array $summary, array $history): array
    {
        return [
            'appearances' => count($history),
            'starts' => $this->sumHistoryField($history, 'starts'),
            'minutes' => (int) ($playerCard['minutes'] ?? 0),
            'goals_scored' => (int) ($playerCard['goals_scored'] ?? 0),
            'assists' => (int) ($playerCard['assists'] ?? 0),
            'clean_sheets' => (int) ($playerCard['clean_sheets'] ?? 0),
            'yellow_cards' => (int) ($playerCard['yellow_cards'] ?? 0),
            'red_cards' => (int) ($playerCard['red_cards'] ?? 0),
            'bonus' => $this->sumHistoryField($history, 'bonus'),
            'points' => (int) ($playerCard['total_points'] ?? 0),
            'selected_by_percent' => (float) ($playerCard['selected_by_percent'] ?? 0),
            'current_form' => (float) ($playerCard['form'] ?? 0),
            'history_past' => $summary['history_past'] ?? [],
        ];
    }

    /**
     * Build short human-readable player insights for the sidebar.
     */
    protected function buildPlayerInsights(array $playerCard, array $upcomingFixtures, array $history): array
    {
        $fixtureDescription = $this->describeFixtures($upcomingFixtures);
        $form = (float) ($playerCard['form'] ?? 0);
        $minutes = (int) ($playerCard['minutes'] ?? 0);
        $pointsPerGame = (float) ($playerCard['points_per_game'] ?? 0);

        return [
            [
                'type' => 'positive',
                'title' => 'Form and minutes',
                'message' => sprintf(
                    '%s has %d recent appearances and is averaging %.1f points per game with %d total minutes, which supports a stable starting role.',
                    $playerCard['name'],
                    count($history),
                    $pointsPerGame,
                    $minutes
                ),
            ],
            [
                'type' => 'info',
                'title' => 'Fixture run',
                'message' => sprintf(
                    'The next fixtures look like %s, so the model leans slightly %s.',
                    $fixtureDescription,
                    $form >= 7.0 ? 'positive' : 'neutral'
                ),
            ],
            [
                'type' => 'warning',
                'title' => 'Selection note',
                'message' => sprintf(
                    'Ownership sits at %.1f%%, so this is useful for both safety and differential planning.',
                    (float) ($playerCard['selected_by_percent'] ?? 0)
                ),
            ],
        ];
    }

    /**
     * Estimate fantasy points for a single upcoming fixture.
     */
    protected function estimatePlayerFixturePoints(array $player, array $fixture, bool $isHome): float
    {
        $form = (float) ($player['form'] ?? 0);
        $pointsPerGame = (float) ($player['points_per_game'] ?? 0);
        $minutes = (int) ($player['minutes'] ?? 0);
        $chance = (int) ($player['chance_of_playing_next_round'] ?? 100);
        $difficulty = (int) ($fixture['difficulty'] ?? 3);
        $homeBonus = $isHome ? 0.3 : 0.0;
        $fixtureBonus = max(0.0, (5 - $difficulty) * 0.55);

        return round(
            max(
                0.5,
                ($form * 0.45)
                + ($pointsPerGame * 0.65)
                + ($minutes / 2200)
                + ($chance / 100)
                + $homeBonus
                + $fixtureBonus
            ),
            1
        );
    }

    /**
     * Sum a numeric field from the player history rows.
     */
    protected function sumHistoryField(array $history, string $field): int|float
    {
        return collect($history)->sum(function (array $row) use ($field): float {
            return (float) ($row[$field] ?? 0);
        });
    }

    public function getHomePageData(int $teamId): array
    {
        $context = $this->getTeamContext($teamId);

        return [
            'team' => $context['team'],
            'gameweek_details' => $this->getGameweekDetailsFromContext($context),
            'transfer_strategy' => $this->getTransferSuggestionsFromContext($context),
            'captaincy_suggestion' => $this->getCaptaincySuggestionFromContext($context),
            'squad_recommendation' => $this->getSquadRecommendationFromContext($context),
            'bench_players' => $context['bench_players'],
            'player_search' => $this->searchPlayers(null, 12, $teamId),
        ];
    }

    public function getGameweekDetails(int $teamId): array
    {
        return $this->getGameweekDetailsFromContext($this->getTeamContext($teamId));
    }

    public function getTransferSuggestions(int $teamId, int $limit = 3): array
    {
        return $this->getTransferSuggestionsFromContext($this->getTeamContext($teamId), $limit);
    }

    public function getCaptaincySuggestion(int $teamId): array
    {
        return $this->getCaptaincySuggestionFromContext($this->getTeamContext($teamId));
    }

    public function getSquadRecommendation(int $teamId): array
    {
        return $this->getSquadRecommendationFromContext($this->getTeamContext($teamId));
    }

    public function getBenchPlayers(int $teamId): array
    {
        $context = $this->getTeamContext($teamId);

        return [
            'team' => $context['team'],
            'current_gameweek' => $context['current_gameweek'],
            'bench_players' => $context['bench_players'],
        ];
    }

    /**
     * Build the fixtures page payload for the selected gameweek and view.
     */
    public function getFixturesPageData(?int $teamId = null, ?int $gameweek = null, string $view = 'fixtures'): array
    {
        $bootstrap = $this->getBootstrapStatic();
        $currentGameweek = $gameweek ?? $this->getCurrentGameweek();
        $normalizedView = $this->normalizeFixtureView($view);

        return [
            'team' => $teamId !== null ? $this->resolveTeamNameById($teamId, $bootstrap) : null,
            'current_gameweek' => $currentGameweek,
            'view' => $normalizedView,
            'navigation' => $this->buildFixtureNavigation($bootstrap, $currentGameweek),
            'fixtures' => $this->buildFixturesByDate($bootstrap, $currentGameweek),
            'fdr' => $this->buildFdrTable($bootstrap, $currentGameweek, $teamId),
            'fdr_key' => $this->buildFdrKey(),
        ];
    }

    /**
     * Build the fixtures tab payload.
     */
    public function getFixturesPageFixtures(?int $teamId = null, ?int $gameweek = null): array
    {
        return [
            'view' => 'fixtures',
            'payload' => $this->getFixturesPageData($teamId, $gameweek, 'fixtures'),
        ];
    }

    /**
     * Build the FDR tab payload.
     */
    public function getFixturesPageFdr(?int $teamId = null, ?int $gameweek = null): array
    {
        return [
            'view' => 'fdr',
            'payload' => $this->getFixturesPageData($teamId, $gameweek, 'fdr'),
        ];
    }

    /**
     * Build the full transfer page payload.
     */
    public function getTransferPageData(int $teamId): array
    {
        $context = $this->getTeamContext($teamId);

        return [
            'team' => $context['team'],
            'current_gameweek' => $context['current_gameweek'],
            'summary' => $this->buildTransferSummaryFromContext($context),
            'primary_transfer' => $this->buildPrimaryTransferCardFromContext($context),
            'transfer_note' => $this->buildTransferNoteFromContext($context),
            'transfer_options' => $this->buildTransferOptionCardsFromContext($context),
            'insights' => $this->buildTransferInsightsFromContext($context),
        ];
    }

    /**
     * Return the primary transfer card payload.
     */
    public function getTransferPrimaryCard(int $teamId): array
    {
        return $this->buildPrimaryTransferCardFromContext($this->getTeamContext($teamId));
    }

    /**
     * Return the three transfer option cards.
     */
    public function getTransferOptionCards(int $teamId): array
    {
        return $this->buildTransferOptionCardsFromContext($this->getTeamContext($teamId));
    }

    /**
     * Return the transfer insight payload.
     */
    public function getTransferInsights(int $teamId): array
    {
        return $this->buildTransferInsightsFromContext($this->getTeamContext($teamId));
    }

    /**
     * Build the optimiser page payload for a given team and view.
     */
    public function getOptimizerPageData(int $teamId, string $view = 'recommended'): array
    {
        $context = $this->getTeamContext($teamId);
        $normalizedView = $this->normalizeOptimizerView($view);

        return [
            'team' => $context['team'],
            'current_gameweek' => $context['current_gameweek'],
            'view' => $normalizedView,
            'recommended_captain' => $this->getRecommendedCaptainFromContext($context, $normalizedView),
            'recommended_vice_captain' => $this->getRecommendedViceCaptainFromContext($context, $normalizedView),
            'starting_xi' => $this->getOptimizedStartingXIFromContext($context, $normalizedView),
            'bench' => $this->getOptimizedBenchFromContext($context, $normalizedView),
            'insights' => $this->getOptimizerInsightsFromContext($context, $normalizedView),
        ];
    }

    /**
     * Return the optimiser captain card payload.
     */
    public function getRecommendedCaptain(int $teamId, string $view = 'recommended'): array
    {
        return $this->getRecommendedCaptainFromContext($this->getTeamContext($teamId), $this->normalizeOptimizerView($view));
    }

    /**
     * Return the optimiser vice-captain card payload.
     */
    public function getRecommendedViceCaptain(int $teamId, string $view = 'recommended'): array
    {
        return $this->getRecommendedViceCaptainFromContext($this->getTeamContext($teamId), $this->normalizeOptimizerView($view));
    }

    /**
     * Return the optimiser starting XI payload.
     */
    public function getOptimizedStartingXI(int $teamId, string $view = 'recommended'): array
    {
        return $this->getOptimizedStartingXIFromContext($this->getTeamContext($teamId), $this->normalizeOptimizerView($view));
    }

    /**
     * Return the optimiser bench payload.
     */
    public function getOptimizedBench(int $teamId, string $view = 'recommended'): array
    {
        return $this->getOptimizedBenchFromContext($this->getTeamContext($teamId), $this->normalizeOptimizerView($view));
    }

    /**
     * Return the optimiser insights payload.
     */
    public function getOptimizerInsights(int $teamId, string $view = 'recommended'): array
    {
        return $this->getOptimizerInsightsFromContext($this->getTeamContext($teamId), $this->normalizeOptimizerView($view));
    }

    public function searchPlayers(?string $search = null, int $limit = 25, ?int $teamId = null, ?string $position = null): array
    {
        $players = $this->filterPlayersCatalog(
            $this->buildPlayerCatalog($this->getBootstrapStatic(), $teamId),
            $search,
            $position,
            null,
            null
        );

        return [
            'query' => $search,
            'limit' => $limit,
            'count' => $players->count(),
            'players' => $players
                ->sortByDesc(fn (array $player): float => $player['search_score'])
                ->take($limit)
                ->values()
                ->all(),
        ];
    }

    /**
     * Build the paginated players page payload.
     */
    public function getPlayersPageData(
        ?string $search = null,
        int $perPage = 10,
        int $page = 1,
        ?int $teamId = null,
        ?string $position = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
    ): array {
        $bootstrap = $this->getBootstrapStatic();
        $players = $this->filterPlayersCatalog(
            $this->buildPlayerCatalog($bootstrap, $teamId),
            $search,
            $position,
            $minPrice,
            $maxPrice
        );

        $total = $players->count();
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min($page, $lastPage);
        $slice = $players->forPage($currentPage, $perPage)->values()->all();
        $from = $total === 0 ? null : (($currentPage - 1) * $perPage) + 1;
        $to = $total === 0 ? null : min($currentPage * $perPage, $total);

        return [
            'query' => $search,
            'count' => $total,
            'limit' => $perPage,
            'page' => $currentPage,
            'filters' => [
                'search' => $search,
                'position' => $position,
                'team_id' => $teamId,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
            ],
            'meta' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'from' => $from,
                'to' => $to,
                'total' => $total,
                'last_page' => $lastPage,
                'has_more_pages' => $currentPage < $lastPage,
            ],
            'players' => $slice,
        ];
    }

    public function getAllPlayerData(?string $search = null, int $limit = 25, ?int $teamId = null, ?string $position = null): array
    {
        return $this->searchPlayers($search, $limit, $teamId, $position);
    }

    public function getTeamImageUrl(int $teamId, string $variant = 'badge'): string
    {
        $safeVariant = in_array($variant, ['badge', 'jersey'], true) ? $variant : 'badge';

        return url("/api/fpl/assets/{$safeVariant}/{$teamId}.svg");
    }

    public function renderTeamImageSvg(int $teamId, string $variant = 'badge'): string
    {
        $team = $this->resolveTeamImageMeta($teamId);
        $safeVariant = in_array($variant, ['badge', 'jersey'], true) ? $variant : 'badge';
        $palette = $this->teamPalette($teamId);
        $label = $this->escapeSvgText($team['short_name'] ?? 'T'.$teamId);
        $name = $this->escapeSvgText($team['name'] ?? 'Team '.$teamId);
        $accent = $palette['accent'];
        $primary = $palette['primary'];
        $secondary = $palette['secondary'];

        if ($safeVariant === 'jersey') {
            return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320" viewBox="0 0 320 320" role="img" aria-labelledby="title desc">
  <title>{$name} jersey</title>
  <desc>Accessible jersey placeholder for {$name}</desc>
  <defs>
    <linearGradient id="shirt{$teamId}" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$primary}" />
      <stop offset="100%" stop-color="{$secondary}" />
    </linearGradient>
  </defs>
  <rect width="320" height="320" rx="48" fill="{$accent}" />
  <path d="M96 88l36-24h56l36 24 24 24-24 32-18-10v118H114V134l-18 10-24-32 24-24z" fill="url(#shirt{$teamId})" stroke="#ffffff" stroke-width="10" stroke-linejoin="round"/>
  <rect x="128" y="154" width="64" height="60" rx="16" fill="rgba(255,255,255,0.16)" />
  <text x="160" y="192" text-anchor="middle" font-family="Arial, sans-serif" font-size="30" font-weight="700" fill="#ffffff">{$label}</text>
</svg>
SVG;
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320" viewBox="0 0 320 320" role="img" aria-labelledby="title desc">
  <title>{$name} badge</title>
  <desc>Accessible badge placeholder for {$name}</desc>
  <defs>
    <linearGradient id="badge{$teamId}" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$primary}" />
      <stop offset="100%" stop-color="{$secondary}" />
    </linearGradient>
  </defs>
  <circle cx="160" cy="160" r="132" fill="url(#badge{$teamId})" stroke="#ffffff" stroke-width="12"/>
  <circle cx="160" cy="160" r="96" fill="rgba(255,255,255,0.14)" />
  <text x="160" y="180" text-anchor="middle" font-family="Arial, sans-serif" font-size="76" font-weight="700" fill="#ffffff">{$label}</text>
</svg>
SVG;
    }

    protected function getTeamContext(int $teamId): array
    {
        return Cache::remember("fpl_team_context_{$teamId}", 120, function () use ($teamId): array {
            $bootstrap = $this->getBootstrapStatic();
            $currentGameweek = $this->getCurrentGameweek();
            $currentEvent = $this->getCurrentEvent();
            $team = $this->getTeam($teamId);
            $picks = $this->getPicks($teamId, $currentGameweek);
            $fixtures = $this->getFixtures();

            $elementsById = collect($bootstrap['elements'] ?? [])->keyBy('id');
            $teamsById = collect($bootstrap['teams'] ?? [])->keyBy('id');

            $ownedPlayers = $this->buildOwnedPlayers(
                $picks,
                $elementsById,
                $teamsById,
                $fixtures,
                $currentGameweek,
            );

            $benchPlayers = collect($ownedPlayers)
                ->where('is_bench', true)
                ->sortBy('squad_position')
                ->values()
                ->all();

            return [
                'bootstrap' => $bootstrap,
                'team' => $team,
                'current_gameweek' => $currentGameweek,
                'current_event' => $currentEvent,
                'picks' => $picks,
                'fixtures' => $fixtures,
                'elements_by_id' => $elementsById,
                'teams_by_id' => $teamsById,
                'current_squad' => collect($ownedPlayers)
                    ->where('is_bench', false)
                    ->sortBy('squad_position')
                    ->values()
                    ->all(),
                'owned_players' => collect($ownedPlayers)
                    ->sortBy('squad_position')
                    ->values()
                    ->all(),
                'bench_players' => $benchPlayers,
                'owned_player_ids' => collect($ownedPlayers)->pluck('id')->values()->all(),
                'entry_history' => $picks['entry_history'] ?? [],
                'bank' => (int) Arr::get($picks, 'entry_history.bank', 0),
                'team_value' => (int) Arr::get($picks, 'entry_history.value', 0),
                'available_transfers' => (int) Arr::get($picks, 'entry_history.event_transfers', 0),
            ];
        });
    }

    /**
     * Normalize the fixtures tab selection.
     */
    protected function normalizeFixtureView(string $view): string
    {
        $normalizedView = strtolower(trim($view));

        return in_array($normalizedView, ['fixtures', 'fdr'], true) ? $normalizedView : 'fixtures';
    }

    /**
     * Build the header navigation block for the fixtures page.
     */
    protected function buildFixtureNavigation(array $bootstrap, ?int $selectedGameweek): array
    {
        $events = collect($bootstrap['events'] ?? [])->sortBy('id')->values();
        $currentEvent = collect($events)->firstWhere('is_current', true)
            ?? collect($events)->firstWhere('is_next', true)
            ?? $events->first();

        $selectedEvent = $selectedGameweek !== null
            ? $events->firstWhere('id', $selectedGameweek)
            : $currentEvent;

        return [
            'selected_gameweek' => (int) ($selectedEvent['id'] ?? $selectedGameweek ?? 0),
            'title' => $selectedEvent['name'] ?? ($currentEvent['name'] ?? 'Gameweek'),
            'deadline' => $this->formatDeadline($selectedEvent['deadline_time'] ?? null),
            'range' => $this->buildGameweekRangeLabel($selectedEvent, $currentEvent),
            'previous' => $this->buildAdjacentGameweekLink($events, (int) ($selectedEvent['id'] ?? 0), -1),
            'next' => $this->buildAdjacentGameweekLink($events, (int) ($selectedEvent['id'] ?? 0), 1),
            'notice' => 'All times are shown in your local time',
        ];
    }

    /**
     * Build the fixtures grouped by kickoff date.
     */
    protected function buildFixturesByDate(array $bootstrap, ?int $gameweek = null): array
    {
        $teamsById = collect($bootstrap['teams'] ?? [])->keyBy('id');
        $fixtures = collect($this->getFixtures($gameweek))
            ->sortBy(function (array $fixture): array {
                return [
                    $fixture['kickoff_time'] ?? '',
                    (int) ($fixture['team_h'] ?? 0),
                    (int) ($fixture['team_a'] ?? 0),
                ];
            })
            ->values();

        return $fixtures
            ->groupBy(function (array $fixture): string {
                return $this->fixtureDateLabel($fixture['kickoff_time'] ?? null);
            })
            ->map(function (Collection $dayFixtures) use ($teamsById): array {
                return [
                    'date' => $dayFixtures->first()['kickoff_time'] ? $this->fixtureDateLabel($dayFixtures->first()['kickoff_time']) : 'Unknown',
                    'fixtures' => $dayFixtures
                        ->map(fn (array $fixture): array => $this->formatFixtureRow($fixture, $teamsById))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build the FDR matrix for the selected gameweek range.
     */
    protected function buildFdrTable(array $bootstrap, ?int $selectedGameweek = null, ?int $teamId = null): array
    {
        $events = collect($bootstrap['events'] ?? [])->sortBy('id')->values();
        $teams = collect($bootstrap['teams'] ?? [])->values();
        $baseEvent = $selectedGameweek ?? $this->getCurrentGameweek();

        $columns = $events
            ->filter(fn (array $event): bool => $baseEvent === null || (int) $event['id'] >= $baseEvent)
            ->take(9)
            ->values()
            ->map(fn (array $event): array => [
                'id' => (int) $event['id'],
                'label' => $event['name'] ?? ('GW'.(int) $event['id']),
                'deadline' => $this->formatShortDate($event['deadline_time'] ?? null),
                'is_selected' => (int) ($event['id'] ?? 0) === (int) $baseEvent,
            ])
            ->all();

        $fixtureMap = $this->buildFixtureMap($bootstrap['fixtures'] ?? [], collect($bootstrap['teams'] ?? [])->keyBy('id'), $baseEvent);

        return [
            'selected_gameweek' => $baseEvent,
            'columns' => $columns,
            'rows' => $teams
                ->map(function (array $team) use ($fixtureMap, $teamId): array {
                    $cells = [];

                    foreach ($fixtureMap[$team['id']] ?? [] as $fixture) {
                        $cells[] = [
                            'event' => $fixture['event'],
                            'opponent' => $fixture['opponent_team'],
                            'label' => sprintf('%s (%s)', $fixture['opponent_team']['short_name'] ?? $fixture['opponent_team']['name'] ?? '-', $fixture['is_home'] ? 'H' : 'A'),
                            'difficulty' => $fixture['difficulty'],
                            'difficulty_label' => $this->difficultyLabel($fixture['difficulty']),
                            'color' => $this->difficultyColor($fixture['difficulty']),
                            'is_home' => $fixture['is_home'],
                        ];
                    }

                    return [
                        'team' => [
                            'id' => (int) $team['id'],
                            'name' => $team['name'] ?? 'Unknown',
                            'short_name' => $team['short_name'] ?? null,
                            'badge_url' => $this->buildTeamBadgeUrl((int) $team['id']),
                            'is_focus' => $teamId !== null && (int) $teamId === (int) $team['id'],
                        ],
                        'fixtures' => $cells,
                    ];
                })
                ->all(),
        ];
    }

    /**
     * Format a single fixture row for the fixtures view.
     */
    protected function formatFixtureRow(array $fixture, Collection $teamsById): array
    {
        $homeTeam = $teamsById->get((int) ($fixture['team_h'] ?? 0));
        $awayTeam = $teamsById->get((int) ($fixture['team_a'] ?? 0));

        return [
            'event' => (int) ($fixture['event'] ?? 0),
            'kickoff_time' => $fixture['kickoff_time'] ?? null,
            'kickoff_time_formatted' => $this->formatShortTime($fixture['kickoff_time'] ?? null),
            'date_label' => $this->fixtureDateLabel($fixture['kickoff_time'] ?? null),
            'home_team' => [
                'id' => (int) ($homeTeam['id'] ?? 0),
                'name' => $homeTeam['name'] ?? 'Unknown',
                'short_name' => $homeTeam['short_name'] ?? null,
                'badge_url' => $this->buildTeamBadgeUrl((int) ($homeTeam['id'] ?? 0)),
            ],
            'away_team' => [
                'id' => (int) ($awayTeam['id'] ?? 0),
                'name' => $awayTeam['name'] ?? 'Unknown',
                'short_name' => $awayTeam['short_name'] ?? null,
                'badge_url' => $this->buildTeamBadgeUrl((int) ($awayTeam['id'] ?? 0)),
            ],
            'difficulty' => (int) ($fixture['difficulty'] ?? 3),
            'difficulty_label' => $this->difficultyLabel((int) ($fixture['difficulty'] ?? 3)),
            'color' => $this->difficultyColor((int) ($fixture['difficulty'] ?? 3)),
        ];
    }

    /**
     * Produce a friendly date label like Sat 14 Mar.
     */
    protected function fixtureDateLabel(?string $kickoffTime): string
    {
        if ($kickoffTime === null || $kickoffTime === '') {
            return 'Unknown';
        }

        return date('D d M', strtotime($kickoffTime));
    }

    /**
     * Produce a friendly time label like 21:00.
     */
    protected function formatShortTime(?string $kickoffTime): ?string
    {
        if ($kickoffTime === null || $kickoffTime === '') {
            return null;
        }

        return date('H:i', strtotime($kickoffTime));
    }

    /**
     * Produce a friendly short date label for the FDR table.
     */
    protected function formatShortDate(?string $deadlineTime): ?string
    {
        if ($deadlineTime === null || $deadlineTime === '') {
            return null;
        }

        return date('d M', strtotime($deadlineTime));
    }

    /**
     * Build a readable range label for the fixtures header.
     */
    protected function buildGameweekRangeLabel(?array $selectedEvent, ?array $currentEvent): string
    {
        $selectedId = (int) ($selectedEvent['id'] ?? 0);
        $currentId = (int) ($currentEvent['id'] ?? 0);

        if ($selectedId > 0 && $currentId > 0 && $selectedId !== $currentId) {
            $selectedLabel = $selectedEvent['deadline_time']
                ? $this->formatShortDate($selectedEvent['deadline_time'])
                : ($selectedEvent['name'] ?? 'Gameweek');
            $currentLabel = $currentEvent['deadline_time']
                ? $this->formatShortDate($currentEvent['deadline_time'])
                : ($currentEvent['name'] ?? 'Gameweek');

            return sprintf(
                '%s - %s',
                $selectedLabel,
                $currentLabel
            );
        }

        return $selectedEvent['deadline_time']
            ? $this->formatShortDate($selectedEvent['deadline_time'])
            : ($selectedEvent['name'] ?? 'Gameweek');
    }

    /**
     * Build the adjacent gameweek navigation target.
     */
    protected function buildAdjacentGameweekLink(Collection $events, int $selectedGameweek, int $direction): ?array
    {
        $index = $events->search(fn (array $event): bool => (int) $event['id'] === $selectedGameweek);

        if ($index === false) {
            return null;
        }

        $target = $events->get($index + $direction);

        if ($target === null) {
            return null;
        }

        return [
            'id' => (int) $target['id'],
            'name' => $target['name'] ?? 'Gameweek',
            'deadline' => $this->formatShortDate($target['deadline_time'] ?? null),
        ];
    }

    /**
     * Return the 1-5 FDR label used by the table chips.
     */
    protected function difficultyLabel(int $difficulty): string
    {
        return match (true) {
            $difficulty <= 1 => '1',
            $difficulty === 2 => '2',
            $difficulty === 3 => '3',
            $difficulty === 4 => '4',
            default => '5',
        };
    }

    /**
     * Return the color for a fixture difficulty chip.
     */
    protected function difficultyColor(int $difficulty): string
    {
        return match (true) {
            $difficulty <= 1 => '#34f5a4',
            $difficulty === 2 => '#4af5c2',
            $difficulty === 3 => '#0ed7f5',
            $difficulty === 4 => '#ff4f8b',
            default => '#ff1f66',
        };
    }

    /**
     * Build the FDR legend payload.
     */
    protected function buildFdrKey(): array
    {
        return [
            ['value' => 1, 'label' => 'Very Easy', 'color' => $this->difficultyColor(1)],
            ['value' => 2, 'label' => 'Easy', 'color' => $this->difficultyColor(2)],
            ['value' => 3, 'label' => 'Balanced', 'color' => $this->difficultyColor(3)],
            ['value' => 4, 'label' => 'Hard', 'color' => $this->difficultyColor(4)],
            ['value' => 5, 'label' => 'Very Hard', 'color' => $this->difficultyColor(5)],
        ];
    }

    protected function getRecommendedCaptainFromContext(array $context, string $view = 'recommended'): array
    {
        $captainCandidates = $this->getCaptainCandidatesFromContext($context, $view);
        $bestCaptain = $captainCandidates->first();

        return [
            'team' => $context['team'],
            'current_gameweek' => $context['current_gameweek'],
            'view' => $view,
            'player' => $bestCaptain ? $this->playerSnapshot($bestCaptain) : null,
            'projected_points' => $bestCaptain ? round((float) $bestCaptain['search_score'], 2) : 0.0,
            'reason' => $bestCaptain ? $this->buildCaptainReason($bestCaptain) : 'No captain candidate found.',
            'alternatives' => $captainCandidates
                ->skip(1)
                ->take(3)
                ->map(fn (array $player): array => $this->playerSnapshot($player))
                ->all(),
        ];
    }

    protected function getRecommendedViceCaptainFromContext(array $context, string $view = 'recommended'): array
    {
        $captainCandidates = $this->getCaptainCandidatesFromContext($context, $view);
        $viceCaptain = $captainCandidates->skip(1)->first() ?? $captainCandidates->first();

        return [
            'team' => $context['team'],
            'current_gameweek' => $context['current_gameweek'],
            'view' => $view,
            'player' => $viceCaptain ? $this->playerSnapshot($viceCaptain) : null,
            'projected_points' => $viceCaptain ? round((float) $viceCaptain['search_score'], 2) : 0.0,
            'reason' => $viceCaptain ? $this->buildCaptainReason($viceCaptain) : 'No vice-captain candidate found.',
        ];
    }

    protected function getOptimizedStartingXIFromContext(array $context, string $view = 'recommended'): array
    {
        $lineup = $this->getOptimizerLineupFromContext($context, $view);

        return [
            'team' => $context['team'],
            'current_gameweek' => $context['current_gameweek'],
            'view' => $lineup['view'],
            'formation' => $lineup['formation'],
            'projected_score' => $lineup['projected_score'],
            'starting_xi' => collect($lineup['starting_xi'])
                ->map(fn (array $player): array => $this->playerSnapshot($player))
                ->all(),
        ];
    }

    protected function getOptimizedBenchFromContext(array $context, string $view = 'recommended'): array
    {
        $lineup = $this->getOptimizerLineupFromContext($context, $view);

        return [
            'team' => $context['team'],
            'current_gameweek' => $context['current_gameweek'],
            'view' => $lineup['view'],
            'players' => collect($lineup['bench'])
                ->take(4)
                ->values()
                ->map(fn (array $player): array => $this->playerSnapshot($player))
                ->all(),
        ];
    }

    protected function getOptimizerInsightsFromContext(array $context, string $view = 'recommended'): array
    {
        $normalizedView = $this->normalizeOptimizerView($view);
        $currentLineup = $this->getOptimizerLineupFromContext($context, 'current');
        $recommendedLineup = $this->getOptimizerLineupFromContext($context, 'recommended');
        $recommendedCaptain = $this->getRecommendedCaptainFromContext($context, $normalizedView);
        $recommendedViceCaptain = $this->getRecommendedViceCaptainFromContext($context, $normalizedView);
        $transferSuggestions = $this->getTransferSuggestionsFromContext($context, 1);
        $bestTransfer = $transferSuggestions['recommendations'][0] ?? null;
        $projectedGain = round($recommendedLineup['projected_score'] - $currentLineup['projected_score'], 2);
        $headline = $projectedGain >= 0
            ? sprintf('Recommended XI projects +%s more points than your current setup.', number_format($projectedGain, 1))
            : sprintf('Your current XI is already +%s ahead of the optimiser.', number_format(abs($projectedGain), 1));
        $currentBenchWeakest = collect($currentLineup['bench'] ?? [])->sortBy('search_score')->first();
        $recommendedStar = collect($recommendedLineup['starting_xi'] ?? [])->sortByDesc('search_score')->first();
        $captainName = $recommendedCaptain['player']['name'] ?? 'your top attacker';
        $viceCaptainName = $recommendedViceCaptain['player']['name'] ?? 'the next best option';
        $nextMove = $bestTransfer
            ? sprintf('If you make one move, %s is the cleanest upgrade path.', $bestTransfer['player_in']['name'])
            : 'No obvious transfer upgrade is standing out right now.';

        return [
            'team' => $context['team'],
            'current_gameweek' => $context['current_gameweek'],
            'view' => $normalizedView,
            'headline' => $headline,
            'summary' => sprintf(
                '%s is the captaincy leader, %s is the safest vice-captain, and %s is the biggest upside player to start.',
                $captainName,
                $viceCaptainName,
                $recommendedStar['name'] ?? 'your best scorer'
            ),
            'strengths' => [
                sprintf('Recommended XI improves your projected score to %s.', number_format((float) $recommendedLineup['projected_score'], 1)),
                sprintf('%s offers the strongest captaincy ceiling.', $captainName),
                sprintf('%s adds the most upside if you compare current and recommended views.', $recommendedStar['name'] ?? 'The optimiser'),
            ],
            'risks' => [
                $currentBenchWeakest
                    ? sprintf('Your weakest current bench option is %s, so emergency cover is limited.', $currentBenchWeakest['name'])
                    : 'Bench depth is limited, so late team news matters.',
                sprintf('Any rotation on %s or %s would reduce the ceiling quickly.', $captainName, $viceCaptainName),
            ],
            'recommended_action' => sprintf(
                'Use the %s view, captain %s, vice-captain %s, and consider %s.',
                $normalizedView,
                $captainName,
                $viceCaptainName,
                $bestTransfer['player_in']['name'] ?? 'a single upgrade transfer'
            ),
            'next_move' => $nextMove,
            'projected_gain' => $projectedGain,
        ];
    }

    protected function normalizeOptimizerView(string $view): string
    {
        $normalizedView = strtolower(trim($view));

        return in_array($normalizedView, ['current', 'recommended'], true) ? $normalizedView : 'recommended';
    }

    protected function getOptimizerLineupFromContext(array $context, string $view = 'recommended'): array
    {
        $normalizedView = $this->normalizeOptimizerView($view);

        if ($normalizedView === 'current') {
            $startingXi = collect($context['current_squad'] ?? [])
                ->where('is_bench', false)
                ->sortBy('squad_position')
                ->values()
                ->all();
            $bench = collect($context['bench_players'] ?? [])
                ->sortBy('squad_position')
                ->values()
                ->all();

            return [
                'view' => $normalizedView,
                'formation' => $this->inferFormationFromCurrentXI($startingXi),
                'projected_score' => round(collect($startingXi)->sum(fn (array $player): float => (float) ($player['search_score'] ?? 0)), 2),
                'starting_xi' => $startingXi,
                'bench' => $bench,
            ];
        }

        $bestFormation = $this->buildBestFormationFromSquad($context['owned_players'] ?? ($context['current_squad'] ?? []));
        $bestFormation['view'] = $normalizedView;
        $bestFormation['bench'] = array_values($bestFormation['bench'] ?? []);

        return $bestFormation;
    }

    protected function getCaptainCandidatesFromContext(array $context, string $view = 'recommended'): Collection
    {
        $lineup = $this->getOptimizerLineupFromContext($context, $view);

        return collect($lineup['starting_xi'] ?? [])
            ->filter(fn (array $player): bool => in_array($player['position_code'] ?? null, ['MID', 'FWD'], true))
            ->sortByDesc('search_score')
            ->values()
            ->take(5);
    }

    protected function inferFormationFromCurrentXI(array $startingXi): string
    {
        $defenders = collect($startingXi)->where('position_code', 'DEF')->count();
        $midfielders = collect($startingXi)->where('position_code', 'MID')->count();
        $forwards = collect($startingXi)->where('position_code', 'FWD')->count();

        return sprintf('%d-%d-%d', $defenders, $midfielders, $forwards);
    }

    protected function getGameweekDetailsFromContext(array $context): array
    {
        $bootstrap = $context['bootstrap'];
        $events = collect($bootstrap['events'] ?? [])->sortBy('id')->values();
        $currentGameweek = $context['current_gameweek'];

        $upcomingEvents = $events
            ->filter(function (array $event) use ($currentGameweek): bool {
                return $currentGameweek === null || (int) $event['id'] >= $currentGameweek;
            })
            ->take(3)
            ->values()
            ->map(function (array $event): array {
                return [
                    'id' => (int) $event['id'],
                    'name' => $event['name'] ?? 'Gameweek',
                    'deadline_time' => $event['deadline_time'] ?? null,
                    'deadline_time_formatted' => $this->formatDeadline($event['deadline_time'] ?? null),
                    'is_current' => (bool) ($event['is_current'] ?? false),
                    'is_next' => (bool) ($event['is_next'] ?? false),
                ];
            })
            ->all();

        return [
            'team' => $context['team'],
            'current_gameweek' => $currentGameweek,
            'current_event' => $context['current_event'],
            'current_deadline' => $this->formatDeadline($context['current_event']['deadline_time'] ?? null),
            'bank' => $context['bank'],
            'team_value' => $context['team_value'],
            'available_transfers' => $context['available_transfers'],
            'upcoming_gameweeks' => $upcomingEvents,
        ];
    }

    protected function getTransferSuggestionsFromContext(array $context, int $limit = 3): array
    {
        $bootstrap = $context['bootstrap'];
        $players = $this->buildPlayerCatalog($bootstrap);
        $ownedIds = collect($context['owned_player_ids'] ?? []);
        $availableBudget = (int) ($context['bank'] ?? 0);

        $weakestStarters = collect($context['current_squad'])
            ->sortBy('search_score')
            ->take(8)
            ->values();

        $recommendations = $weakestStarters
            ->map(function (array $outPlayer) use ($players, $ownedIds, $availableBudget): ?array {
                $bestReplacement = $players
                    ->filter(function (array $player) use ($outPlayer, $ownedIds, $availableBudget): bool {
                        if ($ownedIds->contains($player['id'])) {
                            return false;
                        }

                        if ($player['position_code'] !== $outPlayer['position_code']) {
                            return false;
                        }

                        return $player['now_cost'] <= ($outPlayer['now_cost'] + $availableBudget);
                    })
                    ->sortByDesc('search_score')
                    ->first();

                if (! $bestReplacement) {
                    return null;
                }

                $pointsGain = round($bestReplacement['search_score'] - $outPlayer['search_score'], 2);

                return [
                    'player_out' => $this->playerSnapshot($outPlayer),
                    'player_in' => $this->playerSnapshot($bestReplacement),
                    'points_gain' => $pointsGain,
                    'reason' => $this->buildTransferReason($outPlayer, $bestReplacement, $pointsGain),
                ];
            })
            ->filter()
            ->sortByDesc('points_gain')
            ->take($limit)
            ->values()
            ->all();

        if ($recommendations === []) {
            $weakestPlayer = collect($context['current_squad'] ?? [])
                ->sortBy('search_score')
                ->first();

            $fallbackReplacement = $players
                ->filter(function (array $player) use ($weakestPlayer, $ownedIds, $availableBudget): bool {
                    if ($weakestPlayer === null) {
                        return false;
                    }

                    if ($ownedIds->contains($player['id'])) {
                        return false;
                    }

                    return $player['now_cost'] <= (($weakestPlayer['now_cost'] ?? 0) + $availableBudget);
                })
                ->sortByDesc('search_score')
                ->first();

            if ($weakestPlayer !== null && $fallbackReplacement !== null) {
                $pointsGain = round($fallbackReplacement['search_score'] - $weakestPlayer['search_score'], 2);

                $recommendations = [[
                    'player_out' => $this->playerSnapshot($weakestPlayer),
                    'player_in' => $this->playerSnapshot($fallbackReplacement),
                    'points_gain' => $pointsGain,
                    'reason' => $this->buildTransferReason($weakestPlayer, $fallbackReplacement, $pointsGain),
                ]];
            }
        }

        return [
            'team' => $context['team'],
            'current_gameweek' => $context['current_gameweek'],
            'available_budget' => $availableBudget / 10,
            'recommendations' => $recommendations,
            'focus' => $recommendations[0]['reason'] ?? 'No clear transfer upgrade found in the current budget.',
        ];
    }

    /**
     * Build the top transfer summary shown above the cards.
     */
    protected function buildTransferSummaryFromContext(array $context): array
    {
        $suggestions = $this->getTransferSuggestionsFromContext($context, 3);
        $primary = $suggestions['recommendations'][0] ?? null;

        return [
            'headline' => $primary
                ? sprintf('AI Transfer Strategy: %s to %s', $primary['player_out']['name'], $primary['player_in']['name'])
                : 'AI Transfer Strategy',
            'subheadline' => $primary['reason'] ?? 'No clear transfer upgrade found in the current budget.',
            'available_transfers' => $context['available_transfers'],
            'bank' => round((int) ($context['bank'] ?? 0) / 10, 1),
            'team_value' => round((int) ($context['team_value'] ?? 0) / 10, 1),
            'projected_gain' => $primary['points_gain'] ?? 0.0,
        ];
    }

    /**
     * Build the primary transfer card from the strongest recommendation.
     */
    protected function buildPrimaryTransferCardFromContext(array $context): array
    {
        $suggestions = $this->getTransferSuggestionsFromContext($context, 3);
        $primary = $suggestions['recommendations'][0] ?? null;

        if ($primary === null) {
            return [
                'label' => 'Apply Transfer',
                'player_out' => null,
                'player_in' => null,
                'points_gain' => 0.0,
                'reason' => 'No clear transfer upgrade found in the current budget.',
                'status' => 'no-suggestion',
            ];
        }

        return [
            'label' => 'Apply Transfer',
            'player_out' => $primary['player_out'],
            'player_in' => $primary['player_in'],
            'points_gain' => $primary['points_gain'],
            'reason' => $primary['reason'],
            'status' => 'recommended',
            'badge' => sprintf('+%s', number_format((float) $primary['points_gain'], 1)),
            'meta' => [
                'out_text' => sprintf('%s out', $primary['player_out']['name']),
                'in_text' => sprintf('%s in', $primary['player_in']['name']),
            ],
        ];
    }

    /**
     * Build the explanatory text shown beneath the transfer cards.
     */
    protected function buildTransferNoteFromContext(array $context): string
    {
        $primary = $this->buildPrimaryTransferCardFromContext($context);

        if ($primary['player_out'] === null || $primary['player_in'] === null) {
            return 'No clear transfer upgrade found in the current budget.';
        }

        return sprintf(
            'Selling %s for %s saves %s, improves fixture quality immediately, and projects a stronger 3-gameweek return through better expected minutes and recent form.',
            $primary['player_out']['name'],
            $primary['player_in']['name'],
            $this->formatCostDifference((float) ($primary['player_out']['cost'] ?? 0), (float) ($primary['player_in']['cost'] ?? 0))
        );
    }

    /**
     * Build the three transfer option cards shown at the bottom of the page.
     */
    protected function buildTransferOptionCardsFromContext(array $context): array
    {
        $suggestions = $this->getTransferSuggestionsFromContext($context, 6);
        $recommendations = collect($suggestions['recommendations'] ?? []);
        $primary = $recommendations->first();
        $safe = $recommendations
            ->sortByDesc(fn (array $item): float => ($item['points_gain'] ?? 0) * 0.65)
            ->first();
        $aggressive = $recommendations
            ->sortByDesc(function (array $item): float {
                $playerIn = $item['player_in'] ?? [];

                return ((float) ($item['points_gain'] ?? 0) * 0.8) + ((float) ($playerIn['selected_by_percent'] ?? 0) * -0.03);
            })
            ->first();

        return [
            $this->buildTransferComboCard(
                $primary,
                'Best 2 Transfer Combination',
                'Apply Combo',
                'Aggressive premium reshuffle that upgrades both ceiling and captaincy potential while maintaining reliable minutes.',
            ),
            $this->buildTransferComboCard(
                $safe,
                'Safe Transfer Option',
                'Apply Safe',
                'Low-risk move that targets consistent starts and stronger clean sheet odds without disrupting the squad structure.',
            ),
            $this->buildTransferComboCard(
                $aggressive,
                'Aggressive/Differential Option',
                'Apply Aggressive',
                'Upside-first move that tries to gain rank quickly through a sharper fixture swing and lower ownership leverage.',
            ),
        ];
    }

    /**
     * Build the text blocks used by the transfer insight area.
     */
    protected function buildTransferInsightsFromContext(array $context): array
    {
        $recommendations = $this->getTransferSuggestionsFromContext($context, 3)['recommendations'] ?? [];
        $primary = $recommendations[0] ?? null;
        $secondary = $recommendations[1] ?? null;

        return [
            'headline' => $primary
                ? sprintf('%s is the cleanest move for the next few gameweeks.', $primary['player_in']['name'])
                : 'No clear transfer upgrade found in the current budget.',
            'bullets' => array_values(array_filter([
                $primary ? sprintf('Primary move: %s -> %s.', $primary['player_out']['name'], $primary['player_in']['name']) : null,
                $primary ? sprintf('Projected gain: +%s points.', number_format((float) $primary['points_gain'], 1)) : null,
                $secondary ? sprintf('Backup option: %s -> %s.', $secondary['player_out']['name'], $secondary['player_in']['name']) : null,
            ])),
        ];
    }

    /**
     * Format a money difference in FPL units.
     */
    protected function formatCostDifference(float $outCost, float $inCost): string
    {
        $difference = max(0, $outCost - $inCost);

        return '£'.number_format($difference, 1).'m';
    }

    /**
     * Build a single transfer option card from one recommendation.
     */
    protected function buildTransferComboCard(?array $recommendation, string $title, string $actionLabel, string $description): array
    {
        if ($recommendation === null) {
            return [
                'title' => $title,
                'action_label' => $actionLabel,
                'description' => $description,
                'player_out' => null,
                'player_in' => null,
                'points_gain' => 0.0,
                'reason' => 'No valid transfer path was found.',
            ];
        }

        return [
            'title' => $title,
            'action_label' => $actionLabel,
            'description' => $description,
            'player_out' => $recommendation['player_out'],
            'player_in' => $recommendation['player_in'],
            'points_gain' => $recommendation['points_gain'],
            'reason' => $recommendation['reason'],
            'badge' => sprintf('+%s', number_format((float) $recommendation['points_gain'], 1)),
        ];
    }

    protected function getCaptaincySuggestionFromContext(array $context): array
    {
        $captainCandidates = collect($context['current_squad'])
            ->filter(fn (array $player): bool => in_array($player['position_code'], ['MID', 'FWD'], true))
            ->sortByDesc('search_score')
            ->take(5)
            ->values();

        $bestCaptain = $captainCandidates->first();
        $viceCaptain = $captainCandidates->skip(1)->first();

        return [
            'team' => $context['team'],
            'current_gameweek' => $context['current_gameweek'],
            'best_captain' => $bestCaptain ? $this->playerSnapshot($bestCaptain) : null,
            'vice_captain' => $viceCaptain ? $this->playerSnapshot($viceCaptain) : null,
            'candidates' => $captainCandidates->map(fn (array $player): array => $this->playerSnapshot($player))->all(),
            'reason' => $bestCaptain ? $this->buildCaptainReason($bestCaptain) : 'No captain candidate found.',
        ];
    }

    protected function getSquadRecommendationFromContext(array $context): array
    {
        $bestXI = $this->buildBestFormationFromSquad($context['owned_players'] ?? $context['current_squad']);

        return [
            'team' => $context['team'],
            'current_gameweek' => $context['current_gameweek'],
            'current_team' => [
                'starting_xi' => collect($context['current_squad'])
                    ->where('is_bench', false)
                    ->sortBy('squad_position')
                    ->values()
                    ->map(fn (array $player): array => $this->playerSnapshot($player))
                    ->all(),
                'bench' => collect($context['bench_players'])
                    ->sortBy('squad_position')
                    ->values()
                    ->map(fn (array $player): array => $this->playerSnapshot($player))
                    ->all(),
            ],
            'suggested_team' => [
                'formation' => $bestXI['formation'],
                'projected_score' => $bestXI['projected_score'],
                'starting_xi' => collect($bestXI['starting_xi'])
                    ->map(fn (array $player): array => $this->playerSnapshot($player))
                    ->all(),
                'bench' => collect($bestXI['bench'])
                    ->map(fn (array $player): array => $this->playerSnapshot($player))
                    ->all(),
            ],
        ];
    }

    protected function buildBestFormationFromSquad(array $squad): array
    {
        $formations = [
            ['DEF' => 3, 'MID' => 4, 'FWD' => 3],
            ['DEF' => 3, 'MID' => 5, 'FWD' => 2],
            ['DEF' => 4, 'MID' => 4, 'FWD' => 2],
            ['DEF' => 4, 'MID' => 3, 'FWD' => 3],
            ['DEF' => 4, 'MID' => 5, 'FWD' => 1],
            ['DEF' => 5, 'MID' => 3, 'FWD' => 2],
            ['DEF' => 5, 'MID' => 4, 'FWD' => 1],
        ];

        $goalkeepers = collect($squad)->where('position_code', 'GK')->sortByDesc('search_score')->values();
        $defenders = collect($squad)->where('position_code', 'DEF')->sortByDesc('search_score')->values();
        $midfielders = collect($squad)->where('position_code', 'MID')->sortByDesc('search_score')->values();
        $forwards = collect($squad)->where('position_code', 'FWD')->sortByDesc('search_score')->values();

        $bestFormation = [
            'formation' => '3-4-3',
            'projected_score' => 0.0,
            'starting_xi' => [],
            'bench' => collect($squad)->sortByDesc('search_score')->values()->all(),
        ];

        foreach ($formations as $formation) {
            if ($goalkeepers->count() < 1 || $defenders->count() < $formation['DEF'] || $midfielders->count() < $formation['MID'] || $forwards->count() < $formation['FWD']) {
                continue;
            }

            $selected = collect([
                $goalkeepers->first(),
                ...$defenders->take($formation['DEF'])->all(),
                ...$midfielders->take($formation['MID'])->all(),
                ...$forwards->take($formation['FWD'])->all(),
            ])->filter()->values();

            $projectedScore = (float) $selected->sum(fn (array $player): float => (float) $player['search_score']);

            if ($projectedScore <= $bestFormation['projected_score']) {
                continue;
            }

            $bench = collect($squad)
                ->reject(fn (array $player): bool => $selected->contains(fn (array $chosen): bool => $chosen['id'] === $player['id']))
                ->sortByDesc('search_score')
                ->values()
                ->all();

            $bestFormation = [
                'formation' => sprintf('%d-%d-%d', $formation['DEF'], $formation['MID'], $formation['FWD']),
                'projected_score' => round($projectedScore, 2),
                'starting_xi' => $selected->all(),
                'bench' => $bench,
            ];
        }

        return $bestFormation;
    }

    protected function buildOwnedPlayers(
        array $picks,
        Collection $elementsById,
        Collection $teamsById,
        array $fixtures,
        ?int $currentGameweek,
    ): array {
        $fixtureMap = $this->buildFixtureMap($fixtures, $teamsById, $currentGameweek);

        return collect($picks['picks'] ?? [])
            ->map(function (array $pick) use ($elementsById, $teamsById, $fixtureMap): array {
                $player = $elementsById->get($pick['element']);

                if (! $player) {
                    return [];
                }

                return $this->formatPlayer(
                    $player,
                    ['teams' => $teamsById->values()->all()],
                    $fixtureMap,
                    (int) $pick['position'],
                    (bool) ($pick['is_captain'] ?? false),
                    (bool) ($pick['is_vice_captain'] ?? false),
                    (int) ($pick['multiplier'] ?? 0),
                );
            })
            ->filter()
            ->values()
            ->map(function (array $player): array {
                $player['is_bench'] = $player['squad_position'] > 11;

                return $player;
            })
            ->all();
    }

    protected function buildPlayerCatalog(array $bootstrap, ?int $teamId = null): Collection
    {
        $teamsById = collect($bootstrap['teams'] ?? [])->keyBy('id');
        $fixturesByTeam = $this->buildFixtureMap($bootstrap['fixtures'] ?? [], $teamsById, $this->getCurrentGameweek());

        return collect($bootstrap['elements'] ?? [])
            ->map(function (array $player) use ($bootstrap, $fixturesByTeam, $teamId): array {
                $formatted = $this->formatPlayer($player, $bootstrap, $fixturesByTeam);
                $formatted['is_owned_by_team'] = $teamId !== null && (int) ($formatted['team']['id'] ?? 0) === $teamId;

                return $formatted;
            })
            ->when($teamId !== null, fn (Collection $players): Collection => $players->filter(fn (array $player): bool => (int) ($player['team']['id'] ?? 0) === $teamId))
            ->sortByDesc('search_score')
            ->values();
    }

    /**
     * Apply search and filter rules to the player catalog.
     */
    protected function filterPlayersCatalog(
        Collection $players,
        ?string $search = null,
        ?string $position = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
    ): Collection {
        if ($search !== null && trim($search) !== '') {
            $term = mb_strtolower(trim($search));

            $players = $players->filter(function (array $player) use ($term): bool {
                $haystack = mb_strtolower(
                    implode(' ', [
                        $player['name'],
                        $player['web_name'],
                        $player['team']['name'],
                        $player['team']['short_name'],
                        $player['position_name'],
                    ])
                );

                return str_contains($haystack, $term);
            });
        }

        if ($position !== null && $position !== '') {
            $normalizedPosition = $this->normalizePosition($position);

            $players = $players->filter(fn (array $player): bool => $player['position_code'] === $normalizedPosition);
        }

        if ($minPrice !== null) {
            $players = $players->filter(fn (array $player): bool => (float) ($player['cost'] ?? 0) >= $minPrice);
        }

        if ($maxPrice !== null) {
            $players = $players->filter(fn (array $player): bool => (float) ($player['cost'] ?? 0) <= $maxPrice);
        }

        return $players
            ->sortByDesc(fn (array $player): float => (float) $player['search_score'])
            ->values();
    }

    protected function formatPlayer(
        array $player,
        array $bootstrap,
        array $fixturesByTeam,
        ?int $squadPosition = null,
        bool $isCaptain = false,
        bool $isViceCaptain = false,
        int $multiplier = 0,
    ): array {
        $position = $this->normalizePosition((string) ($player['element_type'] ?? ''));
        $team = $this->resolveTeamNameById((int) ($player['team'] ?? 0), $bootstrap);
        $nextFixtures = $fixturesByTeam[(int) ($player['team'] ?? 0)] ?? [];
        $searchScore = $this->calculatePlayerScore($player, $nextFixtures);
        $photoUrl = $this->buildPlayerPhotoUrl($player);

        return [
            'id' => (int) $player['id'],
            'name' => trim(($player['first_name'] ?? '').' '.($player['second_name'] ?? '')),
            'web_name' => $player['web_name'] ?? null,
            'photo' => $player['photo'] ?? null,
            'photo_url' => $photoUrl,
            'position_code' => $position,
            'position_name' => $this->resolvePositionName($position, $bootstrap),
            'team' => $team,
            'jersey_url' => $this->buildTeamJerseyUrl((int) ($player['team'] ?? 0)),
            'now_cost' => (int) ($player['now_cost'] ?? 0),
            'cost' => round(((int) ($player['now_cost'] ?? 0)) / 10, 1),
            'form' => (float) ($player['form'] ?? 0),
            'points_per_game' => (float) ($player['points_per_game'] ?? 0),
            'minutes' => (int) ($player['minutes'] ?? 0),
            'total_points' => (int) ($player['total_points'] ?? 0),
            'selected_by_percent' => (float) ($player['selected_by_percent'] ?? 0),
            'chance_of_playing_next_round' => (int) ($player['chance_of_playing_next_round'] ?? 100),
            'status' => $player['status'] ?? null,
            'news' => $player['news'] ?? null,
            'ict_index' => (float) ($player['ict_index'] ?? 0),
            'dreamteam_count' => (int) ($player['dreamteam_count'] ?? 0),
            'goals_scored' => (int) ($player['goals_scored'] ?? 0),
            'assists' => (int) ($player['assists'] ?? 0),
            'clean_sheets' => (int) ($player['clean_sheets'] ?? 0),
            'yellow_cards' => (int) ($player['yellow_cards'] ?? 0),
            'red_cards' => (int) ($player['red_cards'] ?? 0),
            'in_dreamteam' => (bool) ($player['in_dreamteam'] ?? false),
            'is_captain' => $isCaptain,
            'is_vice_captain' => $isViceCaptain,
            'multiplier' => $multiplier,
            'squad_position' => $squadPosition,
            'is_starting' => $squadPosition !== null ? $squadPosition <= 11 : null,
            'is_bench' => $squadPosition !== null ? $squadPosition > 11 : null,
            'next_fixtures' => $nextFixtures,
            'search_score' => round($searchScore, 2),
        ];
    }

    protected function playerSnapshot(array $player): array
    {
        return [
            'id' => $player['id'],
            'name' => $player['name'],
            'web_name' => $player['web_name'],
            'photo' => $player['photo'] ?? null,
            'photo_url' => $player['photo_url'] ?? null,
            'position_code' => $player['position_code'],
            'position_name' => $player['position_name'],
            'team' => $player['team'],
            'jersey_url' => $player['jersey_url'] ?? ($player['team']['badge_url'] ?? null),
            'cost' => $player['cost'],
            'form' => $player['form'],
            'points_per_game' => $player['points_per_game'],
            'selected_by_percent' => $player['selected_by_percent'],
            'total_points' => $player['total_points'],
            'search_score' => $player['search_score'],
            'next_fixtures' => $player['next_fixtures'],
        ];
    }

    protected function buildFixtureMap(array $fixtures, Collection $teamsById, ?int $currentGameweek): array
    {
        $fixtureMap = [];

        foreach ($fixtures as $fixture) {
            $event = (int) ($fixture['event'] ?? 0);

            if ($currentGameweek !== null && $event !== 0 && $event < $currentGameweek) {
                continue;
            }

            foreach (['team_h', 'team_a'] as $side) {
                $teamId = (int) ($fixture[$side] ?? 0);

                if ($teamId === 0) {
                    continue;
                }

                $opponentSide = $side === 'team_h' ? 'team_a' : 'team_h';
                $opponent = $teamsById->get((int) ($fixture[$opponentSide] ?? 0));

                $fixtureMap[$teamId][] = [
                    'event' => $event,
                    'difficulty' => (int) ($fixture['difficulty'] ?? 3),
                    'kickoff_time' => $fixture['kickoff_time'] ?? null,
                    'is_home' => $side === 'team_h',
                    'opponent_team' => [
                        'id' => (int) ($opponent['id'] ?? 0),
                        'name' => $opponent['name'] ?? null,
                        'short_name' => $opponent['short_name'] ?? null,
                    ],
                ];
            }
        }

        foreach ($fixtureMap as $teamId => $teamFixtures) {
            usort($fixtureMap[$teamId], static function (array $left, array $right): int {
                return [$left['event'], $left['kickoff_time']] <=> [$right['event'], $right['kickoff_time']];
            });

            $fixtureMap[$teamId] = array_slice($fixtureMap[$teamId], 0, 3);
        }

        return $fixtureMap;
    }

    protected function calculatePlayerScore(array $player, array $nextFixtures): float
    {
        $form = (float) ($player['form'] ?? 0);
        $pointsPerGame = (float) ($player['points_per_game'] ?? 0);
        $minutes = (int) ($player['minutes'] ?? 0);
        $ictIndex = (float) ($player['ict_index'] ?? 0);
        $chance = (int) ($player['chance_of_playing_next_round'] ?? 100);
        $status = (string) ($player['status'] ?? 'a');

        $fixtureBonus = collect($nextFixtures)
            ->take(3)
            ->avg(fn (array $fixture): float => 5 - (float) ($fixture['difficulty'] ?? 3));

        $availabilityBonus = match ($status) {
            'a' => 0.0,
            'd' => -0.5,
            'u' => -1.0,
            'i' => -2.5,
            's' => -1.5,
            default => 0.0,
        };

        $chanceBonus = $chance < 100 ? (($chance - 100) / 25) : 0.0;
        $minutesBonus = min($minutes / 180, 3.0);
        $attackBonus = ((int) ($player['goals_scored'] ?? 0) * 0.35) + ((int) ($player['assists'] ?? 0) * 0.25);

        return round(
            ($form * 3.2)
            + ($pointsPerGame * 4)
            + $minutesBonus
            + ($ictIndex * 0.04)
            + (float) ($fixtureBonus ?? 0)
            + $attackBonus
            + $availabilityBonus
            + $chanceBonus,
            4
        );
    }

    protected function buildTransferReason(array $outPlayer, array $inPlayer, float $pointsGain): string
    {
        $fixtureText = $this->describeFixtures($inPlayer['next_fixtures'] ?? []);

        return sprintf(
            '%s is projected to outscore %s by %s points over the next few gameweeks, with %s.',
            $inPlayer['name'],
            $outPlayer['name'],
            number_format($pointsGain, 1),
            $fixtureText
        );
    }

    protected function buildCaptainReason(array $player): string
    {
        $fixtureText = $this->describeFixtures($player['next_fixtures'] ?? []);

        return sprintf(
            '%s combines strong form, secure minutes, and %s, which makes him the safest captaincy play from the current squad.',
            $player['name'],
            $fixtureText
        );
    }

    protected function describeFixtures(array $fixtures): string
    {
        if ($fixtures === []) {
            return 'a neutral fixture run';
        }

        $fixture = $fixtures[0];
        $opponent = $fixture['opponent_team']['short_name'] ?? $fixture['opponent_team']['name'] ?? 'unknown opposition';
        $difficulty = (int) ($fixture['difficulty'] ?? 3);

        return sprintf(
            'a %s fixture against %s',
            $difficulty <= 2 ? 'favourable' : ($difficulty >= 4 ? 'tough' : 'balanced'),
            $opponent
        );
    }

    protected function resolveTeamNameById(int $teamId, array $bootstrap): array
    {
        $team = collect($bootstrap['teams'] ?? [])->firstWhere('id', $teamId);

        if (! $team) {
            return [
                'id' => $teamId,
                'name' => 'Unknown',
                'short_name' => null,
                'badge_url' => $this->buildTeamBadgeUrl($teamId),
            ];
        }

        return [
            'id' => (int) $team['id'],
            'name' => $team['name'] ?? 'Unknown',
            'short_name' => $team['short_name'] ?? null,
            'badge_url' => $this->buildTeamBadgeUrl((int) $team['id']),
        ];
    }

    protected function resolvePositionName(string $positionCode, array $bootstrap): string
    {
        $position = collect($bootstrap['element_types'] ?? [])->first(function (array $elementType) use ($positionCode): bool {
            return $this->normalizePosition((string) ($elementType['id'] ?? '')) === $positionCode;
        });

        if (! $position) {
            return $positionCode;
        }

        return $position['singular_name_short'] ?? $position['singular_name'] ?? $positionCode;
    }

    protected function normalizePosition(string $position): string
    {
        return match ($position) {
            '1', 'GK', 'Goalkeeper' => 'GK',
            '2', 'DEF', 'Defender' => 'DEF',
            '3', 'MID', 'Midfielder' => 'MID',
            '4', 'FWD', 'Forward' => 'FWD',
            default => $position,
        };
    }

    protected function formatDeadline(?string $deadline): ?string
    {
        if ($deadline === null || $deadline === '') {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime($deadline));
    }

    protected function buildPlayerPhotoUrl(mixed $player): ?string
    {
        $playerCode = null;

        if (is_array($player)) {
            $playerCode = $player['code'] ?? null;

            if ($playerCode === null && isset($player['photo'])) {
                $playerCode = preg_replace('/\D+/', '', (string) $player['photo']);
            }

            if (($playerCode === null || $playerCode === '') && isset($player['id'])) {
                $playerCode = (string) $player['id'];
            }
        } else {
            $playerCode = $player;
        }

        if ($playerCode === null || $playerCode === '') {
            return null;
        }

        return "https://resources.premierleague.com/premierleague/photos/players/40x40/p{$playerCode}.png";
    }

    protected function buildTeamBadgeUrl(int $teamId): string
    {
        return $this->getTeamImageUrl($teamId, 'badge');
    }

    protected function buildTeamJerseyUrl(int $teamId): string
    {
        return $this->getTeamImageUrl($teamId, 'jersey');
    }

    protected function resolveTeamImageMeta(int $teamId): array
    {
        $fallbackTeam = $this->fallbackTeamCatalog()[$teamId] ?? null;

        try {
            $bootstrap = $this->getBootstrapStatic();
            $team = collect($bootstrap['teams'] ?? [])->firstWhere('id', $teamId);

            if ($team) {
                return [
                    'id' => (int) $team['id'],
                    'name' => $team['name'] ?? 'Team '.$teamId,
                    'short_name' => $team['short_name'] ?? 'T'.$teamId,
                ];
            }
        } catch (\Throwable) {
            // Fall back to a local placeholder image when FPL is unavailable.
        }

        if ($fallbackTeam !== null) {
            return [
                'id' => $teamId,
                'name' => $fallbackTeam['name'],
                'short_name' => $fallbackTeam['short_name'],
            ];
        }

        return [
            'id' => $teamId,
            'name' => 'Team '.$teamId,
            'short_name' => 'T'.$teamId,
        ];
    }

    protected function teamPalette(int $teamId): array
    {
        $palettes = [
            ['primary' => '#0f766e', 'secondary' => '#14b8a6', 'accent' => '#042f2e'],
            ['primary' => '#1d4ed8', 'secondary' => '#60a5fa', 'accent' => '#0f172a'],
            ['primary' => '#b91c1c', 'secondary' => '#f87171', 'accent' => '#450a0a'],
            ['primary' => '#6d28d9', 'secondary' => '#a78bfa', 'accent' => '#2e1065'],
            ['primary' => '#ca8a04', 'secondary' => '#facc15', 'accent' => '#422006'],
            ['primary' => '#047857', 'secondary' => '#34d399', 'accent' => '#022c22'],
            ['primary' => '#db2777', 'secondary' => '#f472b6', 'accent' => '#831843'],
            ['primary' => '#ea580c', 'secondary' => '#fb923c', 'accent' => '#431407'],
        ];

        return $palettes[($teamId - 1) % count($palettes)];
    }

    protected function escapeSvgText(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected function fallbackTeamCatalog(): array
    {
        return [
            1 => ['name' => 'Arsenal', 'short_name' => 'ARS'],
            2 => ['name' => 'Aston Villa', 'short_name' => 'AVL'],
            3 => ['name' => 'Bournemouth', 'short_name' => 'BOU'],
            4 => ['name' => 'Brentford', 'short_name' => 'BRE'],
            5 => ['name' => 'Brighton & Hove Albion', 'short_name' => 'BHA'],
            6 => ['name' => 'Burnley', 'short_name' => 'BUR'],
            7 => ['name' => 'Chelsea', 'short_name' => 'CHE'],
            8 => ['name' => 'Crystal Palace', 'short_name' => 'CRY'],
            9 => ['name' => 'Everton', 'short_name' => 'EVE'],
            10 => ['name' => 'Fulham', 'short_name' => 'FUL'],
            11 => ['name' => 'Liverpool', 'short_name' => 'LIV'],
            12 => ['name' => 'Manchester City', 'short_name' => 'MCI'],
            13 => ['name' => 'Manchester United', 'short_name' => 'MUN'],
            14 => ['name' => 'Newcastle United', 'short_name' => 'NEW'],
            15 => ['name' => 'Nottingham Forest', 'short_name' => 'NFO'],
            16 => ['name' => 'Nott\'m Forest', 'short_name' => 'NFO'],
            17 => ['name' => 'Tottenham Hotspur', 'short_name' => 'TOT'],
            18 => ['name' => 'West Ham United', 'short_name' => 'WHU'],
            19 => ['name' => 'Wolverhampton Wanderers', 'short_name' => 'WOL'],
            20 => ['name' => 'Leicester City', 'short_name' => 'LEI'],
        ];
    }
}
