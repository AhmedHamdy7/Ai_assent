<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('context_starts_after_message_id')
                ->nullable()
                ->after('compaction_count');

            $table->index('context_starts_after_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_sessions', function (Blueprint $table) {
            $table->dropIndex(['context_starts_after_message_id']);
            $table->dropColumn('context_starts_after_message_id');
        });
    }
};
