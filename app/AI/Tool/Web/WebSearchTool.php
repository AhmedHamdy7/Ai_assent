<?php

namespace App\AI\Tool\Web;

use App\AI\Provider\ToolResult;
use App\AI\Tool\BaseTool;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebSearchTool extends BaseTool
{
    private const SEARCH_URL = 'https://html.duckduckgo.com/html/';

    public function __construct(
        private readonly int $timeoutSeconds = 15,
        private readonly int $maxResults = 8,
    ) {}

    public function getName(): string
    {
        return 'web_search';
    }

    public function eventAction(): string
    {
        return 'Searching the web';
    }

    public function getDescription(): string
    {
        return 'Search the web using DuckDuckGo and return results with titles, URLs, and snippets. '
            . 'Use this for current information, fact-checking, documentation discovery, and finding a source before using fetch_url.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The search query. Be specific for better results.',
                ],
                'maxResults' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 20,
                    'description' => 'Maximum number of results to return. Defaults to 8.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 20,
                    'description' => 'Legacy alias for maxResults.',
                ],
            ],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        if ($query === '') {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => 'Error: missing required parameter "query". Provide a search query string.',
            ]);
        }

        $limit = (int) ($arguments['maxResults'] ?? $arguments['limit'] ?? $this->maxResults);
        $limit = max(1, min($limit, 20));

        try {
            $results = $this->searchHtmlResults($query, $limit);

            if ($results === []) {
                $results = $this->searchBingResults($query, $limit);
            }

            if ($results === []) {
                $results = $this->searchInstantAnswerApi($query, $limit);
            }
        } catch (ConnectionException $e) {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => $this->describeConnectionError($e),
                'query' => $query,
                'results' => [],
            ]);
        } catch (RequestException $e) {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => $this->describeRequestError($e),
                'query' => $query,
                'results' => [],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => 'FAILED: Unexpected error during search: ' . $e->getMessage(),
                'query' => $query,
                'results' => [],
            ]);
        }

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => empty($results)
                ? sprintf('No results found for "%s". Try a different or broader search query.', $query)
                : sprintf('Found %d web result(s) for "%s".', count($results), $query),
            'query' => $query,
            'results' => $results,
        ]);
    }

    private function searchHtmlResults(string $query, int $limit): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; HamdixBot/1.0; +web_search)',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
            ->withOptions([
                'allow_redirects' => ['max' => 5],
            ])
            ->asForm()
            ->post(self::SEARCH_URL, [
                'q' => $query,
            ]);

        $response->throw();

        $body = (string) $response->body();
        if ($body === '' || str_contains($body, 'anomaly.js')) {
            return [];
        }

        return $this->parseHtmlResults($body, $limit);
    }

    private function searchBingResults(string $query, int $limit): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
            ->get('https://www.bing.com/search', [
                'q' => $query,
                'format' => 'rss',
            ]);

        $response->throw();

        return $this->extractBingResults((string) $response->body(), $limit);
    }

    private function searchInstantAnswerApi(string $query, int $limit): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->get('https://api.duckduckgo.com/', [
                'q' => $query,
                'format' => 'json',
                'no_html' => '1',
                'skip_disambig' => '1',
                'no_redirect' => '1',
            ]);

        $response->throw();
        $data = $response->json();

        $results = [];

        $abstractText = trim((string) Arr::get($data, 'AbstractText', ''));
        $abstractUrl = trim((string) Arr::get($data, 'AbstractURL', ''));
        $heading = trim((string) Arr::get($data, 'Heading', ''));

        if ($abstractText !== '' && $abstractUrl !== '') {
            $results[] = [
                'title' => $heading !== '' ? $heading : 'Result',
                'url' => $abstractUrl,
                'snippet' => Str::limit($abstractText, 280, '...'),
            ];
        }

        foreach ((array) Arr::get($data, 'RelatedTopics', []) as $topic) {
            if (count($results) >= $limit) {
                break;
            }

            if (isset($topic['Topics']) && is_array($topic['Topics'])) {
                foreach ($topic['Topics'] as $nested) {
                    if (count($results) >= $limit) {
                        break;
                    }

                    $item = $this->mapTopicToResult($nested);
                    if ($item !== null) {
                        $results[] = $item;
                    }
                }

                continue;
            }

            $item = $this->mapTopicToResult($topic);
            if ($item !== null) {
                $results[] = $item;
            }
        }

        return array_slice($results, 0, $limit);
    }

    private function parseHtmlResults(string $html, int $limit): array
    {
        $xpath = $this->createXPath($html);
        if ($xpath === null) {
            return [];
        }

        $results = [];

        /** @var DOMElement $resultNode */
        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " result ")]') ?: [] as $resultNode) {
            if (count($results) >= $limit) {
                break;
            }

            $titleNode = $xpath->query(
                './/*[contains(concat(" ", normalize-space(@class), " "), " result__a ")]',
                $resultNode
            )?->item(0);

            if (! $titleNode instanceof DOMElement) {
                continue;
            }

            $title = $this->cleanText($titleNode->textContent ?? '');
            $url = $this->extractUrl($titleNode->getAttribute('href'));
            $snippetNode = $xpath->query(
                './/*[contains(concat(" ", normalize-space(@class), " "), " result__snippet ")]',
                $resultNode
            )?->item(0);
            $snippet = $snippetNode instanceof DOMElement
                ? $this->cleanText($snippetNode->textContent ?? '')
                : '';

            if ($url === '' || $title === '') {
                continue;
            }

            if (str_starts_with($url, 'https://duckduckgo.com') || str_starts_with($url, 'http://duckduckgo.com')) {
                continue;
            }

            $results[] = [
                'title' => Str::limit($title, 140, '...'),
                'url' => $url,
                'snippet' => Str::limit($snippet !== '' ? $snippet : $title, 280, '...'),
            ];
        }

        return $results;
    }

    private function extractBingResults(string $html, int $limit): array
    {
        $results = [];

        $xml = @simplexml_load_string($html);
        if ($xml === false || ! isset($xml->channel->item)) {
            return [];
        }

        foreach ($xml->channel->item as $item) {
            if (count($results) >= $limit) {
                break;
            }

            $url = trim((string) ($item->link ?? ''));
            $title = $this->cleanText((string) ($item->title ?? ''));
            $snippet = $this->cleanText((string) ($item->description ?? ''));

            if ($url === '' || $title === '') {
                continue;
            }

            $results[] = [
                'title' => Str::limit($title, 140, '...'),
                'url' => $url,
                'snippet' => Str::limit($snippet !== '' ? $snippet : $title, 280, '...'),
            ];
        }

        return $results;
    }

    private function cleanText(string $value): string
    {
        $value = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function extractUrl(string $href): string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5));
        if ($href === '') {
            return '';
        }

        $parts = parse_url($href);
        if (is_array($parts)) {
            $query = [];
            parse_str((string) ($parts['query'] ?? ''), $query);
            $uddg = $query['uddg'] ?? null;

            if (is_string($uddg) && trim($uddg) !== '') {
                return trim($uddg);
            }
        }

        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        return '';
    }

    private function mapTopicToResult(array $topic): ?array
    {
        $text = trim((string) ($topic['Text'] ?? ''));
        $url = trim((string) ($topic['FirstURL'] ?? ''));
        if ($text === '' || $url === '') {
            return null;
        }

        $title = Str::before($text, ' - ');
        if ($title === '') {
            $title = Str::limit($text, 80, '...');
        }

        return [
            'title' => $title,
            'url' => $url,
            'snippet' => Str::limit($text, 280, '...'),
        ];
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

    private function describeConnectionError(ConnectionException $e): string
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'timed out')) {
            return sprintf(
                'FAILED: DuckDuckGo search timed out after %ds. The service may be slow - try again in a moment.',
                $this->timeoutSeconds
            );
        }

        return 'FAILED: Could not connect to DuckDuckGo. Check internet connectivity.';
    }

    private function describeRequestError(RequestException $e): string
    {
        $code = $e->response?->status();

        if ($code === 403) {
            return 'FAILED: DuckDuckGo blocked the request (403 Forbidden). This may be temporary rate limiting - wait a minute and try again.';
        }

        if ($code === 429) {
            return 'FAILED: Too many search requests. Wait a minute before searching again.';
        }

        return sprintf('FAILED: Web search returned HTTP %s. Try again later.', $code ?? 'unknown');
    }
}
