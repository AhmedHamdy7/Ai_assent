<?php

namespace App\AI\Orchestration;

use App\AI\Agent\BaseAgent;
use App\AI\Provider\AiMessage;
use App\AI\Provider\BaseProvider;
use App\AI\Provider\ToolResult;
use Throwable;

class Orchestrator
{
    public function __construct(
        private BaseProvider $provider,
        private BaseAgent $agent,
        private string $modelName,
        private int $maxToolRounds = 8,
    ) {}

    /**
     * @param AiMessage[] $messages
     */
    public function askAi(array $messages, array $toolContext = []): string
    {
        $toolsByName = [];
        foreach ($this->agent->getTools() as $tool) {
            $toolsByName[$tool->getName()] = $tool;
        }

        $round = 0;

        do {
            $round++;
            $response = $this->provider->askAiFuture($this->agent, $this->modelName, $messages);

            $messages[] = AiMessage::fromAiModel($response);

            foreach ($response->toolCalls as $toolCall) {
                $tool = $toolsByName[$toolCall->name] ?? null;
                if ($tool === null) {
                    $messages[] = AiMessage::tool(
                        $toolCall->name,
                        (string) ToolResult::fromPayload([
                            'ok' => false,
                            'message' => "Tool '{$toolCall->name}' is not registered.",
                        ]),
                        $toolCall->id
                    );

                    continue;
                }

                try {
                    $result = $tool->execute($toolCall->arguments, $toolContext);
                } catch (Throwable $e) {
                    $result = ToolResult::fromPayload([
                        'ok' => false,
                        'message' => "Tool '{$toolCall->name}' failed: {$e->getMessage()}",
                    ]);
                }

                $messages[] = AiMessage::tool($toolCall->name, (string) $result, $toolCall->id);
            }

            if ($response->hasToolCalls() && $round >= $this->maxToolRounds) {
                return $response->content !== ''
                    ? $response->content
                    : 'I stopped after too many tool calls. Please try a narrower request.';
            }
        } while ($response->hasToolCalls());

        return $response->content;
    }
}
