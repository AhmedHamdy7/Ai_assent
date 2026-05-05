<?php

namespace Tests\Feature\AI;

use App\AI\Agent\BaseAgent;
use App\AI\Orchestration\Orchestrator;
use App\AI\Provider\AiMessage;
use App\AI\Provider\AiModel;
use App\AI\Provider\AiSession;
use App\AI\Provider\AiToolCall;
use App\AI\Provider\BaseProvider;
use App\AI\Provider\ToolResult;
use App\AI\Tool\BaseTool;
use App\AI\Tool\Expense\ListExpensesTool;
use App\Models\AiExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

class ExpenseToolFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_expenses_returns_individual_records(): void
    {
        $session = AiSession::query()->create([
            'title' => 'Expenses',
            'token' => 'session-1',
        ]);

        AiExpense::query()->create([
            'ai_session_id' => $session->id,
            'amount' => 300,
            'category' => 'fuel',
            'note' => 'car',
            'spent_at' => '2026-05-03 12:00:00',
        ]);

        AiExpense::query()->create([
            'ai_session_id' => $session->id,
            'amount' => 150,
            'category' => 'food',
            'note' => 'lunch',
            'spent_at' => '2026-05-02 10:00:00',
        ]);

        $tool = new ListExpensesTool();
        $payload = $tool->execute([
            'month' => '2026-05',
            'limit' => 10,
        ], [
            'ai_session_id' => $session->id,
        ])->payload;

        $this->assertTrue($payload['ok']);
        $this->assertSame('2026-05', $payload['month']);
        $this->assertSame(2, $payload['records_count']);
        $this->assertCount(2, $payload['expenses']);
        $this->assertSame('fuel', $payload['expenses'][0]['category']);
        $this->assertSame(450.0, $payload['total']);
    }

    public function test_orchestrator_sends_full_tool_payload_back_to_the_model(): void
    {
        $provider = new class extends BaseProvider
        {
            public array $capturedPayloads = [];

            public function __construct()
            {
                parent::__construct(30);
            }

            public function askAiFuture(BaseAgent $agent, string $modelName, array $messages): AiModel
            {
                if (count($messages) === 1) {
                    return new AiModel(
                        model: 'fake-model',
                        role: 'assistant',
                        content: '',
                        toolCalls: [new AiToolCall('list_expenses', ['month' => '2026-05'], 'call-1')],
                        done: false,
                    );
                }

                $lastMessage = $messages[array_key_last($messages)];
                Assert::assertSame('tool', $lastMessage->role);

                $decoded = json_decode((string) $lastMessage->content, true);
                Assert::assertIsArray($decoded);
                $this->capturedPayloads[] = $decoded;

                return new AiModel(
                    model: 'fake-model',
                    role: 'assistant',
                    content: 'done',
                    toolCalls: [],
                    done: true,
                );
            }
        };

        $tool = new class extends BaseTool
        {
            public function getName(): string
            {
                return 'list_expenses';
            }

            public function eventAction(): string
            {
                return 'Listing expense records';
            }

            public function getDescription(): string
            {
                return 'List individual expenses.';
            }

            public function getParameters(): array
            {
                return [
                    'type' => 'object',
                    'properties' => [],
                ];
            }

            public function execute(array $arguments, array $context = []): ToolResult
            {
                return ToolResult::fromPayload([
                    'ok' => true,
                    'message' => 'Found 2 expense(s), total 450 EGP.',
                    'month' => '2026-05',
                    'records_count' => 2,
                    'expenses' => [
                        ['amount' => 300, 'category' => 'fuel'],
                        ['amount' => 150, 'category' => 'food'],
                    ],
                ]);
            }
        };

        $agent = new class($tool) extends BaseAgent
        {
            public function __construct(private BaseTool $tool) {}

            public function getInstruction(): string
            {
                return 'Use the tool.';
            }

            public function getTools(): array
            {
                return [$this->tool];
            }
        };

        $orchestrator = new Orchestrator($provider, $agent, 'fake-model');
        $reply = $orchestrator->askAi([
            AiMessage::user('show me expense details'),
        ]);

        $this->assertSame('done', $reply);
        $this->assertCount(1, $provider->capturedPayloads);
        $this->assertSame(2, $provider->capturedPayloads[0]['records_count']);
        $this->assertCount(2, $provider->capturedPayloads[0]['expenses']);
        $this->assertSame('Found 2 expense(s), total 450 EGP.', $provider->capturedPayloads[0]['message']);
    }
}
