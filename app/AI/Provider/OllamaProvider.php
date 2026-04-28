<?php

namespace App\AI\Provider;

use App\AI\Agent\BaseAgent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post($this->apiUrl, $body);

            $response->throw();
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                "AI provider connection failed for {$this->apiUrl}: {$e->getMessage()}",
                previous: $e
            );
        } catch (RequestException $e) {
            $bodyText = trim((string) $e->response?->body());
            $details = $bodyText !== '' ? $bodyText : $e->getMessage();

            throw new RuntimeException(
                "AI provider request failed for {$this->apiUrl}: {$details}",
                previous: $e
            );
        }

        return AiModel::fromOllama($response->json());
    }
}
