<?php

namespace App\AI\Orchestration;

use App\AI\Agent\BaseAgent;
use App\AI\Provider\AiMessage;
use App\AI\Provider\BaseProvider;
use App\AI\Provider\ToolResult;
use App\AI\SpeechToText\SpeechToTextProvider;
use RuntimeException;
use Throwable;

class Orchestrator
{
    public function __construct(
        private BaseProvider $provider,
        private BaseAgent $agent,
        private string $modelName,
        private int $maxToolRounds = 8,
        private ?SpeechToTextProvider $speechToTextProvider = null,
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

    public function transcribeAudioBinary(
        string $binaryAudio,
        string $filename = 'voice.ogg',
        string $mimeType = 'audio/ogg'
    ): string {
        if ($this->speechToTextProvider === null) {
            throw new RuntimeException('Speech-to-text is not enabled');
        }

        if ($binaryAudio === '') {
            throw new RuntimeException('Downloaded voice file is empty');
        }

        $temporaryPath = $this->temporaryAudioPath($filename);

        try {
            $written = file_put_contents($temporaryPath, $binaryAudio);

            if ($written === false) {
                throw new RuntimeException("Unable to write temporary audio file [{$temporaryPath}].");
            }

            return $this->speechToTextProvider->transcribe($temporaryPath, $mimeType);
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function temporaryAudioPath(string $filename): string
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension ?? '') ?: 'ogg';
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $baseName ?: 'voice-input') ?: 'voice-input';

        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $baseName
            . '-'
            . bin2hex(random_bytes(8))
            . '.'
            . $extension;
    }
}
