<?php

namespace App\AI\Provider;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiSession extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'title',
        'token',
        'compaction_count',
        'context_starts_after_message_id',
    ];

    protected $casts = [
        'compaction_count' => 'integer',
        'context_starts_after_message_id' => 'integer',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }
}
