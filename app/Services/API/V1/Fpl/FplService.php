<?php

namespace App\Services\API\V1\Fpl;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FplService
{
    protected string $baseUrl = 'https://fantasy.premierleague.com/api';

    public function getBootstrapStatic()
    {
        //    $data= Http::get("{$this->baseUrl}/bootstrap-static/")->throw()->json();
        //    return $data;
        return Cache::remember('fpl_bootstrap_static', 3600, function () {
            return Http::get("{$this->baseUrl}/bootstrap-static/")->throw()->json();
        });
    }

    public function getTeam(int $teamId)
    {
        return Http::get("{$this->baseUrl}/entry/{$teamId}/")->throw()->json();
    }

    public function getPicks(int $teamId, ?int $gameweek = null)
    {
        $gw = $gameweek ?? $this->getCurrentGameweek();

        return Http::get("{$this->baseUrl}/entry/{$teamId}/event/{$gw}/picks/")->throw()->json();
    }

    public function getFixtures(?int $gameweek = null)
    {
        $url = $gameweek
            ? "{$this->baseUrl}/fixtures/?event={$gameweek}"
            : "{$this->baseUrl}/fixtures/";

        return Http::get($url)->throw()->json();
    }

    public function getCurrentGameweek(): ?int
    {
        $data = $this->getBootstrapStatic();
        foreach ($data['events'] ?? [] as $event) {
            if ($event['is_current'] ?? false) {
                return $event['id'];
            }
        }

        return null;
    }

    // Dashboard all data together
    public function getFullTeamData(int $teamId): array
    {
        $gw = $this->getCurrentGameweek();
        Log::info('Current Gameweek: '.$gw);

        return [
            'static' => $this->getBootstrapStatic(),
            'team' => $this->getTeam($teamId),
            'picks' => $this->getPicks($teamId, $gw),
            'fixtures' => $this->getFixtures($gw ? $gw + 3 : null), // next 3 GW
            'current_gw' => $gw,
        ];
    }

    public function getPlayerData(int $playerId): array
    {
        return Http::get("{$this->baseUrl}/element-summary/{$playerId}/")->throw()->json();
    }

    public function getAllPlayerData(): array
    {
        $data = $this->getBootstrapStatic();

        $responses = Http::pool(function (Pool $pool) use ($data) {
            $requests = [];

            foreach ($data['elements'] ?? [] as $element) {
                $requests[] = $pool->get("{$this->baseUrl}/element-summary/{$element['id']}/");
            }

            return $requests;
        });

        $playerData = [];

        foreach ($responses as $response) {
            if ($response->successful()) {
                $playerData[] = $response->json();
            }
        }

        return $playerData;
    }
}
