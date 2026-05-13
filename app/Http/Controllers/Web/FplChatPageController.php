<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Fpl\FplChatMessageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FplChatPageController extends Controller
{
    public function __construct(protected FplChatMessageService $chatMessages) {}

    /**
     * Render the FPL assistant page with persisted messages for the user's current team.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $teamId = (int) ($user->fpl_team_id ?? 0);
        $initialMessages = [];
        $initialHasMoreOlder = false;
        $initialThreadId = null;
        $initialConversations = [];
        $promptSuggestions = $this->chatMessages->activePromptSuggestions();

        if ($teamId > 0) {
            $thread = $this->chatMessages->defaultConversationForTeam($user, $teamId);
            $initialThreadId = (int) $thread->id;
            $initialConversations = $this->chatMessages->listConversationsSummary($user->id, $teamId);
            $page = $this->chatMessages->messagesPageForUi($user->id, $teamId, $initialThreadId);
            $initialMessages = is_array($page['messages'] ?? null) ? $page['messages'] : [];
            $initialHasMoreOlder = (bool) ($page['has_more_older'] ?? false);
        }

        return Inertia::render('fpl-chat', [
            'promptSuggestions' => $promptSuggestions,
            'initialThreadId' => $initialThreadId,
            'initialConversations' => $initialConversations,
            'initialMessages' => $initialMessages,
            'initialHasMoreOlder' => $initialHasMoreOlder,
        ]);
    }
}
