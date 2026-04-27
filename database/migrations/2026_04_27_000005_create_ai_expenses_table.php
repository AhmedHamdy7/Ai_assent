<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_session_id')
                ->nullable()
                ->constrained('ai_sessions')
                ->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('category', 120);
            $table->text('note')->nullable();
            $table->timestamp('spent_at');
            $table->timestamps();

            $table->index(['ai_session_id', 'spent_at']);
            $table->index(['ai_session_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_expenses');
    }
};
