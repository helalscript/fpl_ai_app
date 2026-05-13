<?php

namespace App\Http\Controllers\Web;

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
            return response()->json(['message' => 'Provide either before_id or after_id, not both.'], 422);
        }

        $validated = $request->validate([
            'team_id' => 'sometimes|integer|min:1',
            'thread_id' => 'required|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'before_id' => 'sometimes|integer|min:1',
            'after_id' => 'sometimes|integer|min:1',
        ]);

        $user = $request->user();
        $teamId = $this->resolveTeamId($request, $user);

        if ($teamId === null) {
            return response()->json(['message' => 'team_id is required.'], 422);
        }

        if (! $this->chatMessages->teamIdAllowedForUser($user, $teamId)) {
            return response()->json([
                'message' => 'The team_id must match your FPL entry ID saved on your profile.',
            ], 422);
        }

        $threadId = (int) $validated['thread_id'];
        $thread = $this->chatMessages->findConversationForUser($threadId, $user, $teamId);

        if ($thread === null) {
            return response()->json(['message' => 'Invalid thread_id for this team.'], 422);
        }

        $perPage = (int) ($validated['per_page'] ?? FplChatMessageService::DEFAULT_UI_PAGE_SIZE);
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

        return response()->json($page);
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

        $user = $request->user();
        $teamId = $this->resolveTeamId($request, $user);

        if ($teamId === null) {
            return response()->json(['message' => 'team_id is required.'], 422);
        }

        if (! $this->chatMessages->teamIdAllowedForUser($user, $teamId)) {
            return response()->json([
                'message' => 'The team_id must match your FPL entry ID saved on your profile.',
            ], 422);
        }

        $threadId = (int) $validated['thread_id'];
        $thread = $this->chatMessages->findConversationForUser($threadId, $user, $teamId);

        if ($thread === null) {
            return response()->json(['message' => 'Invalid thread_id for this team.'], 422);
        }

        $this->chatMessages->clearThreadMessages($threadId, $user, $teamId);

        return response()->json(['ok' => true]);
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
