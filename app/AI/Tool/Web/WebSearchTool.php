<?php

namespace App\AI\Tool\Web;

use App\AI\Provider\ToolResult;
use App\AI\Tool\BaseTool;
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

        $results = array_slice($results, 0, $limit);

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => empty($results)
                ? 'No web results found.'
                : 'Found ' . count($results) . ' web result(s).',
            'query' => $query,
            'results' => $results,
        ]);
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
