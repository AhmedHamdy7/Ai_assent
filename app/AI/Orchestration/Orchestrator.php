<?php

namespace App\AI\Orchestration;

use App\AI\Agent\BaseAgent;
use App\AI\Provider\AiMessage;
use App\AI\Provider\BaseProvider;

class Orchestrator
{
    public function __construct(
        private BaseProvider $provider,
        private BaseAgent $agent,
        private string $modelName,
    ) {}

    /**
     * @param AiMessage[] $messages
     */
    public function askAi(array $messages): string
    {
        $toolsByName = [];
        foreach ($this->agent->getTools() as $tool) {
            $toolsByName[$tool->getName()] = $tool;
        }

        do {
            $response = $this->provider->askAiFuture($this->agent, $this->modelName, $messages);

            $messages[] = AiMessage::fromAiModel($response);

            foreach ($response->toolCalls as $toolCall) {
                $tool = $toolsByName[$toolCall->name] ?? null;
                if ($tool === null) {
                    continue;
                }
                $result = $tool->execute($toolCall->arguments);
                $messages[] = AiMessage::tool($toolCall->name, (string) $result, $toolCall->id);
            }
        } while ($response->hasToolCalls());

        return $response->content;
    }
}
