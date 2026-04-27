<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_sessions', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_sessions', function (Blueprint $table) {
            $table->dropUnique(['telegram_chat_id']);
            $table->dropColumn('telegram_chat_id');
        });
    }
};
