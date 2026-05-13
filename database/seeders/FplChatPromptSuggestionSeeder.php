<?php

namespace Database\Seeders;

use App\Models\FplChatPromptSuggestion;
use Illuminate\Database\Seeder;

class FplChatPromptSuggestionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['body' => 'Who should I captain?', 'sort_order' => 1],
            ['body' => 'Is a -4 hit worth it?', 'sort_order' => 2],
            ['body' => 'Analyze my bench', 'sort_order' => 3],
            ['body' => 'Best transfer targets under £7.0m?', 'sort_order' => 4],
            ['body' => 'Should I use my Free Hit this week?', 'sort_order' => 5],
        ];

        foreach ($rows as $row) {
            FplChatPromptSuggestion::query()->updateOrCreate(
                ['body' => $row['body']],
                [
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
