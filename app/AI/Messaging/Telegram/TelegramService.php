<?php

namespace App\AI\Messaging\Telegram;

use App\AI\Agent\BaseAgent;
use App\AI\Orchestration\Orchestrator;
use App\AI\Provider\AiMessage;
use App\AI\Provider\AiRoleEnum;
use App\AI\Provider\AiSession;
use App\AI\Provider\BaseProvider;
use RuntimeException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private const BASE_URL = 'https://api.telegram.org';
    private const TELEGRAM_TEXT_LIMIT = 4096;
    private const THINKING_TEXT = 'thinking...';

    public function __construct(
        private string $botToken,
        private ?string $webhookSecret,
        private int $timeout,
        private ?TelegramVoiceTranscriber $voiceTranscriber,
        private BaseProvider $provider,
        private BaseAgent $agent,
        private string $modelName,
        private int $historyLimit = 30,
    ) {}

    public function setWebhook(string $url, array $allowedUpdates = ['message', 'edited_message', 'callback_query']): array
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

    public function editMessageText(int|string $chatId, int $messageId, string $text, array $extra = []): array
    {
        $text = $this->truncateForTelegram($text);

        return $this->call('editMessageText', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ], $extra));
    }

    public function deleteMessage(int|string $chatId, int $messageId): array
    {
        return $this->call('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
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
        $incomingMessageId = isset($message['message_id']) ? (int) $message['message_id'] : null;
        $replyToMessageId = isset($message['reply_to_message']['message_id']) ? (int) $message['reply_to_message']['message_id'] : null;
        $isEditedMessage = isset($update['edited_message']);
        $hasVoicePayload = is_array($message['voice'] ?? null) || is_array($message['audio'] ?? null);

        if ($chatId === null || $incomingMessageId === null) {
            return;
        }

        if (!is_string($text) || trim($text) === '') {
            if ($hasVoicePayload) {
                try {
                    $text = $this->transcribeIncomingVoice($message);
                } catch (\Throwable $e) {
                    $userMessage = $this->resolveVoiceFailureUserMessage($e);

                    Log::warning('telegram.voice_transcription_failed', [
                        'chat_id' => $chatId,
                        'message_id' => $incomingMessageId,
                        'error' => $e->getMessage(),
                        'user_message' => $userMessage,
                    ]);

                    try {
                        $this->sendMessage($chatId, $userMessage);
                    } catch (\Throwable $sendError) {
                        Log::warning('telegram.voice_transcription_error_message_failed', [
                            'chat_id' => $chatId,
                            'message_id' => $incomingMessageId,
                            'error' => $sendError->getMessage(),
                        ]);
                    }

                    return;
                }
            }
        }

        if (!is_string($text) || trim($text) === '') {
            return;
        }

        $session = $this->resolveSession((string) $chatId, $message);

        if ($isEditedMessage) {
            $this->handleEditedMessage($session, $chatId, $incomingMessageId, $text);

            return;
        }

        $this->handleNewMessage($session, $chatId, $incomingMessageId, $replyToMessageId, $text);
    }

    public function verifySecret(?string $headerSecret): bool
    {
        if (empty($this->webhookSecret)) {
            return true;
        }

        return is_string($headerSecret) && hash_equals($this->webhookSecret, $headerSecret);
    }

    private function handleNewMessage(AiSession $session, int|string $chatId, int $incomingMessageId, ?int $replyToMessageId, string $text): void
    {
        if ($replyToMessageId !== null) {
            $anchor = $session->messages()
                ->where('telegram_message_id', $replyToMessageId)
                ->orderByDesc('id')
                ->first();

            if ($anchor !== null) {
                $this->pruneSessionAfter($session, $chatId, (int) $anchor->id, null, $replyToMessageId);
            }
        }

        AiMessage::user($text)
            ->forceFill([
                'ai_session_id' => $session->id,
                'telegram_message_id' => $incomingMessageId,
                'reply_to_telegram_message_id' => $replyToMessageId,
            ])
            ->save();

        $thinkingMessageId = $this->sendThinkingPlaceholder($chatId);
        try {
            $reply = $this->replyAndPublish($session, $chatId, $thinkingMessageId);
        } catch (\Throwable $e) {
            Log::error('telegram.reply_failed', [
                'chat_id' => $chatId,
                'message_id' => $incomingMessageId,
                'error' => $e->getMessage(),
            ]);

            $reply = 'حصل خطأ أثناء تنفيذ الطلب. جرّب تاني.';
            if ($thinkingMessageId !== null) {
                try {
                    $this->editMessageText($chatId, $thinkingMessageId, $reply);
                } catch (\Throwable) {
                    $this->sendMessage($chatId, $reply);
                }
            } else {
                $this->sendMessage($chatId, $reply);
            }
        }

        AiMessage::assistant($reply)
            ->forceFill([
                'ai_session_id' => $session->id,
                'telegram_message_id' => $thinkingMessageId,
                'reply_to_telegram_message_id' => $incomingMessageId,
            ])
            ->save();
    }

    private function handleEditedMessage(AiSession $session, int|string $chatId, int $incomingMessageId, string $text): void
    {
        $editedUserMessage = $session->messages()
            ->where('role', AiRoleEnum::User->value)
            ->where('telegram_message_id', $incomingMessageId)
            ->orderByDesc('id')
            ->first();

        if ($editedUserMessage === null) {
            $this->handleNewMessage($session, $chatId, $incomingMessageId, null, $text);

            return;
        }

        $editedUserMessage->content = $text;
        $editedUserMessage->save();

        $this->pruneSessionAfter($session, $chatId, (int) $editedUserMessage->id, null, $incomingMessageId);
        $thinkingMessageId = $this->sendThinkingPlaceholder($chatId);
        try {
            $reply = $this->replyAndPublish($session, $chatId, $thinkingMessageId);
        } catch (\Throwable $e) {
            Log::error('telegram.reply_failed', [
                'chat_id' => $chatId,
                'message_id' => $incomingMessageId,
                'error' => $e->getMessage(),
            ]);

            $reply = 'حصل خطأ أثناء تنفيذ الطلب. جرّب تاني.';
            if ($thinkingMessageId !== null) {
                try {
                    $this->editMessageText($chatId, $thinkingMessageId, $reply);
                } catch (\Throwable) {
                    $this->sendMessage($chatId, $reply);
                }
            } else {
                $this->sendMessage($chatId, $reply);
            }
        }

        AiMessage::assistant($reply)
            ->forceFill([
                'ai_session_id' => $session->id,
                'telegram_message_id' => $thinkingMessageId,
                'reply_to_telegram_message_id' => $incomingMessageId,
            ])
            ->save();
    }

    private function pruneSessionAfter(
        AiSession $session,
        int|string $chatId,
        int $messageId,
        ?int $assistantMessageIdToKeep = null,
        ?int $anchorTelegramMessageId = null
    ): void
    {
        $messagesToDrop = $session->messages()
            ->where('id', '>', $messageId)
            ->orderBy('id')
            ->get();

        $deletedTelegramMessageIds = [];
        $maxKnownTelegramMessageId = $anchorTelegramMessageId ?? 0;

        foreach ($messagesToDrop as $message) {
            if ($message->telegram_message_id !== null && (int) $message->telegram_message_id !== $assistantMessageIdToKeep) {
                $telegramMessageId = (int) $message->telegram_message_id;
                $maxKnownTelegramMessageId = max($maxKnownTelegramMessageId, $telegramMessageId);

                try {
                    $this->deleteMessage($chatId, $telegramMessageId);
                    $deletedTelegramMessageIds[$telegramMessageId] = true;
                } catch (\Throwable $e) {
                    Log::warning('telegram.delete_message_failed', [
                        'chat_id' => $chatId,
                        'message_id' => $telegramMessageId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($anchorTelegramMessageId !== null && $maxKnownTelegramMessageId > $anchorTelegramMessageId) {
            for ($candidateMessageId = $anchorTelegramMessageId + 1; $candidateMessageId <= $maxKnownTelegramMessageId; $candidateMessageId++) {
                if (
                    isset($deletedTelegramMessageIds[$candidateMessageId])
                    || ($assistantMessageIdToKeep !== null && $candidateMessageId === $assistantMessageIdToKeep)
                ) {
                    continue;
                }

                try {
                    $this->deleteMessage($chatId, $candidateMessageId);
                } catch (\Throwable $e) {
                    Log::warning('telegram.delete_message_range_failed', [
                        'chat_id' => $chatId,
                        'message_id' => $candidateMessageId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $session->messages()->where('id', '>', $messageId)->delete();
    }

    private function sendThinkingPlaceholder(int|string $chatId, ?int $replyToMessageId = null): ?int
    {
        try {
            $this->sendChatAction($chatId, 'typing');
        } catch (\Throwable $e) {
            Log::warning('telegram.typing_failed', ['error' => $e->getMessage()]);
        }

        try {
            $extra = [];
            if ($replyToMessageId !== null) {
                $extra['reply_to_message_id'] = $replyToMessageId;
            }

            $response = $this->sendMessage($chatId, self::THINKING_TEXT, $extra);

            return $this->extractTelegramMessageId($response);
        } catch (\Throwable $e) {
            Log::warning('telegram.send_thinking_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function replyAndPublish(AiSession $session, int|string $chatId, ?int &$targetMessageId): string
    {
        $reply = $this->runOrchestrator($session);

        if ($reply === '') {
            $reply = '(no response)';
        }

        $reply = $this->formatAssistantReply($reply);

        if ($targetMessageId !== null) {
            try {
                $response = $this->editMessageText($chatId, $targetMessageId, $reply);
                $messageId = $this->extractTelegramMessageId($response);
                if ($messageId !== null) {
                    $targetMessageId = $messageId;
                }

                return $reply;
            } catch (\Throwable $e) {
                Log::warning('telegram.edit_reply_failed', [
                    'chat_id' => $chatId,
                    'message_id' => $targetMessageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $response = $this->sendMessage($chatId, $reply);
        $targetMessageId = $this->extractTelegramMessageId($response);

        return $reply;
    }

    private function formatAssistantReply(string $reply): string
    {
        $reply = trim($reply);
        if ($reply === '') {
            return '(no response)';
        }

        $decoded = json_decode($reply, true);
        if (is_array($decoded)) {
            $formatted = $this->formatArrayAsText($decoded);
            if ($formatted !== '') {
                return $formatted;
            }
        }

        $reply = preg_replace("/[ \t]+/", ' ', $reply) ?? $reply;
        $reply = preg_replace("/\n{3,}/", "\n\n", $reply) ?? $reply;

        return trim($reply);
    }

    private function formatArrayAsText(array $data): string
    {
        $lines = [];
        $this->appendFormattedLines($lines, $data);

        $text = trim(implode("\n", $lines));
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function appendFormattedLines(array &$lines, array $data, int $depth = 0, ?string $label = null): void
    {
        $indent = str_repeat('  ', max(0, $depth));

        if ($label !== null) {
            $lines[] = $indent . $label . ':';
            $indent = str_repeat('  ', $depth + 1);
        }

        $isList = array_keys($data) === range(0, count($data) - 1);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($isList) {
                    $this->appendFormattedLines($lines, $value, $depth + 1, ((string) ((int) $key + 1)));
                } else {
                    $this->appendFormattedLines($lines, $value, $depth + 1, (string) $key);
                }
                continue;
            }

            $scalar = $this->stringifyScalar($value);
            if ($scalar === '') {
                continue;
            }

            if ($isList) {
                $lines[] = $indent . '- ' . $scalar;
            } else {
                $lines[] = $indent . (string) $key . ': ' . $scalar;
            }
        }
    }

    private function stringifyScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
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

    private function transcribeIncomingVoice(array $message): string
    {
        if ($this->voiceTranscriber === null || ! $this->voiceTranscriber->isEnabled()) {
            throw new RuntimeException('Voice transcription is not enabled');
        }

        $voice = is_array($message['voice'] ?? null) ? $message['voice'] : null;
        $audio = is_array($message['audio'] ?? null) ? $message['audio'] : null;
        $file = $voice ?? $audio;

        if (!is_array($file)) {
            throw new RuntimeException('No voice or audio payload found');
        }

        $fileId = isset($file['file_id']) ? (string) $file['file_id'] : '';
        if ($fileId === '') {
            throw new RuntimeException('Voice file_id is missing');
        }

        $fileMeta = $this->call('getFile', ['file_id' => $fileId]);
        $filePath = (string) ($fileMeta['result']['file_path'] ?? '');
        if ($filePath === '') {
            throw new RuntimeException('Telegram file_path is missing');
        }

        $downloadUrl = self::BASE_URL . "/file/bot{$this->botToken}/{$filePath}";
        $downloadResponse = Http::timeout($this->timeout)->get($downloadUrl);
        $downloadResponse->throw();

        $binary = (string) $downloadResponse->body();
        if ($binary === '') {
            throw new RuntimeException('Downloaded voice file is empty');
        }

        $filename = basename($filePath);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = is_array($voice) ? 'voice.ogg' : 'audio.bin';
        }

        return $this->voiceTranscriber->transcribe($binary, $filename);
    }

    private function resolveVoiceFailureUserMessage(\Throwable $e): string
    {
        $error = strtolower($e->getMessage());

        if (str_contains($error, 'quota') || str_contains($error, 'billing') || str_contains($error, 'insufficient_quota')) {
            return 'ميزة الفويس متوقفة مؤقتًا لأن رصيد خدمة التفريغ الصوتي خلص (API quota). جدد الرصيد أو غيّر المفتاح، وممكن تبعتلي الرسالة كنص حاليًا.';
        }

        if (str_contains($error, 'api key is missing') || str_contains($error, 'invalid api key') || str_contains($error, 'unauthorized')) {
            return 'ميزة الفويس غير مفعلة بسبب مشكلة في API key. راجع إعدادات TELEGRAM_VOICE_API_KEY.';
        }

        if (str_contains($error, 'timeout')) {
            return 'الفويس أخد وقت طويل ومكملش. جرّب تبعت فويس أقصر أو ابعته كنص.';
        }

        return 'مقدرتش أفهم الفويس. ابعته تاني أو ابعته كنص.';
    }

    private function resolveAssistantFailureUserMessage(\Throwable $e): string
    {
        $error = strtolower($e->getMessage());

        if (
            str_contains($error, 'connection failed')
            || str_contains($error, 'couldn\'t connect to server')
            || str_contains($error, 'curl error 7')
        ) {
            return 'خدمة الذكاء الاصطناعي مش متاحة دلوقتي أو مش متوصلة صح. راجع OLLAMA_API_URL و OLLAMA_API_KEY وجرّب تاني.';
        }

        if (str_contains($error, 'timeout')) {
            return 'الخدمة أخدت وقت أطول من اللازم ومكملتش الرد. جرّب تاني أو ابعت طلب أقصر.';
        }

        if (
            str_contains($error, 'creating the response')
            || str_contains($error, 'provider request failed')
            || str_contains($error, 'tool failed')
        ) {
            return 'حصلت مشكلة أثناء تنفيذ الطلب من خدمة الذكاء الاصطناعي أو إحدى الأدوات. لو الرابط من LinkedIn فممكن يكون الموقع مانع القراءة المباشرة.';
        }

        return 'حصل خطأ أثناء تنفيذ الطلب. جرّب تاني.';
    }

    private function runOrchestrator(AiSession $session): string
    {
        $query = $session->messages();

        $contextStartAfterMessageId = (int) ($session->context_starts_after_message_id ?? 0);
        if ($contextStartAfterMessageId > 0) {
            $query->where('id', '>', $contextStartAfterMessageId);
        }

        $messages = $query
            ->orderByDesc('id')
            ->limit($this->historyLimit)
            ->get()
            ->reverse()
            ->values()
            ->all();

        $orchestrator = new Orchestrator($this->provider, $this->agent, $this->modelName);

        return $orchestrator->askAi($messages, [
            'ai_session_id' => (int) $session->id,
            'telegram_chat_id' => (string) $session->telegram_chat_id,
        ]);
    }

    private function extractTelegramMessageId(array $response): ?int
    {
        $result = $response['result'] ?? null;
        if (!is_array($result) || !isset($result['message_id'])) {
            return null;
        }

        return (int) $result['message_id'];
    }

    private function truncateForTelegram(string $text): string
    {
        if (mb_strlen($text) <= self::TELEGRAM_TEXT_LIMIT) {
            return $text;
        }

        return mb_substr($text, 0, self::TELEGRAM_TEXT_LIMIT - 1) . '...';
    }

    private function call(string $method, array $payload = []): array
    {
        $response = Http::timeout($this->timeout)
            ->asJson()
            ->post(self::BASE_URL . "/bot{$this->botToken}/{$method}", $payload);

        $response->throw();

        $json = $response->json();
        if (!is_array($json) || ($json['ok'] ?? false) !== true) {
            $description = is_array($json) ? ($json['description'] ?? 'Unknown Telegram API error') : 'Invalid Telegram API response';
            throw new RuntimeException((string) $description);
        }

        return $json;
    }
}
