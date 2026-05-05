<?php

namespace Tests\Feature\AI;

use App\AI\Tool\Web\FetchUrlTool;
use App\AI\Tool\Web\WebFetchTool;
use App\AI\Tool\Web\WebSearchTool;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebToolTest extends TestCase
{
    public function test_web_search_prefers_html_results_and_formats_them(): void
    {
        Http::fake([
            'https://html.duckduckgo.com/html/' => Http::response(
                '<html><body><div class="result"><a class="result__a" href="/l/?uddg=https%3A%2F%2Fexample.com%2Fdocker">Docker Guide</a><span class="result__snippet">Learn Docker basics fast.</span></div></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $tool = new WebSearchTool();
        $payload = $tool->execute([
            'query' => 'docker basics',
            'limit' => 3,
        ])->payload;

        $this->assertTrue($payload['ok']);
        $this->assertSame('Docker Guide', $payload['results'][0]['title']);
        $this->assertSame('https://example.com/docker', $payload['results'][0]['url']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://html.duckduckgo.com/html/');
    }

    public function test_fetch_url_reads_html_and_removes_layout_noise(): void
    {
        Http::fake([
            'https://example.com/article' => Http::response(
                '<html><body><header>Navigation</header><main><h1>Docker Guide</h1><p>Useful content.</p></main><script>console.log("ignore")</script></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
        ]);

        $tool = new FetchUrlTool();
        $payload = $tool->execute([
            'url' => 'https://example.com/article',
            'maxLength' => 500,
        ])->payload;

        $this->assertTrue($payload['ok']);
        $this->assertStringContainsString('Docker Guide', $payload['content']);
        $this->assertStringContainsString('Useful content.', $payload['content']);
        $this->assertStringNotContainsString('Navigation', $payload['content']);
        $this->assertStringNotContainsString('console.log', $payload['content']);
    }

    public function test_web_search_falls_back_to_instant_answer_api(): void
    {
        Http::fake([
            'https://html.duckduckgo.com/html/' => Http::response('<html><body>No matches</body></html>', 200),
            'https://www.bing.com/search*' => Http::response('<html><body>No matches</body></html>', 200),
            'https://api.duckduckgo.com/*' => Http::response([
                'Heading' => 'Docker',
                'AbstractText' => 'Docker is a platform for containers.',
                'AbstractURL' => 'https://www.docker.com/',
                'RelatedTopics' => [],
            ], 200),
        ]);

        $tool = new WebSearchTool();
        $payload = $tool->execute([
            'query' => 'what is docker',
        ])->payload;

        $this->assertTrue($payload['ok']);
        $this->assertSame('Docker', $payload['results'][0]['title']);
    }

    public function test_web_search_falls_back_to_bing_when_duckduckgo_is_challenged(): void
    {
        Http::fake([
            'https://html.duckduckgo.com/html/' => Http::response('<html><body><script src="/anomaly.js"></script></body></html>', 202),
            'https://www.bing.com/search*' => Http::response(
                '<?xml version="1.0" encoding="utf-8" ?><rss version="2.0"><channel><item><title>Docker Docs</title><link>https://docs.docker.com/</link><description>Official Docker documentation.</description></item></channel></rss>',
                200
            ),
        ]);

        $tool = new WebSearchTool();
        $payload = $tool->execute([
            'query' => 'Docker official docs',
            'limit' => 2,
        ])->payload;

        $this->assertTrue($payload['ok']);
        $this->assertSame('Docker Docs', $payload['results'][0]['title']);
        $this->assertSame('https://docs.docker.com/', $payload['results'][0]['url']);
    }

    public function test_web_fetch_returns_clear_message_for_linkedin_blocks(): void
    {
        Http::fake([
            'https://www.linkedin.com/*' => Http::response('blocked', 403),
        ]);

        $tool = new WebFetchTool();
        $payload = $tool->execute([
            'url' => 'https://www.linkedin.com/in/example',
        ])->payload;

        $this->assertFalse($payload['ok']);
        $this->assertStringContainsString('LinkedIn blocked direct page fetching', $payload['message']);
    }
}
