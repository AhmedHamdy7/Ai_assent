<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_learning_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')
                ->constrained('ai_learning_tracks')
                ->cascadeOnDelete();
            $table->foreignId('lesson_id')
                ->constrained('ai_learning_lessons')
                ->cascadeOnDelete();
            $table->json('answers')->nullable();
            $table->unsignedTinyInteger('score_percentage')->default(0);
            $table->boolean('passed')->default(false);
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->index(['track_id', 'created_at']);
            $table->index(['lesson_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_learning_quiz_attempts');
    }
};
