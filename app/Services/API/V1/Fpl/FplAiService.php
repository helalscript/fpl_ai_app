<?php

namespace App\Services\API\V1\Fpl;

use App\Ai\Agents\FplCoach;
use Laravel\Ai\Enums\Lab;

class FplAiService
{
    public function __construct(protected FplService $fpl) {}

    public function analyzeDashboard(int $teamId): array
    {
        $teamData = $this->fpl->getFullTeamData($teamId);
        $teamJson = json_encode($teamData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $agent = FplCoach::make();   // Laravel AI SDK

        $analysis = $agent->prompt(
            "Analyze this FPL team thoroughly and provide best recommendations for Gameweek {$teamData['current_gw']}:\n\n{$teamJson}",
            provider: Lab::OpenAI,     // or Lab::Xai if you want to use Grok
            model: 'gpt-4o'
        );

        return [
            'raw_data'     => $teamData,
            'ai_analysis'  => $analysis,
            'squad_rating' => $analysis['squad_rating'] ?? 84,
        ];
    }

    // AI Chat for separate
    public function chat(string $message, int $teamId, array $history = [])
    {
        $teamData = $this->fpl->getFullTeamData($teamId);

        $agent = FplCoach::make();

        return $agent->prompt($message, attachments: [
            'team_context' => $teamData,
            'history'      => $history,
        ]);
    }
}