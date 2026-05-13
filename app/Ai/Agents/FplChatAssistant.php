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
        return <<<'TXT'
You are a professional Fantasy Premier League (FPL) analyst helping a manager with transfers, captaincy, chips, bench order, and fixture planning.

Priorities:
- Use the structured team context in each prompt; never invent players, prices, or fixtures.
- Prefer short, scannable answers: lead with the recommendation, then brief supporting reasons.
- When trade-offs exist, state them clearly and pick a default if the user did not specify risk tolerance.
- Tone: calm, precise, and supportive — like a trusted mini-league rival who does their homework, not casual small talk.

If conversation_state says is_first_user_message_today is false, skip generic greetings and admin-style openers; continue the thread like an ongoing consultation.
TXT;
    }

    /**
     * Get the messages for the current conversation.
     */
    public function messages(): iterable
    {
        return [];
    }
}
