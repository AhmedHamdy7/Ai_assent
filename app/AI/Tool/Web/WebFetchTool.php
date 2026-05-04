<?php

namespace App\AI\Tool\Web;

use App\AI\Provider\ToolResult;
use App\AI\Tool\BaseTool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebFetchTool extends BaseTool
{
    public function __construct(
        private readonly int $timeoutSeconds = 15,
        private readonly int $maxContentChars = 12000,
    ) {}

    public function getName(): string
    {
        return 'web_fetch';
    }

    public function eventAction(): string
    {
        return 'Fetching a web page';
    }

    public function getDescription(): string
    {
        return 'Fetch and extract readable text content from a public web URL.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => ['type' => 'string', 'description' => 'Public http(s) URL'],
                'max_chars' => ['type' => 'integer', 'minimum' => 500, 'maximum' => 30000],
            ],
            'required' => ['url'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $url = trim((string) ($arguments['url'] ?? ''));
        if ($url === '') {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'url is required']);
        }

        if (! $this->isSafePublicUrl($url)) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'Blocked URL. Only public http(s) URLs are allowed.']);
        }

        $maxChars = (int) ($arguments['max_chars'] ?? $this->maxContentChars);
        $maxChars = max(500, min($maxChars, 30000));

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders(['User-Agent' => 'HamdixBot/1.0 (+web_fetch)'])
                ->get($url);

            $response->throw();
        } catch (RequestException $e) {
            $details = trim((string) $e->response?->body());
            $message = $details !== ''
                ? 'Failed to fetch URL: ' . Str::limit($details, 300, '...')
                : 'Failed to fetch URL: ' . $e->getMessage();

            if (str_contains(strtolower($url), 'linkedin.com')) {
                $message = 'LinkedIn blocked direct page fetching. Ask for a profile summary from the URL itself or use another public source.';
            }

            return ToolResult::fromPayload([
                'ok' => false,
                'message' => $message,
                'url' => $url,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => 'Failed to fetch URL: ' . $e->getMessage(),
                'url' => $url,
            ]);
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));
        $body = (string) $response->body();

        if (str_contains($contentType, 'application/json')) {
            $text = $this->normalizeWhitespace($body);
        } elseif (str_contains($contentType, 'text/html') || $contentType === '') {
            $text = $this->extractTextFromHtml($body);
        } else {
            $text = $this->normalizeWhitespace($body);
        }

        $text = Str::limit($text, $maxChars, '...');

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => 'Fetched content from ' . $url,
            'url' => $url,
            'content_type' => $contentType,
            'content' => $text,
        ]);
    }

    private function extractTextFromHtml(string $html): string
    {
        $withoutScripts = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', ' ', $html) ?? $html;
        $withoutStyles = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', ' ', $withoutScripts) ?? $withoutScripts;
        $text = strip_tags($withoutStyles);

        return $this->normalizeWhitespace(html_entity_decode($text, ENT_QUOTES | ENT_HTML5));
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function isSafePublicUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        $resolvedHosts = gethostbynamel($host);
        if ($resolvedHosts === false || $resolvedHosts === []) {
            return true;
        }

        foreach ($resolvedHosts as $resolvedIp) {
            $isPublic = filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($isPublic === false) {
                return false;
            }
        }

        return true;
    }
}
