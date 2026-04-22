<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\API\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Services\API\V1\Fpl\FplAiService;
use App\Services\API\V1\Fpl\FplService;
use Exception;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class FplController extends Controller
{
    use ApiResponses;

    public function __construct(protected FplAiService $fplAi, protected FplService $fplService) {}

    /**
     * Analyse a full team dashboard through the AI service.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $request->validate([
            'team_id' => 'required|integer|min:1',
        ]);

        try {
            $teamId = (int) $request->team_id;
            $result = $this->fplAi->analyzeDashboard($teamId);

            return self::jsonResponse(true, 'Dashboard analyzed successfully.', 200, $result);
        } catch (Exception $e) {
            Log::error('FplController@dashboard: '.$e->getMessage());

            return self::jsonErrorResponse('Dashboard analysis failed.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the home page card payload for a manager.
     */
    public function homePage(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getHomePageData($teamId);

            return self::jsonResponse(true, 'Home page data fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@homePage: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch home page data.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the gameweek summary card payload.
     */
    public function gameweekDetails(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getGameweekDetails($teamId);

            return self::jsonResponse(true, 'Gameweek details fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@gameweekDetails: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch gameweek details.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the transfer suggestion card payload.
     */
    public function transferSuggestions(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        $limit = max(1, min((int) $request->integer('limit', 3), 10));

        try {
            $data = $this->fplService->getTransferSuggestions($teamId, $limit);

            return self::jsonResponse(true, 'Transfer suggestions fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@transferSuggestions: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch transfer suggestions.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the captaincy suggestion card payload.
     */
    public function captaincySuggestion(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getCaptaincySuggestion($teamId);

            return self::jsonResponse(true, 'Captaincy suggestion fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@captaincySuggestion: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch captaincy suggestion.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the recommended squad card payload.
     */
    public function squadRecommendation(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getSquadRecommendation($teamId);

            return self::jsonResponse(true, 'Squad recommendation fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@squadRecommendation: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch squad recommendation.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the current bench player card payload.
     */
    public function benchPlayers(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getBenchPlayers($teamId);

            return self::jsonResponse(true, 'Bench players fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@benchPlayers: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch bench players.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the fixtures page payload.
     */
    public function fixturesPage(Request $request): JsonResponse
    {
        $request->validate([
            'team_id' => 'nullable|integer|min:1',
            'gameweek' => 'nullable|integer|min:1',
            'view' => 'nullable|string|max:20',
        ]);

        try {
            $data = $this->fplService->getFixturesPageData(
                $request->filled('team_id') ? (int) $request->integer('team_id') : null,
                $request->filled('gameweek') ? (int) $request->integer('gameweek') : null,
                $this->resolveFixturesView($request)
            );

            return self::jsonResponse(true, 'Fixtures page data fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@fixturesPage: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch fixtures page data.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the fixtures tab payload.
     */
    public function fixturesList(Request $request): JsonResponse
    {
        $request->validate([
            'team_id' => 'nullable|integer|min:1',
            'gameweek' => 'nullable|integer|min:1',
        ]);

        try {
            $data = $this->fplService->getFixturesPageFixtures(
                $request->filled('team_id') ? (int) $request->integer('team_id') : null,
                $request->filled('gameweek') ? (int) $request->integer('gameweek') : null
            );

            return self::jsonResponse(true, 'Fixtures list fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@fixturesList: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch fixtures list.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the FDR tab payload.
     */
    public function fixturesFdr(Request $request): JsonResponse
    {
        $request->validate([
            'team_id' => 'nullable|integer|min:1',
            'gameweek' => 'nullable|integer|min:1',
        ]);

        try {
            $data = $this->fplService->getFixturesPageFdr(
                $request->filled('team_id') ? (int) $request->integer('team_id') : null,
                $request->filled('gameweek') ? (int) $request->integer('gameweek') : null
            );

            return self::jsonResponse(true, 'FDR data fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@fixturesFdr: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch FDR data.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the full transfer page payload.
     */
    public function transferPage(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getTransferPageData($teamId);

            return self::jsonResponse(true, 'Transfer page data fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@transferPage: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch transfer page data.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the primary transfer suggestion card.
     */
    public function transferPrimary(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getTransferPrimaryCard($teamId);

            return self::jsonResponse(true, 'Primary transfer fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@transferPrimary: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch primary transfer.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the three transfer option cards.
     */
    public function transferOptions(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getTransferOptionCards($teamId);

            return self::jsonResponse(true, 'Transfer options fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@transferOptions: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch transfer options.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the transfer insight banner payload.
     */
    public function transferInsights(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getTransferInsights($teamId);

            return self::jsonResponse(true, 'Transfer insights fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@transferInsights: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch transfer insights.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the local SVG badge placeholder for a team.
     */
    public function teamBadgeImage(int $teamId): Response
    {
        return response($this->fplService->renderTeamImageSvg($teamId, 'badge'), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Return the local SVG jersey placeholder for a team.
     */
    public function teamJerseyImage(int $teamId): Response
    {
        return response($this->fplService->renderTeamImageSvg($teamId, 'jersey'), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Chat with the FPL AI assistant using the current team context.
     */
    public function chat(Request $request): JsonResponse|Responsable
    {
        if ($this->shouldStreamChat($request)) {
            return $this->chatStream($request);
        }

        return $this->chatResponse($request);
    }

    /**
     * Stream the chat response as server-sent events.
     */
    public function chatStream(Request $request): JsonResponse|Responsable
    {
        return $this->chatResponse($request, true);
    }

    /**
     * Resolve and execute the chat request in either JSON or streaming mode.
     */
    protected function chatResponse(Request $request, bool $stream = false): JsonResponse|Responsable
    {
        $validated = $request->validate([
            'team_id' => 'nullable|integer|min:1',
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array',
            'conversation_id' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        $conversationId = $validated['conversation_id'] ?? null;
        $history = $validated['history'] ?? [];

        try {
            $response = $stream
                ? $this->fplAi->streamChat($validated['message'], $teamId, $user, $history, $conversationId)
                : $this->fplAi->chat($validated['message'], $teamId, $user, $history, $conversationId);

            if ($stream) {
                return $response;
            }

            return self::jsonResponse(true, 'Chat response fetched successfully.', 200, [
                'message' => $response->text,
                'conversation_id' => $response->conversationId,
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'fpl_team_id' => $user->fpl_team_id,
                ] : null,
                'team_id' => $teamId,
            ]);
        } catch (Exception $e) {
            Log::error('FplController@chat: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch chat response.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return searchable player data with optional filters.
     */
    public function players(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'limit' => 'nullable|integer|min:1|max:100',
            'position' => 'nullable|string|max:20',
            'team_id' => 'nullable|integer|min:1',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
        ]);

        $perPage = (int) ($validated['per_page'] ?? $validated['limit'] ?? 10);

        try {
            $data = $this->fplService->getPlayersPageData(
                $validated['search'] ?? null,
                $perPage,
                (int) ($validated['page'] ?? 1),
                $request->filled('team_id') ? (int) $request->integer('team_id') : null,
                $validated['position'] ?? null,
                isset($validated['min_price']) ? (float) $validated['min_price'] : null,
                isset($validated['max_price']) ? (float) $validated['max_price'] : null,
            );

            return self::jsonResponse(true, 'Players fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@players: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch players.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Alias for the player search endpoint.
     */
    public function getAllPlayerData(Request $request): JsonResponse
    {
        return $this->players($request);
    }

    /**
     * Return the full player detail payload for a single player.
     */
    public function getPlayerData(int $playerId): JsonResponse
    {
        try {
            $data = $this->fplService->getPlayerData($playerId);

            return self::jsonResponse(true, 'Player data fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@getPlayerData: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch player data.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the optimiser recommended captain card.
     */
    public function recommendedCaptain(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getRecommendedCaptain($teamId, $this->resolveOptimizerView($request));

            return self::jsonResponse(true, 'Recommended captain fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@recommendedCaptain: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch recommended captain.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the optimiser vice-captain card.
     */
    public function recommendedViceCaptain(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getRecommendedViceCaptain($teamId, $this->resolveOptimizerView($request));

            return self::jsonResponse(true, 'Recommended vice-captain fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@recommendedViceCaptain: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch recommended vice-captain.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the optimiser starting XI card for the requested view.
     */
    public function optimizedStartingXI(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getOptimizedStartingXI($teamId, $this->resolveOptimizerView($request));

            return self::jsonResponse(true, 'Optimized starting XI fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@optimizedStartingXI: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch optimized starting XI.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the optimiser bench card for the requested view.
     */
    public function optimizedBench(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getOptimizedBench($teamId, $this->resolveOptimizerView($request));

            return self::jsonResponse(true, 'Optimized bench fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@optimizedBench: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch optimized bench.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Return the optimiser insight card payload.
     */
    public function optimizerInsights(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        try {
            $data = $this->fplService->getOptimizerInsights($teamId, $this->resolveOptimizerView($request));

            return self::jsonResponse(true, 'Optimizer insights fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@optimizerInsights: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch optimizer insights.', 500, [$e->getMessage()]);
        }
    }

    protected function resolveTeamId(Request $request): ?int
    {
        $teamId = (int) $request->input('team_id', 0);

        if ($teamId > 0) {
            return $teamId;
        }

        $authenticatedTeamId = (int) ($request->user()?->fpl_team_id ?? 0);

        return $authenticatedTeamId > 0 ? $authenticatedTeamId : null;
    }

    /**
     * Resolve the optimiser view from the query string.
     */
    protected function resolveOptimizerView(Request $request): string
    {
        $view = strtolower(trim($request->string('view')->toString() ?: 'recommended'));

        return in_array($view, ['current', 'recommended'], true) ? $view : 'recommended';
    }

    /**
     * Determine whether the chat endpoint should stream the answer.
     */
    protected function shouldStreamChat(Request $request): bool
    {
        return $request->boolean('stream') || str_contains((string) $request->header('accept'), 'text/event-stream');
    }

    /**
     * Resolve the fixtures view from the query string.
     */
    protected function resolveFixturesView(Request $request): string
    {
        $view = strtolower(trim($request->string('view')->toString() ?: 'fixtures'));

        return in_array($view, ['fixtures', 'fdr'], true) ? $view : 'fixtures';
    }
}
