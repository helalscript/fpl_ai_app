<?php

namespace App\Services\API\V1\Fpl;

use App\Ai\Agents\FplChatAssistant;
use App\Ai\Agents\FplCoach;
use App\Models\User;
use Illuminate\Support\Arr;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;

class FplAiService
{
    public function __construct(protected FplService $fpl) {}

    /**
     * Analyse a team dashboard and return a structured AI summary.
     */
    public function analyzeDashboard(int $teamId): array
    {
        $teamData = $this->fpl->getFullTeamData($teamId);
        $analysisPrompt = $this->buildDashboardPrompt($teamId, $teamData);

        $analysis = FplCoach::make()->prompt(
            $analysisPrompt,
            provider: Lab::OpenAI,
            model: 'gpt-4o'
        );

        return [
            'raw_data' => $teamData,
            'ai_analysis' => $analysis,
            'squad_rating' => $analysis['squad_rating'] ?? 84,
        ];
    }

    /**
     * Generate a chat response for a manager and their FPL team.
     */
    public function chat(
        string $message,
        int $teamId,
        ?User $user = null,
        array $history = [],
        ?string $conversationId = null
    ): AgentResponse {
        $agent = $this->buildChatAgent($user, $conversationId);

        return $agent->prompt(
            $this->buildChatPrompt($teamId, $message, $user, $history),
            provider: Lab::OpenAI,
            model: 'gpt-4o'
        );
    }

    /**
     * Stream a chat response for the active manager.
     */
    public function streamChat(
        string $message,
        int $teamId,
        ?User $user = null,
        array $history = [],
        ?string $conversationId = null
    ): StreamableAgentResponse {
        $agent = $this->buildChatAgent($user, $conversationId);

        return $agent->stream(
            $this->buildChatPrompt($teamId, $message, $user, $history),
            provider: Lab::OpenAI,
            model: 'gpt-4o'
        );
    }

    /**
     * Build the AI agent with optional conversation memory.
     */
    protected function buildChatAgent(?User $user = null, ?string $conversationId = null): FplChatAssistant
    {
        $agent = FplChatAssistant::make();

        if ($user === null) {
            return $agent;
        }

        if ($conversationId !== null && $conversationId !== '') {
            return $agent->continue($conversationId, $user);
        }

        return $agent->continueLastConversation($user);
    }

    /**
     * Build the dashboard analysis prompt.
     */
    protected function buildDashboardPrompt(int $teamId, array $teamData): string
    {
        $team = $teamData['team'] ?? [];
        $context = [
            'team_id' => $teamId,
            'team' => [
                'name' => $team['name'] ?? null,
                'short_name' => $team['short_name'] ?? null,
                'current_gameweek' => $teamData['current_gw'] ?? null,
            ],
            'current_squad' => $this->summarizePlayers($teamData['current_squad'] ?? []),
            'bench_players' => $this->summarizePlayers($teamData['bench_players'] ?? []),
            'transfer_suggestions' => $teamData['transfer_suggestions'] ?? [],
            'captaincy_suggestion' => $teamData['captaincy_suggestion'] ?? [],
            'bench_players_count' => count($teamData['bench_players'] ?? []),
            'available_transfers' => Arr::get($teamData, 'team_context.available_transfers', null),
        ];

        return <<<PROMPT
Analyze this Fantasy Premier League team thoroughly and provide clear, actionable recommendations for the current gameweek.

Rules:
- Be specific and practical.
- Explain the reasoning behind each recommendation.
- Prefer the most likely high-upside answer.
- Mention fixture difficulty, minutes risk, form, and captaincy where relevant.
- Do not hallucinate players or stats.

Team context:
{$this->encodeContextForPrompt($context)}
PROMPT;
    }

    /**
     * Build the chat prompt using user identity and live FPL context.
     */
    protected function buildChatPrompt(int $teamId, string $message, ?User $user, array $history = []): string
    {
        $teamData = $this->fpl->getFullTeamData($teamId);

        $context = [
            'authenticated_user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'fpl_team_id' => $user->fpl_team_id,
            ] : null,
            'team_id' => $teamId,
            'team' => [
                'name' => $teamData['team']['name'] ?? null,
                'short_name' => $teamData['team']['short_name'] ?? null,
                'current_gameweek' => $teamData['current_gw'] ?? null,
            ],
            'current_squad' => $this->summarizePlayers($teamData['current_squad'] ?? []),
            'bench_players' => $this->summarizePlayers($teamData['bench_players'] ?? []),
            'transfer_suggestions' => $teamData['transfer_suggestions'] ?? [],
            'captaincy_suggestion' => $teamData['captaincy_suggestion'] ?? [],
            'history' => array_slice($history, -10),
            'instructions' => [
                'answer_style' => 'helpful, concise, and practical',
                'goal' => 'give the best possible FPL advice for the current team',
                'fallback' => 'if the request is ambiguous, make the most reasonable assumption and state it briefly',
            ],
        ];

        $userName = $user?->name ?? 'manager';

        return <<<PROMPT
You are speaking with {$userName} about Fantasy Premier League.

Use the following context to answer as well as possible:
{$this->encodeContextForPrompt($context)}

User message:
{$message}

Response rules:
- Be friendly and helpful.
- Use the user's name naturally when appropriate.
- Base the answer on the team context above.
- If recommending a transfer, captain, bench choice, or chip decision, explain why in one or two clear points.
- Keep the answer focused on the user's question and avoid unnecessary filler.
PROMPT;
    }

    /**
     * Convert player data into a compact prompt-friendly shape.
     */
    protected function summarizePlayers(array $players): array
    {
        return collect($players)
            ->map(function (array $player): array {
                return [
                    'id' => $player['id'] ?? null,
                    'name' => $player['name'] ?? null,
                    'web_name' => $player['web_name'] ?? null,
                    'team' => $player['team']['short_name'] ?? ($player['team']['name'] ?? null),
                    'position' => $player['position_code'] ?? null,
                    'cost' => $player['cost'] ?? null,
                    'form' => $player['form'] ?? null,
                    'points_per_game' => $player['points_per_game'] ?? null,
                    'search_score' => $player['search_score'] ?? null,
                    'next_fixtures' => collect($player['next_fixtures'] ?? [])
                        ->take(2)
                        ->map(fn (array $fixture): array => [
                            'opponent' => $fixture['opponent_team']['short_name'] ?? $fixture['opponent_team']['name'] ?? null,
                            'difficulty' => $fixture['difficulty'] ?? null,
                            'is_home' => $fixture['is_home'] ?? null,
                        ])
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Encode the prompt payload as readable JSON.
     */
    protected function encodeContextForPrompt(array $context): string
    {
        return json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
