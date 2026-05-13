<?php

namespace App\Http\Controllers\API\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Fpl\FplChatMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FplChatConversationsController extends Controller
{
    public function __construct(protected FplChatMessageService $chatMessages) {}

    /**
     * List chat threads for this FPL team (most recently active first).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $teamId = $this->resolveTeamId($request, $user);

        if ($teamId === null) {
            return Helper::jsonResponse(false, 'team_id is required.', 422);
        }

        if (! $this->chatMessages->teamIdAllowedForUser($user, $teamId)) {
            return Helper::jsonResponse(false, 'The team_id must match your FPL entry ID saved on your profile.', 422);
        }

        return Helper::jsonResponse(true, 'Conversations fetched successfully.', 200, [
            'conversations' => $this->chatMessages->listConversationsSummary($user->id, $teamId),
        ]);
    }

    /**
     * Start a new empty thread (user can discuss separately from older chats).
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $teamId = $this->resolveTeamId($request, $user);

        if ($teamId === null) {
            return Helper::jsonResponse(false, 'team_id is required.', 422);
        }

        if (! $this->chatMessages->teamIdAllowedForUser($user, $teamId)) {
            return Helper::jsonResponse(false, 'The team_id must match your FPL entry ID saved on your profile.', 422);
        }

        $conversation = $this->chatMessages->createConversation($user, $teamId);

        return Helper::jsonResponse(true, 'Conversation created successfully.', 201, [
            'conversation' => [
                'id' => (int) $conversation->id,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'created_at' => $conversation->created_at?->toIso8601String() ?? '',
            ],
        ]);
    }

    protected function resolveTeamId(Request $request, User $user): ?int
    {
        $teamId = (int) $request->integer('team_id', 0);

        if ($teamId > 0) {
            return $teamId;
        }

        $authenticatedTeamId = (int) ($user->fpl_team_id ?? 0);

        return $authenticatedTeamId > 0 ? $authenticatedTeamId : null;
    }
}
