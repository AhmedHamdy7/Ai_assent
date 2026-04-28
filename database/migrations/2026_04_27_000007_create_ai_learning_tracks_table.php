<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_learning_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_session_id')
                ->nullable()
                ->constrained('ai_sessions')
                ->nullOnDelete();
            $table->string('topic', 255);
            $table->text('goal')->nullable();
            $table->string('level', 32)->default('beginner');
            $table->string('status', 32)->default('active');
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedSmallInteger('daily_minutes')->default(30);
            $table->string('template', 64)->default('general');
            $table->text('summary')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['ai_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_learning_tracks');
    }
};
