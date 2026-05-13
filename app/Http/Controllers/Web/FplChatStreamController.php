<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\API\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\FplChatMessage;
use App\Models\User;
use App\Services\API\V1\Fpl\FplAiService;
use App\Services\Fpl\FplChatMessageService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class FplChatStreamController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected FplAiService $fplAi,
        protected FplChatMessageService $chatMessages,
    ) {}

    /**
     * Stream FPL chat as server-sent events (same behaviour as the API stream route, session-auth).
     */
    public function __invoke(Request $request): JsonResponse|Response
    {
        $validated = $request->validate([
            'team_id' => 'nullable|integer|min:1',
            'thread_id' => 'nullable|integer|min:1',
            'message' => 'required|string|max:1000',
            'history' => 'sometimes|nullable|array|max:20',
            'history.*.role' => 'sometimes|string|in:user,assistant',
            'history.*.content' => 'sometimes|string|max:8000',
            'conversation_id' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            return self::jsonErrorResponse('Unauthenticated.', 401);
        }

        $teamId = $this->resolveTeamId($request);

        if ($teamId === null) {
            return self::jsonErrorResponse('team_id is required.', 422);
        }

        if (! $this->chatMessages->teamIdAllowedForUser($user, $teamId)) {
            return self::jsonErrorResponse(
                'The team_id must match your FPL entry ID saved on your profile.',
                422
            );
        }

        $threadId = isset($validated['thread_id']) ? (int) $validated['thread_id'] : null;
        $thread = null;

        if ($threadId !== null) {
            $thread = $this->chatMessages->findConversationForUser($threadId, $user, $teamId);

            if ($thread === null) {
                return self::jsonErrorResponse('Invalid thread_id for this team.', 422);
            }
        } else {
            $thread = $this->chatMessages->defaultConversationForTeam($user, $teamId);
        }

        $conversationDbId = (int) $thread->id;

        $aiConversationId = $validated['conversation_id'] ?? ('fpl-thread-'.$conversationDbId);

        $history = $this->chatMessages->historyForPrompt($user->id, $teamId, $conversationDbId, 20);
        $isFirstUserMessageToday = ! $this->chatMessages->hasUserMessageToday($user->id, $teamId, $conversationDbId);

        $this->chatMessages->storeMessage(
            $user,
            $teamId,
            $conversationDbId,
            FplChatMessage::ROLE_USER,
            $validated['message'],
        );

        try {
            ignore_user_abort(true);
            set_time_limit(0);

            $response = $this->fplAi->streamChat(
                $validated['message'],
                $teamId,
                $user,
                $history,
                $aiConversationId,
                $isFirstUserMessageToday,
            );

            return $this->chatMessages->streamSseAndPersistAssistantDraft(
                $response,
                $user,
                $teamId,
                $conversationDbId,
            );
        } catch (Exception $e) {
            Log::error('FplChatStreamController: '.$e->getMessage());

            return self::jsonErrorResponse('Failed to fetch chat response.', 500, [$e->getMessage()]);
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
}
