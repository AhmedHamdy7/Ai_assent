<?php

namespace App\AI\Tool\Expense;

use App\AI\Provider\ToolResult;
use App\Models\AiExpense;
use Carbon\Carbon;

class AddExpenseTool extends AbstractExpenseTool
{
    public function getName(): string
    {
        return 'add_expense';
    }

    public function eventAction(): string
    {
        return 'Adding an expense record';
    }

    public function getDescription(): string
    {
        return 'Add a new expense record for the current chat session.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'amount' => ['type' => 'number', 'minimum' => 0.01],
                'category' => ['type' => 'string', 'description' => 'Expense category like transport, food, bills'],
                'note' => ['type' => 'string', 'description' => 'Optional note'],
                'spent_at' => ['type' => 'string', 'description' => 'Optional date/time (ISO or natural date). Defaults to now.'],
            ],
            'required' => ['amount', 'category'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        $amount = (float) ($arguments['amount'] ?? 0);
        if ($amount <= 0) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'amount must be greater than 0']);
        }

        $category = $this->cleanText((string) ($arguments['category'] ?? ''), 120);
        if ($category === null) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'category is required']);
        }

        $spentAt = $this->parseDateTime(
            isset($arguments['spent_at']) ? (string) $arguments['spent_at'] : null,
            Carbon::now()
        );

        if ($spentAt === null) {
            return ToolResult::fromPayload(['ok' => false, 'message' => 'Invalid spent_at format']);
        }

        $rawSpentAt = isset($arguments['spent_at']) ? trim((string) $arguments['spent_at']) : '';
        if ($rawSpentAt !== '' && ! $this->hasExplicitTime($rawSpentAt) && $spentAt->format('H:i:s') === '00:00:00') {
            // Avoid DST-invalid midnight timestamps (e.g. DST switch days).
            $spentAt = $spentAt->copy()->setTime(12, 0, 0);
        }

        $expense = AiExpense::query()->create([
            'ai_session_id' => $this->resolveSessionId($context),
            'amount' => round($amount, 2),
            'category' => $category,
            'note' => $this->cleanText(isset($arguments['note']) ? (string) $arguments['note'] : null, 500),
            'spent_at' => $spentAt,
        ]);

        $currency = $this->currency();

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => "Expense #{$expense->id} added: {$expense->amount} {$currency} - {$expense->category}",
            'expense' => $this->serializeExpense($expense),
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

    private function hasExplicitTime(string $value): bool
    {
        $normalized = strtolower($value);

        if (preg_match('/\d{1,2}:\d{2}(:\d{2})?/', $normalized) === 1) {
            return true;
        }

        if (str_contains($normalized, 't')) {
            return true;
        }

        if (
            str_contains($normalized, 'am')
            || str_contains($normalized, 'pm')
            || str_contains($normalized, 'ص')
            || str_contains($normalized, 'م')
        ) {
            return true;
        }

        return false;
    }
}
