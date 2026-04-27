<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_session_id')
                ->nullable()
                ->constrained('ai_sessions')
                ->nullOnDelete();
            $table->string('title');
            $table->text('details')->nullable();
            $table->string('status')->default('pending');
            $table->string('priority')->default('medium');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['ai_session_id', 'status']);
            $table->index(['ai_session_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tasks');
    }
};
