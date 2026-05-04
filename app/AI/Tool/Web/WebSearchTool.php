<?php

namespace App\AI\Tool\Web;

use App\AI\Provider\ToolResult;
use App\AI\Tool\BaseTool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebSearchTool extends BaseTool
{
    public function __construct(
        private readonly int $timeoutSeconds = 12,
        private readonly int $maxResults = 5,
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
        return 'Search the web for current information and return short result snippets with URLs.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Search query'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
            ],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        if ($query === '') {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'query is required']);
        }

        $limit = (int) ($arguments['limit'] ?? $this->maxResults);
        $limit = max(1, min($limit, 10));

        try {
            $results = $this->searchHtmlResults($query, $limit);

            if ($results === []) {
                $results = $this->searchBingResults($query, $limit);
            }

            if ($results === []) {
                $results = $this->searchInstantAnswerApi($query, $limit);
            }
        } catch (RequestException $e) {
            $details = trim((string) $e->response?->body());

            return ToolResult::fromPayload([
                'ok' => false,
                'message' => $details !== ''
                    ? 'Web search failed: ' . Str::limit($details, 300, '...')
                    : 'Web search failed: ' . $e->getMessage(),
                'query' => $query,
                'results' => [],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::fromPayload([
                'ok' => false,
                'message' => 'Web search failed: ' . $e->getMessage(),
                'query' => $query,
                'results' => [],
            ]);
        }

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => empty($results)
                ? 'No web results found.'
                : 'Found ' . count($results) . ' web result(s).',
            'query' => $query,
            'results' => $results,
        ]);
    }

    private function searchHtmlResults(string $query, int $limit): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders([
                'User-Agent' => 'HamdixBot/1.0 (+web_search)',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
            ->asForm()
            ->post('https://html.duckduckgo.com/html/', [
                'q' => $query,
            ]);

        $response->throw();

        $body = (string) $response->body();
        if (str_contains($body, 'anomaly.js')) {
            return [];
        }

        return $this->extractHtmlResults($body, $limit);
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

    private function extractHtmlResults(string $html, int $limit): array
    {
        $results = [];
        $pattern = '/<a[^>]*class="[^"]*result__a[^"]*"[^>]*href="(?P<url>[^"]+)"[^>]*>(?P<title>.*?)<\/a>.*?(?:<a[^>]*class="[^"]*result__snippet[^"]*"[^>]*>(?P<snippet_a>.*?)<\/a>|<span[^>]*class="[^"]*result__snippet[^"]*"[^>]*>(?P<snippet_span>.*?)<\/span>)/si';

        $matched = preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
        if ($matched === false || $matched === 0) {
            return [];
        }

        foreach ($matches as $match) {
            if (count($results) >= $limit) {
                break;
            }

            $url = trim(html_entity_decode((string) ($match['url'] ?? ''), ENT_QUOTES | ENT_HTML5));
            $title = $this->cleanHtmlText((string) ($match['title'] ?? ''));
            $snippet = $this->cleanHtmlText((string) (($match['snippet_a'] ?? '') !== '' ? $match['snippet_a'] : ($match['snippet_span'] ?? '')));

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
            $title = $this->cleanHtmlText((string) ($item->title ?? ''));
            $snippet = $this->cleanHtmlText((string) ($item->description ?? ''));

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

    private function cleanHtmlText(string $value): string
    {
        $value = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
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
}
