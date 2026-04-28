<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiLearningTrack extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'ai_session_id',
        'topic',
        'goal',
        'level',
        'status',
        'duration_days',
        'daily_minutes',
        'template',
        'summary',
        'started_at',
        'completed_at',
        'last_activity_at',
    ];

    protected $casts = [
        'ai_session_id' => 'integer',
        'duration_days' => 'integer',
        'daily_minutes' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(\App\AI\Provider\AiSession::class, 'ai_session_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(AiLearningLesson::class, 'track_id');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(AiLearningQuizAttempt::class, 'track_id');
    }
}
