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
        Schema::table('fpl_chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('fpl_chat_conversation_id')->nullable()->after('fpl_team_id');
        });

        $pairs = DB::table('fpl_chat_messages')
            ->select('user_id', 'fpl_team_id')
            ->distinct()
            ->get();

        $now = now();

        foreach ($pairs as $pair) {
            $conversationId = DB::table('fpl_chat_conversations')->insertGetId([
                'user_id' => $pair->user_id,
                'fpl_team_id' => $pair->fpl_team_id,
                'last_message_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('fpl_chat_messages')
                ->where('user_id', $pair->user_id)
                ->where('fpl_team_id', $pair->fpl_team_id)
                ->update(['fpl_chat_conversation_id' => $conversationId]);

            $lastAt = DB::table('fpl_chat_messages')
                ->where('fpl_chat_conversation_id', $conversationId)
                ->max('created_at');

            if ($lastAt !== null) {
                DB::table('fpl_chat_conversations')
                    ->where('id', $conversationId)
                    ->update(['last_message_at' => $lastAt]);
            }
        }

        Schema::table('fpl_chat_messages', function (Blueprint $table) {
            $table->foreign('fpl_chat_conversation_id')
                ->references('id')
                ->on('fpl_chat_conversations')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fpl_chat_messages', function (Blueprint $table) {
            $table->dropForeign(['fpl_chat_conversation_id']);
            $table->dropColumn('fpl_chat_conversation_id');
        });
    }
};
