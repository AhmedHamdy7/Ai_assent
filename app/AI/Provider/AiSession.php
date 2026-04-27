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
    ];

    protected $casts = [
        'compaction_count' => 'integer',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }
}
