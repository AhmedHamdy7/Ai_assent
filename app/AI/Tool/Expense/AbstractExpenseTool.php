<?php

namespace App\AI\Tool\Expense;

use App\AI\Tool\BaseTool;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

abstract class AbstractExpenseTool extends BaseTool
{
    protected function resolveSessionId(array $context): ?int
    {
        $sessionId = Arr::get($context, 'ai_session_id');

        return is_numeric($sessionId) ? (int) $sessionId : null;
    }

    protected function cleanText(?string $value, int $limit = 255): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $clean = trim($value);
        if ($clean === '') {
            return null;
        }

        return Str::limit($clean, $limit, '...');
    }

    protected function parseDateTime(?string $value, ?Carbon $fallback = null): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return $fallback;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveMonthRange(?string $month): array
    {
        $base = null;

        if (is_string($month) && trim($month) !== '') {
            $normalized = trim($month);

            try {
                if (preg_match('/^\d{4}-\d{2}$/', $normalized) === 1) {
                    $base = Carbon::createFromFormat('Y-m', $normalized)->startOfMonth();
                } else {
                    $base = Carbon::parse($normalized)->startOfMonth();
                }
            } catch (\Throwable) {
                $base = null;
            }
        }

        $start = ($base ?? Carbon::now())->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [$start, $end];
    }

    protected function currency(): string
    {
        return (string) config('services.ai_tools.expense_currency', 'EGP');
    }
}
