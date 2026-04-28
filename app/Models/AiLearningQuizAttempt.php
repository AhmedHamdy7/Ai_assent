<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiLearningQuizAttempt extends Model
{
    protected $fillable = [
        'track_id',
        'lesson_id',
        'answers',
        'score_percentage',
        'passed',
        'feedback',
    ];

    protected $casts = [
        'track_id' => 'integer',
        'lesson_id' => 'integer',
        'answers' => 'array',
        'score_percentage' => 'integer',
        'passed' => 'boolean',
    ];

    public function track(): BelongsTo
    {
        return $this->belongsTo(AiLearningTrack::class, 'track_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(AiLearningLesson::class, 'lesson_id');
    }
}
