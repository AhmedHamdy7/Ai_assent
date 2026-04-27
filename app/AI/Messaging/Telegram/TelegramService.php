<?php

namespace App\AI\Messaging\Telegram;

use App\AI\Agent\BaseAgent;
use App\AI\Orchestration\Orchestrator;
use App\AI\Provider\AiMessage;
use App\AI\Provider\AiSession;
use App\AI\Provider\BaseProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private const BASE_URL = 'https://api.telegram.org';
    private const TELEGRAM_TEXT_LIMIT = 4096;

    public function __construct(
        private string $botToken,
        private ?string $webhookSecret,
        private int $timeout,
        private BaseProvider $provider,
        private BaseAgent $agent,
        private string $modelName,
        private int $historyLimit = 30,
    ) {}

    public function setWebhook(string $url, array $allowedUpdates = ['message', 'callback_query']): array
    {
        $payload = [
            'url' => $url,
            'allowed_updates' => $allowedUpdates,
        ];

        if (!empty($this->webhookSecret)) {
            $payload['secret_token'] = $this->webhookSecret;
        }

        return $this->call('setWebhook', $payload);
    }

    public function deleteWebhook(bool $dropPendingUpdates = false): array
    {
        return $this->call('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates,
        ]);
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo');
    }

    public function sendMessage(int|string $chatId, string $text, array $extra = []): array
    {
        $text = $this->truncateForTelegram($text);

        return $this->call('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $extra));
    }

    public function sendChatAction(int|string $chatId, string $action = 'typing'): array
    {
        return $this->call('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    public function handleUpdate(array $update): void
    {
        Log::info('telegram.update', ['update_id' => $update['update_id'] ?? null]);

        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (!is_array($message)) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? null;

        if ($chatId === null || !is_string($text) || trim($text) === '') {
            return;
        }

        $session = $this->resolveSession((string) $chatId, $message);

        AiMessage::user($text)
            ->forceFill(['ai_session_id' => $session->id])
            ->save();

        try {
            $this->sendChatAction($chatId, 'typing');
        } catch (\Throwable $e) {
            Log::warning('telegram.typing_failed', ['error' => $e->getMessage()]);
        }

        $reply = $this->runOrchestrator($session);

        if ($reply === '') {
            $reply = '(no response)';
        }

        AiMessage::assistant($reply)
            ->forceFill(['ai_session_id' => $session->id])
            ->save();

        $this->sendMessage($chatId, $reply);
    }

    public function verifySecret(?string $headerSecret): bool
    {
        if (empty($this->webhookSecret)) {
            return true;
        }

        return is_string($headerSecret) && hash_equals($this->webhookSecret, $headerSecret);
    }

    private function resolveSession(string $chatId, array $message): AiSession
    {
        $title = $message['chat']['title']
            ?? trim(($message['chat']['first_name'] ?? '') . ' ' . ($message['chat']['last_name'] ?? ''))
            ?: ('Telegram chat ' . $chatId);

        return AiSession::firstOrCreate(
            ['telegram_chat_id' => $chatId],
            ['title' => $title],
        );
    }

    private function runOrchestrator(AiSession $session): string
    {
        $messages = $session->messages()
            ->orderByDesc('id')
            ->limit($this->historyLimit)
            ->get()
            ->reverse()
            ->values()
            ->all();

        $orchestrator = new Orchestrator($this->provider, $this->agent, $this->modelName);

        return $orchestrator->askAi($messages);
    }

    private function truncateForTelegram(string $text): string
    {
        if (mb_strlen($text) <= self::TELEGRAM_TEXT_LIMIT) {
            return $text;
        }

        return mb_substr($text, 0, self::TELEGRAM_TEXT_LIMIT - 1) . '…';
    }

    private function call(string $method, array $payload = []): array
    {
        $response = Http::timeout($this->timeout)
            ->asJson()
            ->post(self::BASE_URL . "/bot{$this->botToken}/{$method}", $payload);

        $response->throw();

        return $response->json();
    }
}
