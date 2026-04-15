<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\API\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Services\API\V1\Fpl\FplAiService;
use App\Services\API\V1\Fpl\FplService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FplController extends Controller
{
    use ApiResponses;

    public function __construct(protected FplAiService $fplAi, protected FplService $fplService) {}

    // Main Dashboard API
    public function dashboard(Request $request)
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

    // AI Chat Endpoint
    public function chat(Request $request)
    {
        $request->validate([
            'team_id' => 'required|integer',
            'message' => 'required|string|max:1000',
        ]);

        $response = $this->fplAi->chat(
            $request->message,
            (int) $request->team_id,
            $request->history ?? []
        );

        return response()->json($response);
    }

    public function getAllPlayerData()
    {
        try {
            $data = $this->fplService->getAllPlayerData();

            return self::jsonResponse(true, 'All player data fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@getAllPlayerData: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch all player data.', 500, [$e->getMessage()]);
        }
    }

    public function getPlayerData(Request $request)
    {
        $request->validate([
            'player_id' => 'required|integer',
        ]);
        try {
            $data = $this->fplService->getPlayerData((int) $request->player_id);

            return self::jsonResponse(true, 'Player data fetched successfully.', 200, $data);
        } catch (Exception $e) {
            Log::error('FplController@getPlayerData: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch player data.', 500, [$e->getMessage()]);
        }
    }
}
