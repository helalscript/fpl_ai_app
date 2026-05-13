<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FplChatMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'fpl_team_id',
        'fpl_chat_conversation_id',
        'role',
        'content',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fpl_team_id' => 'integer',
            'fpl_chat_conversation_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FplChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(FplChatConversation::class, 'fpl_chat_conversation_id');
    }
}
