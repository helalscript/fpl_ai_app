<?php

namespace App\Http\Controllers\API\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Fpl\FplChatMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FplChatMessagesController extends Controller
{
    public function __construct(protected FplChatMessageService $chatMessages) {}

    /**
     * List stored messages for a thread (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->filled('before_id') && $request->filled('after_id')) {
            return Helper::jsonResponse(false, 'Provide either before_id or after_id, not both.', 422);
        }

        $validated = $request->validate([
            'team_id' => 'sometimes|integer|min:1',
            'thread_id' => 'required|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'before_id' => 'sometimes|integer|min:1',
            'after_id' => 'sometimes|integer|min:1',
        ]);

        /** @var User $user */
        $user = $request->user();
        $teamId = $this->resolveTeamId($request, $user);

        if ($teamId === null) {
            return Helper::jsonResponse(false, 'team_id is required.', 422);
        }

        if (! $this->chatMessages->teamIdAllowedForUser($user, $teamId)) {
            return Helper::jsonResponse(false, 'The team_id must match your FPL entry ID saved on your profile.', 422);
        }

        $threadId = (int) $validated['thread_id'];
        $thread = $this->chatMessages->findConversationForUser($threadId, $user, $teamId);

        if ($thread === null) {
            return Helper::jsonResponse(false, 'Invalid thread_id for this team.', 422);
        }

        $perPage = (int) ($validated['per_page'] ?? 10);
        $beforeId = isset($validated['before_id']) ? (int) $validated['before_id'] : null;
        $afterId = isset($validated['after_id']) ? (int) $validated['after_id'] : null;

        $page = $this->chatMessages->messagesPageForUi(
            $user->id,
            $teamId,
            $threadId,
            $perPage,
            $beforeId,
            $afterId,
        );

        return Helper::jsonResponse(true, 'Messages fetched successfully.', 200, $page,true);
    }

    /**
     * Clear all messages in the current thread.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'team_id' => 'sometimes|integer|min:1',
            'thread_id' => 'required|integer|min:1',
        ]);

        /** @var User $user */
        $user = $request->user();
        $teamId = $this->resolveTeamId($request, $user);

        if ($teamId === null) {
            return Helper::jsonResponse(false, 'team_id is required.', 422);
        }

        if (! $this->chatMessages->teamIdAllowedForUser($user, $teamId)) {
            return Helper::jsonResponse(false, 'The team_id must match your FPL entry ID saved on your profile.', 422);
        }

        $threadId = (int) $validated['thread_id'];
        $thread = $this->chatMessages->findConversationForUser($threadId, $user, $teamId);

        if ($thread === null) {
            return Helper::jsonResponse(false, 'Invalid thread_id for this team.', 422);
        }

        $this->chatMessages->clearThreadMessages($threadId, $user, $teamId);

        return Helper::jsonResponse(true, 'Messages cleared successfully.', 200, ['ok' => true]);
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
