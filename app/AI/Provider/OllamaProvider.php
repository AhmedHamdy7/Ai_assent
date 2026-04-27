<?php

namespace App\AI\Provider;

use App\AI\Agent\BaseAgent;
use Illuminate\Support\Facades\Http;

class OllamaProvider extends BaseProvider
{
    public function __construct(
        private string $apiKey,
        private string $apiUrl,
        int $timeout,
    ) {
        parent::__construct($timeout);
    }

    public function askAiFuture(BaseAgent $agent, string $modelName, array $messages): AiModel
    {
        $instruction = $agent->getInstruction();

        if (!empty($agent->getTools())) {
            $instruction .= "\n\n## Critical Rule\nYou MUST call the appropriate tool before responding. Never claim to have completed an action, retrieved data, or saved anything without actually calling the tool first. If you have not called a tool, you have not done the action — do not pretend otherwise.";
        }

        $ollamaMessages = [
            ['role' => 'system', 'content' => $instruction],
        ];

        foreach ($messages as $message) {
            $ollamaMessages[] = $message->toOllama();
        }

        $body = [
            'model' => $modelName,
            'messages' => $ollamaMessages,
            'stream' => false,
        ];

        $tools = $agent->getTools();
        if (!empty($tools)) {
            $body['tools'] = array_map(fn($tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'parameters' => $tool->getParameters(),
                ],
            ], $tools);
        }

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->post($this->apiUrl, $body);

        $response->throw();

        return AiModel::fromOllama($response->json());
    }
}
