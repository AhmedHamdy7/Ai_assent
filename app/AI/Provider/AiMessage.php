<?php

namespace App\AI\Provider;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    protected $fillable = [
        'ai_session_id',
        'role',
        'content',
        'tool_calls',
        'tool_name',
        'tool_call_id',
        'context_origin_turn',
        'context_expires_after_turns',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'context_origin_turn' => 'integer',
        'context_expires_after_turns' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiSession::class);
    }

    public static function system(string $content): self
    {
        return new self([
            'role' => AiRoleEnum::System->value,
            'content' => $content,
        ]);
    }

    public static function user(string $content): self
    {
        return new self([
            'role' => AiRoleEnum::User->value,
            'content' => $content,
        ]);
    }

    /**
     * @param AiToolCall[] $toolCalls
     */
    public static function assistant(string $content, array $toolCalls = []): self
    {
        return new self([
            'role' => AiRoleEnum::Assistant->value,
            'content' => $content,
            'tool_calls' => !empty($toolCalls) ? array_map(fn (AiToolCall $tc) => [
                'id' => $tc->id,
                'name' => $tc->name,
                'arguments' => $tc->arguments,
            ], $toolCalls) : null,
        ]);
    }

    public static function tool(string $toolName, string $content, ?string $toolCallId = null): self
    {
        return new self([
            'role' => AiRoleEnum::Tool->value,
            'content' => $content,
            'tool_name' => $toolName,
            'tool_call_id' => $toolCallId,
        ]);
    }

    public static function fromAiModel(AiModel $model): self
    {
        return self::assistant($model->content, $model->toolCalls);
    }

    public function retainInContextFromTurn(int $turn, int $expiresAfterTurns): self
    {
        $this->context_origin_turn = $turn;
        $this->context_expires_after_turns = max(0, $expiresAfterTurns);

        return $this;
    }

    public function shouldAppearInContextForTurn(int $currentTurn): bool
    {
        if ($this->context_origin_turn === null) {
            return true;
        }

        return ($currentTurn - $this->context_origin_turn) <= (int) ($this->context_expires_after_turns ?? 0);
    }

    public function toOllama(): array
    {
        $content = $this->content;
        if (is_array($content) || is_object($content)) {
            $content = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $message = [
            'role' => (string) $this->role,
            'content' => $content ?? '',
        ];

        if (!empty($this->tool_calls) && is_array($this->tool_calls)) {
            $message['tool_calls'] = array_values(array_filter(array_map(
                function (array $tc): ?array {
                    if (empty($tc['name'])) {
                        return null;
                    }

                    return [
                        'function' => [
                            'name' => $tc['name'],
                            'arguments' => (object) ($tc['arguments'] ?? []),
                        ],
                    ];
                },
                $this->tool_calls
            )));
        }

        return $message;
    }

    public function toOpenAI(): array
    {
        $content = $this->normalizedContent();
        $role = (string) $this->role;

        $message = [
            'role' => $role,
            'content' => $content,
        ];

        if ($role === AiRoleEnum::Assistant->value && ! empty($this->tool_calls) && is_array($this->tool_calls)) {
            $message['tool_calls'] = array_values(array_filter(array_map(
                function (array $tc): ?array {
                    if (empty($tc['name'])) {
                        return null;
                    }

                    return [
                        'id' => isset($tc['id']) ? (string) $tc['id'] : null,
                        'type' => 'function',
                        'function' => [
                            'name' => (string) $tc['name'],
                            'arguments' => json_encode($tc['arguments'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ],
                    ];
                },
                $this->tool_calls
            )));

            if ($message['tool_calls'] !== [] && $message['content'] === '') {
                $message['content'] = null;
            }
        }

        if ($role === AiRoleEnum::Tool->value) {
            $message['tool_call_id'] = $this->tool_call_id;
        }

        return $message;
    }

    private function normalizedContent(): string
    {
        $content = $this->content;

        if (is_array($content) || is_object($content)) {
            $content = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string) ($content ?? '');
    }
}
