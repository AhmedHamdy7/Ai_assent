<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_message_id')->nullable()->after('content');
            $table->unsignedBigInteger('reply_to_telegram_message_id')->nullable()->after('telegram_message_id');

            $table->index(['ai_session_id', 'telegram_message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropIndex(['ai_session_id', 'telegram_message_id']);
            $table->dropColumn(['telegram_message_id', 'reply_to_telegram_message_id']);
        });
    }
};
