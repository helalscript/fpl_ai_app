<?php

namespace App\Services\Fpl;

use App\Models\FplChatConversation;
use App\Models\FplChatMessage;
use App\Models\FplChatPromptSuggestion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FplChatMessageService
{
    public const DEFAULT_UI_PAGE_SIZE = 40;

    /**
     * When the user has an FPL team saved on their profile, chat and FPL context must use that entry ID only.
     */
    public function teamIdAllowedForUser(User $user, int $fplTeamId): bool
    {
        $saved = $user->fpl_team_id;

        if ($saved === null) {
            return true;
        }

        return (int) $saved === $fplTeamId;
    }

    /**
     * Active prompt chips for the chat UI (from database).
     *
     * @return array<int, array{id: int, body: string}>
     */
    public function activePromptSuggestions(): array
    {
        return FplChatPromptSuggestion::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'body'])
            ->map(fn (FplChatPromptSuggestion $s): array => [
                'id' => (int) $s->id,
                'body' => $s->body,
            ])
            ->all();
    }

    public function createConversation(User $user, int $fplTeamId): FplChatConversation
    {
        return FplChatConversation::query()->create([
            'user_id' => $user->id,
            'fpl_team_id' => $fplTeamId,
            'last_message_at' => null,
        ]);
    }

    /**
     * Conversation to open by default: last active thread with messages, else newest empty thread, else create one.
     */
    public function defaultConversationForTeam(User $user, int $fplTeamId): FplChatConversation
    {
        $base = FplChatConversation::query()
            ->where('user_id', $user->id)
            ->where('fpl_team_id', $fplTeamId);

        $withMessages = (clone $base)
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();

        if ($withMessages !== null) {
            return $withMessages;
        }

        $any = (clone $base)->orderByDesc('id')->first();

        if ($any !== null) {
            return $any;
        }

        return $this->createConversation($user, $fplTeamId);
    }

    public function findConversationForUser(int $conversationId, User $user, int $fplTeamId): ?FplChatConversation
    {
        return FplChatConversation::query()
            ->where('id', $conversationId)
            ->where('user_id', $user->id)
            ->where('fpl_team_id', $fplTeamId)
            ->first();
    }

    /**
     * @return array<int, array{id: int, last_message_at: string|null, created_at: string}>
     */
    public function listConversationsSummary(int $userId, int $fplTeamId, int $limit = 25): array
    {
        return FplChatConversation::query()
            ->where('user_id', $userId)
            ->where('fpl_team_id', $fplTeamId)
            ->orderByRaw('case when last_message_at is null then 1 else 0 end')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 50)))
            ->get(['id', 'last_message_at', 'created_at'])
            ->map(fn (FplChatConversation $c): array => [
                'id' => (int) $c->id,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
                'created_at' => $c->created_at?->toIso8601String() ?? '',
            ])
            ->all();
    }

    /**
     * Whether this user already sent at least one chat message today in this thread (app timezone).
     */
    public function hasUserMessageToday(
        int $userId,
        int $fplTeamId,
        int $conversationId,
        ?CarbonInterface $now = null,
    ): bool {
        $now ??= now();

        return FplChatMessage::query()
            ->where('user_id', $userId)
            ->where('fpl_team_id', $fplTeamId)
            ->where('fpl_chat_conversation_id', $conversationId)
            ->where('role', FplChatMessage::ROLE_USER)
            ->whereDate('created_at', $now->toDateString())
            ->exists();
    }

    /**
     * Persist a chat line and bump thread activity.
     */
    public function storeMessage(
        User $user,
        int $fplTeamId,
        int $conversationId,
        string $role,
        string $content,
    ): FplChatMessage {
        $message = FplChatMessage::query()->create([
            'user_id' => $user->id,
            'fpl_team_id' => $fplTeamId,
            'fpl_chat_conversation_id' => $conversationId,
            'role' => $role,
            'content' => $content,
        ]);

        $now = now();
        FplChatConversation::query()->where('id', $conversationId)->update([
            'last_message_at' => $now,
            'updated_at' => $now,
        ]);

        return $message;
    }

    /**
     * Recover assistant text from the streamed response. OpenAI and other gateways usually emit
     * {@see TextDelta} events; reasoning-capable models may emit {@see ReasoningDelta} without text deltas.
     */
    private function extractStreamedAssistantBody(StreamedAgentResponse $streamed): string
    {
        $direct = trim((string) ($streamed->text ?? ''));

        if ($direct !== '') {
            return $direct;
        }

        $events = $streamed->events;

        $fromText = trim(TextDelta::combine($events));

        if ($fromText !== '') {
            return $fromText;
        }

        return trim(
            $events
                ->whereInstanceOf(ReasoningDelta::class)
                ->map(fn (ReasoningDelta $e): string => $e->delta)
                ->join(''),
        );
    }

    /**
     * Persist the assistant reply after a streamed agent response completes.
     *
     * Tries, in order: response text, {@see TextDelta} events, then {@see ReasoningDelta} events
     * (some models stream reasoning without standard text deltas).
     */
    public function persistAssistantFromStreamResponse(
        User $user,
        int $fplTeamId,
        int $conversationId,
        StreamedAgentResponse $streamed,
    ): ?FplChatMessage {
        $text = $this->extractStreamedAssistantBody($streamed);

        if ($text === '') {
            Log::warning('FplChatMessageService: streamed assistant response had no extractable text; skipping DB row.', [
                'user_id' => $user->id,
                'fpl_team_id' => $fplTeamId,
                'fpl_chat_conversation_id' => $conversationId,
                'invocation_id' => $streamed->invocationId,
                'event_class_counts' => $streamed->events->countBy(fn (object $e): string => $e::class)->all(),
            ]);

            return null;
        }

        try {
            return $this->storeMessage(
                $user,
                $fplTeamId,
                $conversationId,
                FplChatMessage::ROLE_ASSISTANT,
                $text,
            );
        } catch (Throwable $e) {
            Log::error('FplChatMessageService: failed to persist assistant message: '.$e->getMessage(), [
                'user_id' => $user->id,
                'fpl_team_id' => $fplTeamId,
                'fpl_chat_conversation_id' => $conversationId,
            ]);

            return null;
        }
    }

    /**
     * Register persistence of the assistant reply once the HTTP stream has finished iterating.
     * Web and API streaming routes must use this so behaviour stays identical.
     */
    public function persistAssistantAfterStreamCompletes(
        StreamableAgentResponse $streamResponse,
        User $user,
        int $fplTeamId,
        int $conversationId,
    ): void {
        $streamResponse->then(function (StreamedAgentResponse $streamed) use ($user, $fplTeamId, $conversationId): void {
            $this->persistAssistantFromStreamResponse($user, $fplTeamId, $conversationId, $streamed);
        });
    }

    /**
     * Stream SSE to the client while persisting the assistant reply incrementally.
     *
     * This is the safest approach when users refresh/close mid-stream: we create an assistant "draft"
     * row immediately, then append streamed deltas to it as they arrive.
     */
    public function streamSseAndPersistAssistantDraft(
        StreamableAgentResponse $aiStream,
        User $user,
        int $fplTeamId,
        int $conversationId,
    ): StreamedResponse {
        $assistantDraft = $this->storeMessage($user, $fplTeamId, $conversationId, FplChatMessage::ROLE_ASSISTANT, '');
        $buffer = '';
        $lastFlushAt = microtime(true);

        return response()->stream(function () use ($aiStream, $assistantDraft, &$buffer, &$lastFlushAt): void {
            foreach ($aiStream as $event) {
                // Persist progressively so even aborts keep most content.
                if ($event instanceof TextDelta) {
                    $buffer .= $event->delta;
                } elseif ($event instanceof ReasoningDelta) {
                    $buffer .= $event->delta;
                }

                $now = microtime(true);

                if ($buffer !== '' && ($now - $lastFlushAt) >= 0.25) {
                    $assistantDraft->forceFill(['content' => $buffer])->save();
                    $lastFlushAt = $now;
                }

                if ($event instanceof StreamEvent || method_exists($event, '__toString')) {
                    echo 'data: '.((string) $event)."\n\n";
                    @ob_flush();
                    @flush();
                }
            }

            if ($buffer !== '') {
                $assistantDraft->forceFill(['content' => $buffer])->save();
            }

            echo "data: [DONE]\n\n";
            @ob_flush();
            @flush();
        }, headers: ['Content-Type' => 'text/event-stream']);
    }

    /**
     * Recent messages for the AI prompt (chronological), excluding the current request body.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function historyForPrompt(int $userId, int $fplTeamId, int $conversationId, int $limit = 20): array
    {
        /** @var Collection<int, FplChatMessage> $rows */
        $rows = FplChatMessage::query()
            ->where('user_id', $userId)
            ->where('fpl_team_id', $fplTeamId)
            ->where('fpl_chat_conversation_id', $conversationId)
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 40)))
            ->get()
            ->reverse()
            ->values();

        return $rows
            ->map(fn (FplChatMessage $m): array => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->all();
    }

    /**
     * @return array{id: int, role: string, content: string, created_at: string}
     */
    protected function mapMessageRow(FplChatMessage $m): array
    {
        return [
            'id' => (int) $m->id,
            'role' => $m->role,
            'content' => $m->content,
            'created_at' => $m->created_at?->toIso8601String() ?? '',
        ];
    }


    public function messagesPageForUi(int $userId,int $fplTeamId,int $conversationId,int $perPage = 10,?int $beforeId = null,?int $afterId = null,)
    {
        $perPage = max(1, min($perPage, 100));

        $base = FplChatMessage::query()
            ->where('user_id', $userId)
            ->where('fpl_team_id', $fplTeamId)
            ->where('fpl_chat_conversation_id', $conversationId)
            // ->orderByDesc('id')
            ->paginate($perPage);

        if ($beforeId !== null && $beforeId > 0) {
            return $base->where('id', '<', $beforeId)->paginate($perPage);
        }

        if ($afterId !== null && $afterId > 0) {
            return $base->where('id', '>', $afterId)->paginate($perPage);
        }

        return $base;

    }

    /**
     * Delete all messages in a thread (conversation row is kept).
     */
    public function clearThreadMessages(int $conversationId, User $user, int $fplTeamId): int
    {
        $conversation = $this->findConversationForUser($conversationId, $user, $fplTeamId);

        if ($conversation === null) {
            return 0;
        }

        $deleted = FplChatMessage::query()
            ->where('fpl_chat_conversation_id', $conversationId)
            ->delete();

        $conversation->update([
            'last_message_at' => null,
        ]);

        return $deleted;
    }
}
