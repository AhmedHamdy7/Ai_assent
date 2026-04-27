<?php

namespace App\AI\Tool;

use App\AI\Tool\Expense\AddExpenseTool;
use App\AI\Tool\Expense\ExpenseSummaryTool;
use App\AI\Tool\Expense\ListExpensesTool;
use App\AI\Tool\Task\CreateTaskTool;
use App\AI\Tool\Task\DeleteTaskTool;
use App\AI\Tool\Task\GetTaskTool;
use App\AI\Tool\Task\ListTasksTool;
use App\AI\Tool\Task\UpdateTaskTool;
use App\AI\Tool\Web\WebFetchTool;
use App\AI\Tool\Web\WebSearchTool;

class ToolRegistry
{
    public function __construct(
        private readonly int $webSearchTimeoutSeconds = 12,
        private readonly int $webSearchMaxResults = 5,
        private readonly int $webFetchTimeoutSeconds = 15,
        private readonly int $webFetchMaxContentChars = 12000,
    ) {}

    /**
     * @return BaseTool[]
     */
    public function all(): array
    {
        return [
            new CreateTaskTool(),
            new ListTasksTool(),
            new GetTaskTool(),
            new UpdateTaskTool(),
            new DeleteTaskTool(),
            new AddExpenseTool(),
            new ListExpensesTool(),
            new ExpenseSummaryTool(),
            new WebSearchTool(
                timeoutSeconds: $this->webSearchTimeoutSeconds,
                maxResults: $this->webSearchMaxResults,
            ),
            new WebFetchTool(
                timeoutSeconds: $this->webFetchTimeoutSeconds,
                maxContentChars: $this->webFetchMaxContentChars,
            ),
        ];
    }
}
