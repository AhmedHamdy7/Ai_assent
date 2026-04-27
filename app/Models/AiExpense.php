<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiExpense extends Model
{
    protected $fillable = [
        'ai_session_id',
        'amount',
        'category',
        'note',
        'spent_at',
    ];

    protected $casts = [
        'ai_session_id' => 'integer',
        'amount' => 'decimal:2',
        'spent_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(\App\AI\Provider\AiSession::class, 'ai_session_id');
    }

    public function scopeForSession(Builder $query, ?int $sessionId): Builder
    {
        return $sessionId === null
            ? $query->whereNull('ai_session_id')
            : $query->where('ai_session_id', $sessionId);
    }
}
