<?php

namespace App\Ai\Agents;

use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

class FplChatAssistant implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    /**
     * Get the instructions that guide the chat assistant.
     */
    public function instructions(): Stringable|string
    {
        return "You are a friendly Fantasy Premier League assistant.
Give the best possible FPL answer using the supplied team context.
Be specific, practical, and helpful.
Prefer clear recommendations over generic advice.
If the user's request is ambiguous, make the most reasonable assumption and say so briefly.";
    }

    /**
     * Get the messages for the current conversation.
     */
    public function messages(): iterable
    {
        return [];
    }
}
