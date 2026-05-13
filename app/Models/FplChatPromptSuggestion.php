<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FplChatPromptSuggestion extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'body',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
