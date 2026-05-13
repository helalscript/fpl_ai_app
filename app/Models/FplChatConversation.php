<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FplChatConversation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'fpl_team_id',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fpl_team_id' => 'integer',
            'last_message_at' => 'datetime',
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
     * @return HasMany<FplChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(FplChatMessage::class, 'fpl_chat_conversation_id');
    }
}
