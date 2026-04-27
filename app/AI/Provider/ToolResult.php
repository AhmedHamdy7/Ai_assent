<?php

namespace App\AI\Provider;

class ToolResult
{
    public function __construct(
        public readonly array $payload = [],
        public readonly ?string $handoffAgentKey = null,
        public readonly ?string $executionTenantId = null,
        public readonly ?string $executionMode = null,
        public readonly array $sessionStatePatch = [],
        public readonly array $outboundMessages = [],
    ) {}

    public static function fromPayload(array $payload, array $outboundMessages = []): self
    {
        return new self(payload: $payload, outboundMessages: $outboundMessages);
    }

    public static function handoff(string $agentKey, array $payload = [], array $outboundMessages = []): self
    {
        return new self(payload: $payload, handoffAgentKey: $agentKey, outboundMessages: $outboundMessages);
    }

    public function __toString(): string
    {
        $message = $this->payload['message'] ?? null;

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return $this->toJson();
    }

    public function toJson(): string
    {
        return json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
