<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Fpl\FplChatMessageService;
use Illuminate\Http\JsonResponse;

class FplChatPromptSuggestionsController extends Controller
{
    public function __construct(protected FplChatMessageService $chatMessages) {}

    /**
     * List active default question chips (database-driven).
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'suggestions' => $this->chatMessages->activePromptSuggestions(),
        ]);
    }
}
