<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiLearningLesson extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'track_id',
        'day_number',
        'title',
        'objective',
        'lesson_body',
        'practice_task',
        'resource_hint',
        'quiz',
        'status',
        'completed_at',
        'last_score',
    ];

    protected $casts = [
        'track_id' => 'integer',
        'day_number' => 'integer',
        'quiz' => 'array',
        'completed_at' => 'datetime',
        'last_score' => 'integer',
    ];

    public function track(): BelongsTo
    {
        return $this->belongsTo(AiLearningTrack::class, 'track_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AiLearningQuizAttempt::class, 'lesson_id');
    }
}
