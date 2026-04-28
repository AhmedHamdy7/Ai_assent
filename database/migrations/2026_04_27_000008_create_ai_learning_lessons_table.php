<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_learning_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')
                ->constrained('ai_learning_tracks')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->string('title', 255);
            $table->text('objective');
            $table->longText('lesson_body');
            $table->text('practice_task')->nullable();
            $table->text('resource_hint')->nullable();
            $table->json('quiz')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedTinyInteger('last_score')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['track_id', 'day_number']);
            $table->index(['track_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_learning_lessons');
    }
};
