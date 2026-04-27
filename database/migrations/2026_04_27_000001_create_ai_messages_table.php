<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_session_id')
                ->constrained('ai_sessions')
                ->cascadeOnDelete();
            $table->string('role');
            $table->longText('content')->nullable();
            $table->json('tool_calls')->nullable();
            $table->string('tool_name')->nullable();
            $table->string('tool_call_id')->nullable();
            $table->unsignedInteger('context_origin_turn')->nullable();
            $table->unsignedInteger('context_expires_after_turns')->nullable();
            $table->timestamps();

            $table->index(['ai_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
