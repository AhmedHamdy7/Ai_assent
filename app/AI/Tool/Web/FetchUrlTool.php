<?php

namespace App\AI\Tool\Web;

use App\AI\Provider\ToolResult;
use App\AI\Tool\BaseTool;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FetchUrlTool extends BaseTool
{
    public function __construct(
        private readonly int $timeoutSeconds = 15,
        private readonly int $maxContentChars = 20000,
    ) {}

    public function getName(): string
    {
        return 'fetch_url';
    }

    public function eventAction(): string
    {
        return 'Reading a webpage';
    }

    public function getDescription(): string
    {
        return 'Fetch the content of a public URL and return it as clean text. '
            . 'HTML pages are parsed into readable text, while JSON and plain text responses are returned as-is. '
            . 'Use this after web_search when you need the content of a specific page.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'The full public URL to fetch, including http:// or https://.',
                ],
                'maxLength' => [
                    'type' => 'integer',
                    'minimum' => 500,
                    'maximum' => 50000,
                    'description' => 'Maximum number of characters to return. Defaults to 20000.',
                ],
                'max_chars' => [
                    'type' => 'integer',
                    'minimum' => 500,
                    'maximum' => 50000,
                    'description' => 'Legacy alias for maxLength.',
                ],
            ],
            'required' => ['url'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $url = trim((string) ($arguments['url'] ?? ''));
        if ($url === '') {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => 'Error: missing required parameter "url". Provide the full URL including protocol.',
            ]);
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => sprintf('FAILED: Invalid URL "%s". Must start with http:// or https://.', $url),
                'url' => $url,
            ]);
        }

        if (! $this->isSafePublicUrl($url)) {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => 'FAILED: Blocked URL. Only public http(s) URLs are allowed.',
                'url' => $url,
            ]);
        }

        $maxLength = (int) ($arguments['maxLength'] ?? $arguments['max_chars'] ?? $this->maxContentChars);
        $maxLength = max(500, min($maxLength, 50000));

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; HamdixBot/1.0; +fetch_url)',
                    'Accept' => 'text/html,application/json,text/plain,*/*',
                ])
                ->withOptions([
                    'allow_redirects' => ['max' => 5],
                ])
                ->get($url);

            $response->throw();
        } catch (ConnectionException $e) {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => $this->describeConnectionError($e, $url),
                'url' => $url,
            ]);
        } catch (RequestException $e) {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => $this->describeRequestError($e, $url),
                'url' => $url,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => sprintf('FAILED: Unexpected error fetching "%s": %s', $url, $e->getMessage()),
                'url' => $url,
            ]);
        }

        $body = (string) $response->body();
        if ($body === '') {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => 'FAILED: Server returned an empty response. This may be a JavaScript-rendered page that requires a browser to load.',
                'url' => $url,
            ]);
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));
        $text = str_contains($contentType, 'text/html') || $contentType === ''
            ? $this->extractTextFromHtml($body)
            : $this->normalizeWhitespace($body);

        $text = trim($text);
        if ($text === '') {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => 'FAILED: Page returned HTML but contained no readable text. This is likely a JavaScript-rendered single-page app.',
                'url' => $url,
                'content_type' => $contentType,
            ]);
        }

        $totalChars = Str::length($text);
        $truncated = $totalChars > $maxLength;
        if ($truncated) {
            $text = Str::substr($text, 0, $maxLength)
                . sprintf(
                    "\n\n[TRUNCATED - showing %d of %d characters. Use maxLength to see more.]",
                    $maxLength,
                    $totalChars
                );
        }

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => 'Fetched content from ' . $url,
            'url' => $url,
            'content_type' => $contentType,
            'content' => $text,
            'returned_chars' => Str::length($text),
            'total_chars' => $totalChars,
            'truncated' => $truncated,
        ]);
    }

    private function extractTextFromHtml(string $html): string
    {
        $xpath = $this->createXPath($html);

        if ($xpath === null) {
            return $this->normalizeWhitespace(strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5)));
        }

        foreach ([
            '//script',
            '//style',
            '//noscript',
            '//svg',
            '//nav',
            '//footer',
            '//header',
        ] as $expression) {
            foreach ($xpath->query($expression) ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $text = $xpath->document->textContent ?? '';

        return $this->normalizeWhitespace(html_entity_decode($text, ENT_QUOTES | ENT_HTML5));
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function describeConnectionError(ConnectionException $e, string $url): string
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'timed out')) {
            return sprintf(
                'FAILED: Connection timed out after %ds trying to reach "%s". The server may be down or the URL may be incorrect.',
                $this->timeoutSeconds,
                $url
            );
        }

        return sprintf(
            'FAILED: Could not connect to "%s". Check that the URL is correct and the server is reachable. DNS resolution may have failed or the host may be down.',
            $url
        );
    }

    private function describeRequestError(RequestException $e, string $url): string
    {
        $code = $e->response?->status();

        if (str_contains(strtolower($url), 'linkedin.com') && in_array($code, [401, 403], true)) {
            return 'FAILED: LinkedIn blocked direct page fetching. Ask for a profile summary from the URL itself or use another public source.';
        }

        $messages = [
            400 => 'Bad request - the URL may be malformed.',
            401 => 'Authentication required - this is not a public page.',
            403 => 'Access forbidden - the server is blocking automated requests.',
            404 => 'Page not found - the URL may be wrong or the page was removed.',
            429 => 'Rate limited - too many requests. Try again later.',
            500 => 'Internal server error - the server is having issues.',
            502 => 'Bad gateway - the server is temporarily unavailable.',
            503 => 'Service unavailable - the server is overloaded or under maintenance.',
        ];

        $detail = $messages[$code] ?? sprintf('HTTP %s error.', $code ?? 'unknown');

        return sprintf('FAILED: Server returned status %s for "%s". %s', $code ?? 'unknown', $url, $detail);
    }

    private function createXPath(string $html): ?DOMXPath
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument();
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);

            if (! $loaded) {
                return null;
            }

            return new DOMXPath($document);
        } catch (\Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function isSafePublicUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
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
