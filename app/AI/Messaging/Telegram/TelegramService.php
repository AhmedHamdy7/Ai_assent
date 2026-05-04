<?php

namespace App\AI\Messaging\Telegram;

use App\AI\Agent\BaseAgent;
use App\AI\Orchestration\Orchestrator;
use App\AI\Provider\AiMessage;
use App\AI\Provider\AiRoleEnum;
use App\AI\Provider\AiSession;
use App\AI\Provider\BaseProvider;
use App\AI\SpeechToText\SpeechToTextProvider;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private const BASE_URL = 'https://api.telegram.org';
    private const TELEGRAM_TEXT_LIMIT = 4096;
    private const THINKING_TEXT = 'thinking...';
    private const CHUNK_DELAY_MICROSECONDS = 1100000;
    private const MAX_RETRIES = 3;
    private const FALLBACK_MESSAGE = 'Something went wrong, please try again.';

    public function __construct(
        private string $botToken,
        private ?string $webhookSecret,
        private int $timeout,
        private BaseProvider $provider,
        private BaseAgent $agent,
        private string $modelName,
        private ?SpeechToTextProvider $speechToTextProvider = null,
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
        $thinkingMessageId = null;

        if ($chatId === null || $incomingMessageId === null) {
            return;
        }

        if (!is_string($text) || trim($text) === '') {
            if ($hasVoicePayload) {
                $thinkingMessageId = $this->sendThinkingPlaceholder($chatId);

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
                        if ($thinkingMessageId !== null) {
                            $this->editMessageText($chatId, $thinkingMessageId, $userMessage);
                        } else {
                            $this->sendMessage($chatId, $userMessage);
                        }
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

        $this->handleNewMessage($session, $chatId, $incomingMessageId, $replyToMessageId, $text, $thinkingMessageId);
    }

    public function verifySecret(?string $headerSecret): bool
    {
        if (empty($this->webhookSecret)) {
            return true;
        }

        return is_string($headerSecret) && hash_equals($this->webhookSecret, $headerSecret);
    }

    private function handleNewMessage(
        AiSession $session,
        int|string $chatId,
        int $incomingMessageId,
        ?int $replyToMessageId,
        string $text,
        ?int $thinkingMessageId = null
    ): void
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

        $thinkingMessageId ??= $this->sendThinkingPlaceholder($chatId);
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

            $response = $this->sendPlainMessage($chatId, self::THINKING_TEXT, $extra);

            return $this->extractTelegramMessageId($response);
        } catch (\Throwable $e) {
            Log::warning('telegram.send_thinking_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function replyAndPublish(AiSession $session, int|string $chatId, ?int &$targetMessageId): string
    {
        $reply = $this->runOrchestrator($session, $chatId, $targetMessageId);

        if ($reply === '') {
            $reply = '(no response)';
        }

        $reply = $this->formatAssistantReply($reply);

        try {
            if ($targetMessageId !== null) {
                $messageIds = $this->editRenderedMessageWithMessageIds($chatId, $targetMessageId, $reply);
                $targetMessageId = $messageIds[0] ?? $targetMessageId;

                return $reply;
            }

            $messageIds = $this->sendRenderedMessageWithMessageIds($chatId, $reply);
            $targetMessageId = $messageIds[0] ?? null;

            return $reply;
        } catch (\Throwable $e) {
            Log::warning('telegram.edit_reply_failed', [
                'chat_id' => $chatId,
                'message_id' => $targetMessageId,
                'error' => $e->getMessage(),
            ]);
        }

        $response = $this->sendPlainMessage($chatId, self::FALLBACK_MESSAGE);
        $targetMessageId = $this->extractTelegramMessageId($response);

        return self::FALLBACK_MESSAGE;
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

        $filename = basename($filePath);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = is_array($voice) ? 'voice.ogg' : 'audio.bin';
        }

        return $this->makeOrchestrator()->transcribeAudioBinary(
            $binary,
            $filename,
            $this->resolveIncomingAudioMimeType($voice, $audio),
        );
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

    private function runOrchestrator(AiSession $session, int|string $chatId, ?int $thinkingMessageId = null): string
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

        $onToolStart = null;

        if ($thinkingMessageId !== null) {
            $onToolStart = function (string $eventAction) use ($chatId, $thinkingMessageId): void {
                try {
                    $this->editMessageText($chatId, $thinkingMessageId, $eventAction . '...');
                } catch (\Throwable $e) {
                    Log::warning('telegram.tool_event_status_failed', [
                        'chat_id' => $chatId,
                        'message_id' => $thinkingMessageId,
                        'event_action' => $eventAction,
                        'error' => $e->getMessage(),
                    ]);
                }
            };
        }

        return $this->makeOrchestrator()->askAi($messages, [
            'ai_session_id' => (int) $session->id,
            'telegram_chat_id' => (string) $session->telegram_chat_id,
        ], $onToolStart);
    }

    private function makeOrchestrator(): Orchestrator
    {
        return new Orchestrator(
            $this->provider,
            $this->agent,
            $this->modelName,
            speechToTextProvider: $this->speechToTextProvider,
        );
    }

    private function resolveIncomingAudioMimeType(?array $voice, ?array $audio): string
    {
        if (is_array($audio)) {
            $mimeType = trim((string) ($audio['mime_type'] ?? ''));

            if ($mimeType !== '') {
                return $mimeType;
            }
        }

        return is_array($voice) ? 'audio/ogg' : 'application/octet-stream';
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

    /**
     * @return list<int>
     */
    private function sendRenderedMessageWithMessageIds(int|string $chatId, string $text): array
    {
        $messageIds = [];
        $chunks = $this->toHtmlChunks($text);

        foreach ($chunks as $index => $chunk) {
            if ($index > 0) {
                usleep(self::CHUNK_DELAY_MICROSECONDS);
            }

            $response = $this->withRetry(fn () => $this->postHtmlWithFallback('sendMessage', [
                'chat_id' => $chatId,
                'text' => $chunk,
            ]));

            $messageId = $this->extractTelegramMessageId($response);
            if ($messageId !== null) {
                $messageIds[] = $messageId;
            }
        }

        return $messageIds;
    }

    /**
     * @return list<int>
     */
    private function editRenderedMessageWithMessageIds(int|string $chatId, int $messageId, string $text): array
    {
        $messageIds = [$messageId];
        $chunks = $this->toHtmlChunks($text);
        $firstChunk = $chunks[0] ?? '';

        $this->withRetry(fn () => $this->postHtmlWithFallback('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $firstChunk,
        ], isEdit: true));

        foreach (array_slice($chunks, 1) as $chunk) {
            usleep(self::CHUNK_DELAY_MICROSECONDS);

            $response = $this->withRetry(fn () => $this->postHtmlWithFallback('sendMessage', [
                'chat_id' => $chatId,
                'text' => $chunk,
            ]));

            $overflowMessageId = $this->extractTelegramMessageId($response);
            if ($overflowMessageId !== null) {
                $messageIds[] = $overflowMessageId;
            }
        }

        return $messageIds;
    }

    private function postHtmlWithFallback(string $method, array $payload, bool $isEdit = false): array
    {
        try {
            return $this->telegramRequest($method, array_merge($payload, [
                'parse_mode' => 'HTML',
            ]));
        } catch (TelegramApiException $e) {
            if ($isEdit && $e->isNotModified()) {
                return ['ok' => true, 'result' => ['message_id' => $payload['message_id'] ?? null]];
            }

            if (! $e->isParseError()) {
                throw $e;
            }

            $plainPayload = $payload;
            $plainPayload['text'] = $this->truncateForTelegram($this->stripHtmlTags((string) ($payload['text'] ?? '')));

            return $this->telegramRequest($method, $plainPayload);
        }
    }

    private function withRetry(callable $callback): array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                return $callback();
            } catch (TelegramApiException $e) {
                $lastException = $e;

                if (! $e->isRetryable() || $attempt === self::MAX_RETRIES) {
                    throw $e;
                }

                sleep($e->errorCode() === 429 && $e->retryAfter() !== null ? $e->retryAfter() : $attempt * 2);
            } catch (ConnectionException $e) {
                $lastException = $e;

                if ($attempt === self::MAX_RETRIES) {
                    throw $e;
                }

                sleep($attempt * 2);
            }
        }

        throw $lastException ?? new RuntimeException('Telegram request failed.');
    }

    private function sendPlainMessage(int|string $chatId, string $text, array $extra = []): array
    {
        return $this->call('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $this->truncateForTelegram($text),
        ], $extra));
    }

    /**
     * @return list<string>
     */
    private function toHtmlChunks(string $text): array
    {
        $text = trim($text);
        $paragraphs = preg_split('/\n{2,}/u', $text);
        $chunks = [];
        $buffer = '';

        foreach ($paragraphs ?: [''] as $paragraph) {
            $html = $this->markdownToHtml((string) $paragraph);
            $separator = $buffer === '' ? '' : "\n\n";

            if ($this->telegramLength($buffer . $separator . $html) > self::TELEGRAM_TEXT_LIMIT) {
                if ($buffer !== '') {
                    $chunks[] = $buffer;
                    $buffer = '';
                }

                if ($this->telegramLength($html) > self::TELEGRAM_TEXT_LIMIT) {
                    $this->hardSplitInto($html, $chunks);
                } else {
                    $buffer = $html;
                }
            } else {
                $buffer .= $separator . $html;
            }
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks !== [] ? $chunks : [''];
    }

    /**
     * @param list<string> $chunks
     */
    private function hardSplitInto(string $text, array &$chunks): void
    {
        while ($this->telegramLength($text) > self::TELEGRAM_TEXT_LIMIT) {
            $head = mb_substr($text, 0, self::TELEGRAM_TEXT_LIMIT);
            $splitAt = mb_strrpos($head, "\n");

            if ($splitAt === false || $splitAt <= 0) {
                $splitAt = self::TELEGRAM_TEXT_LIMIT;
            }

            $chunks[] = mb_substr($text, 0, $splitAt);
            $text = ltrim(mb_substr($text, $splitAt));
        }

        if ($text !== '') {
            $chunks[] = $text;
        }
    }

    private function markdownToHtml(string $text): string
    {
        $codeBlocks = [];
        $inlineCodes = [];

        $text = preg_replace_callback('/```(\w*)\n?([\s\S]*?)```/u', function (array $matches) use (&$codeBlocks): string {
            $language = (string) ($matches[1] ?? '');
            $body = $this->escapeHtml((string) ($matches[2] ?? ''));
            $tag = $language !== ''
                ? "<pre><code class=\"language-{$language}\">{$body}</code></pre>"
                : "<pre><code>{$body}</code></pre>";

            $codeBlocks[] = $tag;

            return "\x00" . (count($codeBlocks) - 1) . "\x00";
        }, $text) ?? $text;

        $text = preg_replace_callback('/`([^`\n]+)`/u', function (array $matches) use (&$inlineCodes): string {
            $inlineCodes[] = '<code>' . $this->escapeHtml((string) ($matches[1] ?? '')) . '</code>';

            return "\x01" . (count($inlineCodes) - 1) . "\x01";
        }, $text) ?? $text;

        $text = $this->escapeHtml($text);
        $text = preg_replace('/^#{1,3} +(.+)$/mu', '<b>$1</b>', $text) ?? $text;
        $text = preg_replace('/\[([^\]\n]+)\]\((https?:\/\/[^\)\n]+)\)/u', '<a href="$2">$1</a>', $text) ?? $text;
        $text = preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/u', '<i>$1</i>', $text) ?? $text;
        $text = preg_replace('/(?<!\w)_([^_\n]+)_(?!\w)/u', '<i>$1</i>', $text) ?? $text;
        $text = preg_replace('/~~(.+?)~~/u', '<s>$1</s>', $text) ?? $text;

        $text = preg_replace_callback('/\x01(\d+)\x01/u', fn (array $matches): string => $inlineCodes[(int) $matches[1]] ?? '', $text) ?? $text;
        $text = preg_replace_callback('/\x00(\d+)\x00/u', fn (array $matches): string => $codeBlocks[(int) $matches[1]] ?? '', $text) ?? $text;

        return $text;
    }

    private function escapeHtml(string $text): string
    {
        return strtr($text, [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
        ]);
    }

    private function stripHtmlTags(string $html): string
    {
        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function telegramLength(string $text): int
    {
        return (int) (strlen(mb_convert_encoding($text, 'UTF-16', 'UTF-8')) / 2);
    }

    private function call(string $method, array $payload = []): array
    {
        return $this->telegramRequest($method, $payload);
    }

    private function telegramRequest(string $method, array $payload = []): array
    {
        $response = Http::timeout($this->timeout)
            ->asJson()
            ->post(self::BASE_URL . "/bot{$this->botToken}/{$method}", $payload);

        $json = $response->json();

        if ($response->successful() && is_array($json) && ($json['ok'] ?? false) === true) {
            return $json;
        }

        $description = is_array($json)
            ? (string) ($json['description'] ?? 'Unknown Telegram API error')
            : ((string) $response->body() !== '' ? (string) $response->body() : 'Invalid Telegram API response');

        $errorCode = is_array($json) ? (int) ($json['error_code'] ?? $response->status()) : $response->status();
        $retryAfter = is_array($json) && isset($json['parameters']['retry_after'])
            ? (int) $json['parameters']['retry_after']
            : null;

        throw new TelegramApiException($description, $errorCode, $retryAfter);
    }
}
