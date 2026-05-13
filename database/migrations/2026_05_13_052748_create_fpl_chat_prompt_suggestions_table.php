<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fpl_chat_prompt_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('body');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['Who should I captain?', 1],
            ['Is a -4 hit worth it?', 2],
            ['Analyze my bench', 3],
            ['Best transfer targets under £7.0m?', 4],
            ['Should I use my Free Hit this week?', 5],
        ];

        foreach ($rows as [$body, $order]) {
            DB::table('fpl_chat_prompt_suggestions')->insert([
                'body' => $body,
                'sort_order' => $order,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fpl_chat_prompt_suggestions');
    }
};
