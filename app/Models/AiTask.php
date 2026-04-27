<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTask extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';

    protected $fillable = [
        'ai_session_id',
        'title',
        'details',
        'status',
        'priority',
        'due_at',
        'completed_at',
    ];

    protected $casts = [
        'ai_session_id' => 'integer',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
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
