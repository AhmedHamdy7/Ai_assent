<?php

namespace App\AI\Tool;

use App\AI\Learning\CurriculumBuilder;
use App\AI\Tool\Expense\AddExpenseTool;
use App\AI\Tool\Expense\ExpenseSummaryTool;
use App\AI\Tool\Expense\ListExpensesTool;
use App\AI\Tool\Learning\CreateLearningTrackTool;
use App\AI\Tool\Learning\GetLearningLessonTool;
use App\AI\Tool\Learning\GetLearningProgressTool;
use App\AI\Tool\Learning\ListLearningTracksTool;
use App\AI\Tool\Learning\SubmitLearningQuizTool;
use App\AI\Tool\Session\StartNewSessionTool;
use App\AI\Tool\Task\CreateTaskTool;
use App\AI\Tool\Task\DeleteTaskTool;
use App\AI\Tool\Task\GetTaskTool;
use App\AI\Tool\Task\ListTasksTool;
use App\AI\Tool\Task\UpdateTaskTool;
use App\AI\Tool\Reminder\CreateReminderTool;
use App\AI\Tool\Reminder\DeleteReminderTool;
use App\AI\Tool\Reminder\ListRemindersTool;
use App\AI\Tool\Web\FetchUrlTool;
use App\AI\Tool\Web\WebSearchTool;

class ToolRegistry
{
    public function __construct(
        private readonly CurriculumBuilder $curriculumBuilder = new CurriculumBuilder(),
        private readonly int $webSearchTimeoutSeconds = 15,
        private readonly int $webSearchMaxResults = 8,
        private readonly int $webFetchTimeoutSeconds = 15,
        private readonly int $webFetchMaxContentChars = 20000,
    ) {}

    /**
     * @return BaseTool[]
     */
    public function all(): array
    {
        return [
            new StartNewSessionTool(),
            new CreateTaskTool(),
            new ListTasksTool(),
            new GetTaskTool(),
            new UpdateTaskTool(),
            new DeleteTaskTool(),
            new AddExpenseTool(),
            new ListExpensesTool(),
            new ExpenseSummaryTool(),
            new CreateLearningTrackTool($this->curriculumBuilder),
            new ListLearningTracksTool(),
            new GetLearningLessonTool(),
            new SubmitLearningQuizTool(),
            new GetLearningProgressTool(),
            new CreateReminderTool(),
            new ListRemindersTool(),
            new DeleteReminderTool(),
            new WebSearchTool(
                timeoutSeconds: $this->webSearchTimeoutSeconds,
                maxResults: $this->webSearchMaxResults,
            ),
            new FetchUrlTool(
                timeoutSeconds: $this->webFetchTimeoutSeconds,
                maxContentChars: $this->webFetchMaxContentChars,
            ),
        ];
    }
}
