<?php

namespace App\AI\Tool\Expense;

use App\AI\Provider\ToolResult;
use App\Models\AiExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExpenseSummaryTool extends AbstractExpenseTool
{
    public function getName(): string
    {
        return 'expense_summary';
    }

    public function eventAction(): string
    {
        return 'Generating an expense summary';
    }

    public function getDescription(): string
    {
        return 'Generate a monthly expense summary: total spent, top category, and saving suggestion.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'month' => ['type' => 'string', 'description' => 'Optional month like 2026-04. Defaults to current month.'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, array $context = []): ToolResult
    {
        [$start, $end] = $this->resolveMonthRange(isset($arguments['month']) ? (string) $arguments['month'] : null);
        $sessionId = $this->resolveSessionId($context);

        $baseQuery = AiExpense::query()
            ->forSession($sessionId)
            ->whereBetween('spent_at', [$start, $end]);

        $total = (float) (clone $baseQuery)->sum('amount');
        $count = (int) (clone $baseQuery)->count();
        $currency = $this->currency();

        if ($count === 0) {
            return ToolResult::fromPayload([
                'ok' => true,
                'message' => "No expenses recorded for {$start->format('Y-m')} yet.",
                'summary' => [
                    'month' => $start->format('Y-m'),
                    'total_spent' => 0.0,
                    'currency' => $currency,
                    'top_category' => null,
                    'save_tip' => 'Start by recording every expense this month to get accurate advice.',
                ],
            ]);
        }

        $topCategoryRow = AiExpense::query()
            ->forSession($sessionId)
            ->select('category', DB::raw('SUM(amount) as total_amount'))
            ->whereBetween('spent_at', [$start, $end])
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->first();

        $topCategory = $topCategoryRow?->category;
        $topCategoryTotal = $topCategoryRow !== null ? (float) $topCategoryRow->total_amount : 0.0;

        $avgPerDay = $total / max(1, $start->diffInDays($end) + 1);
        $today = Carbon::now();
        $daysRemaining = $today->between($start, $end) ? max(0, $today->diffInDays($end)) : 0;
        $projectedEndMonth = $today->between($start, $end)
            ? round($total + ($avgPerDay * $daysRemaining), 2)
            : $total;

        $suggestedSaveAmount = round(max($topCategoryTotal * 0.15, $total * 0.05), 2);
        $saveTip = $this->buildSaveTip($topCategory, $suggestedSaveAmount, $currency);

        return ToolResult::fromPayload([
            'ok' => true,
            'message' => "Summary for {$start->format('Y-m')}: spent {$total} {$currency}.",
            'summary' => [
                'month' => $start->format('Y-m'),
                'total_spent' => $total,
                'currency' => $currency,
                'records_count' => $count,
                'top_category' => $topCategory !== null ? [
                    'name' => $topCategory,
                    'total' => $topCategoryTotal,
                ] : null,
                'projected_end_month_total' => $projectedEndMonth,
                'save_suggestion_amount' => $suggestedSaveAmount,
                'save_tip' => $saveTip,
            ],
        ]);
    }

    private function buildSaveTip(?string $topCategory, float $saveAmount, string $currency): string
    {
        if ($topCategory === null) {
            return "Try to save around {$saveAmount} {$currency} by reducing non-essential daily spending.";
        }

        return "Top spending is {$topCategory}. Try to cut it by about {$saveAmount} {$currency} this month to save more.";
    }
}
