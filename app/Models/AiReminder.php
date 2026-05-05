<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReminder extends Model
{
    public const FREQUENCY_ONCE   = 'once';
    public const FREQUENCY_DAILY  = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';

    protected $fillable = [
        'ai_session_id',
        'telegram_chat_id',
        'message',
        'frequency',
        'time_of_day',
        'day_of_week',
        'remind_at',
        'last_sent_at',
        'is_active',
    ];

    protected $casts = [
        'ai_session_id' => 'integer',
        'day_of_week'   => 'integer',
        'remind_at'     => 'datetime',
        'last_sent_at'  => 'datetime',
        'is_active'     => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(\App\AI\Provider\AiSession::class, 'ai_session_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('remind_at', '<=', now());
    }

    public function scopeForSession(Builder $query, ?int $sessionId): Builder
    {
        return $sessionId === null
            ? $query->whereNull('ai_session_id')
            : $query->where('ai_session_id', $sessionId);
    }
}
