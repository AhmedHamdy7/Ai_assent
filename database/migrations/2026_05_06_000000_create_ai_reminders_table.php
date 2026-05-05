<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_session_id')
                ->nullable()
                ->constrained('ai_sessions')
                ->nullOnDelete();
            $table->string('telegram_chat_id');
            $table->text('message');
            $table->string('frequency')->default('once'); // once, daily, weekly
            $table->string('time_of_day')->nullable();    // HH:MM for daily/weekly
            $table->tinyInteger('day_of_week')->nullable(); // 0=Sun..6=Sat for weekly
            $table->timestamp('remind_at');
            $table->timestamp('last_sent_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'remind_at']);
            $table->index(['ai_session_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_reminders');
    }
};
