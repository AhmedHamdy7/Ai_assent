<?php

namespace App\AI\Tool\Expense;

use App\AI\Provider\ToolResult;
use App\Models\AiExpense;

class ListExpensesTool extends AbstractExpenseTool
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
        return 'List expenses for a month in the current chat session.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'month' => ['type' => 'string', 'description' => 'Optional month like 2026-04. Defaults to current month.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        [$start, $end] = $this->resolveMonthRange(isset($arguments['month']) ? (string) $arguments['month'] : null);
        $limit = (int) ($arguments['limit'] ?? 30);
        $limit = max(1, min($limit, 100));

        $expenses = AiExpense::query()
            ->forSession($this->resolveSessionId($context))
            ->whereBetween('spent_at', [$start, $end])
            ->orderByDesc('spent_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $total = (float) $expenses->sum('amount');
        $currency = $this->currency();

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => $expenses->isEmpty()
                ? "No expenses found for {$start->format('Y-m')}."
                : "Found {$expenses->count()} expense(s), total {$total} {$currency}.",
            'month' => $start->format('Y-m'),
            'total' => $total,
            'currency' => $currency,
            'expenses' => $expenses->map(fn (AiExpense $expense) => $this->serializeExpense($expense))->values()->all(),
        ]);
    }

    private function serializeExpense(AiExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'amount' => (float) $expense->amount,
            'category' => $expense->category,
            'note' => $expense->note,
            'spent_at' => optional($expense->spent_at)->toIso8601String(),
        ];
    }
}
