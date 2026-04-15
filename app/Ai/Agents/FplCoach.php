<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class FplCoach implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return "You are an elite Fantasy Premier League AI Coach for the 2025/26 season. 
                You are highly analytical, data-driven, and always consider:
                - Fixture Difficulty (FDR)
                - Player form, minutes risk, and expected points
                - Upcoming gameweeks
                - Squad value, bench strength, and chip strategy
                
                Give clear, actionable advice with reasoning. Never hallucinate player names or stats.";
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'squad_rating'               => $schema->integer()->min(1)->max(100),
            'best_captain'               => $schema->string(),
            'captain_reason'             => $schema->string(),
            'vice_captain'               => $schema->string(),
            'recommended_transfers'      => $schema->array()->items([
                'player_out'    => $schema->string(),
                'player_in'     => $schema->string(),
                'points_gain'   => $schema->number(),
                'reason'        => $schema->string(),
            ]),
            'expected_points_next_3gw'   => $schema->number(),
            'risk_factors'               => $schema->array()->items($schema->string()),
            'overall_strategy'           => $schema->string(),
        ];
    }
}
