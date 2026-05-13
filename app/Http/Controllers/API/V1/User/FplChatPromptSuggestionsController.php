<?php

namespace App\Http\Controllers\API\V1\User;

use App\Helpers\Helper;
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
        return Helper::jsonResponse(true, 'Prompt suggestions fetched successfully.', 200, [
            'suggestions' => $this->chatMessages->activePromptSuggestions(),
        ]);
    }
}
