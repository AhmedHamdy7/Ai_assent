<?php

namespace App\Http\Controllers;

use App\AI\Agent\BaseAgent;
use App\AI\Orchestration\Orchestrator;
use App\AI\Provider\AiMessage;
use App\AI\Provider\AiRoleEnum;
use App\AI\Provider\AiSession;
use App\AI\Provider\BaseProvider;
use App\AI\Tool\ToolRegistry;
use App\Models\AiExpense;
use App\Models\AiLearningLesson;
use App\Models\AiLearningQuizAttempt;
use App\Models\AiLearningTrack;
use App\Models\AiReminder;
use App\Models\AiTask;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return view('dashboard', [
            'bootData' => $this->collectAll(),
        ]);
    }

    /**
     * POST /dashboard/chat — send a message and get an AI reply.
     * Body: { session_id?: int|null, text: string }
     * If session_id is null/0, a new dashboard session is created.
     */
    public function chatSend(Request $request, BaseProvider $provider, BaseAgent $agent): JsonResponse
    {
        $data = $request->validate([
            'session_id' => 'nullable|integer',
            'text'       => 'required|string|max:8000',
        ]);

        $text = trim((string) $data['text']);
        if ($text === '') {
            return response()->json(['ok' => false, 'message' => 'empty message'], 422);
        }

        $session = !empty($data['session_id'])
            ? AiSession::query()->find($data['session_id'])
            : null;

        if ($session === null) {
            $session = AiSession::query()->create([
                'title' => 'Dashboard chat · ' . now()->format('Y-m-d H:i'),
            ]);
        }

        $userMessage = AiMessage::user($text);
        $userMessage->forceFill(['ai_session_id' => $session->id])->save();

        $modelName    = (string) config('services.ollama.model', 'minimax-m2.5:cloud');
        $historyLimit = (int) config('services.telegram.history_limit', 30);

        $query = $session->messages();
        $contextStartAfter = (int) ($session->context_starts_after_message_id ?? 0);
        if ($contextStartAfter > 0) {
            $query->where('id', '>', $contextStartAfter);
        }

        $messages = $query
            ->orderByDesc('id')
            ->limit($historyLimit)
            ->get()
            ->reverse()
            ->values()
            ->all();

        $orchestrator = new Orchestrator($provider, $agent, $modelName);

        try {
            $reply = $orchestrator->askAi($messages, [
                'ai_session_id'    => (int) $session->id,
                'telegram_chat_id' => $session->telegram_chat_id !== null ? (string) $session->telegram_chat_id : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('dashboard.chat_failed', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'حصل خطأ أثناء تنفيذ الطلب: ' . $e->getMessage(),
                'session_id' => $session->id,
                'user_message' => $this->serializeMessage($userMessage->fresh()),
            ], 500);
        }

        $reply = trim($reply) === '' ? '(no response)' : trim($reply);

        $assistantMessage = AiMessage::assistant($reply);
        $assistantMessage->forceFill(['ai_session_id' => $session->id])->save();

        $session->touch();

        return response()->json([
            'ok'                 => true,
            'session_id'         => $session->id,
            'session_title'      => $session->title,
            'user_message'       => $this->serializeMessage($userMessage->fresh()),
            'assistant_message'  => $this->serializeMessage($assistantMessage->fresh()),
        ]);
    }

    /**
     * POST /dashboard/sessions/new — create an empty dashboard session.
     */
    public function newSession(Request $request): JsonResponse
    {
        $session = AiSession::query()->create([
            'title' => 'Dashboard chat · ' . now()->format('Y-m-d H:i'),
        ]);

        return response()->json([
            'ok'         => true,
            'session_id' => $session->id,
            'title'      => $session->title,
        ]);
    }

    private function serializeMessage(AiMessage $m): array
    {
        return [
            'id'         => $m->id,
            'role'       => $m->role,
            'who'        => $this->roleLabel($m->role, $m->tool_name),
            'text'       => $this->snippet($m->content, 4000),
            't'          => optional($m->created_at)->format('H:i'),
            'date'       => optional($m->created_at)->toDateString(),
            'tool'       => $m->tool_name,
            'tool_calls' => $m->tool_calls,
        ];
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->collectAll());
    }

    private function collectAll(): array
    {
        return [
            'meta'     => $this->meta(),
            'overview' => $this->overview(),
            'agents'   => $this->agents(),
            'telegram' => $this->telegram(),
            'queue'    => $this->queue(),
            'cost'     => $this->cost(),
            'tasks'    => $this->tasks(),
            'reminders'=> $this->reminders(),
            'learning' => $this->learning(),
            'sessions' => $this->sessions(),
            'memory'   => $this->memory(),
        ];
    }

    private function meta(): array
    {
        return [
            'app_name'   => config('app.name', 'Hamdix'),
            'env'        => app()->environment(),
            'laravel'    => app()->version(),
            'php'        => PHP_VERSION,
            'now'        => now()->toIso8601String(),
            'timezone'   => config('app.timezone'),
            'webhook'    => config('services.telegram.webhook_path', 'telegram/webhook'),
            'bot_user'   => config('services.telegram.bot_username'),
            'model'      => config('services.ollama.model') ?: config('services.ai.model'),
            'provider'   => 'ollama',
        ];
    }

    private function overview(): array
    {
        $today = CarbonImmutable::today();
        $weekStart = $today->subDays(6)->startOfDay();

        $messagesToday = AiMessage::query()
            ->whereDate('created_at', $today)
            ->count();

        $messagesByDay = AiMessage::query()
            ->where('created_at', '>=', $weekStart)
            ->selectRaw("DATE(created_at) as d, COUNT(*) as c")
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('c', 'd')
            ->toArray();

        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $today->subDays($i)->toDateString();
            $series[] = [
                'day' => $d,
                'count' => (int) ($messagesByDay[$d] ?? 0),
            ];
        }

        $sessionsActive = AiSession::query()
            ->whereNotNull('telegram_chat_id')
            ->count();

        $tasksOpen = AiTask::query()
            ->whereIn('status', [AiTask::STATUS_PENDING, AiTask::STATUS_IN_PROGRESS])
            ->count();

        $remindersActive = AiReminder::query()->where('is_active', true)->count();

        $expenseToday = (float) AiExpense::query()
            ->whereDate('spent_at', $today)
            ->sum('amount');

        $expenseWeek = (float) AiExpense::query()
            ->where('spent_at', '>=', $weekStart)
            ->sum('amount');

        $recentActivity = AiMessage::query()
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (AiMessage $m) => [
                'who'  => $this->roleLabel($m->role, $m->tool_name),
                'what' => $this->snippet($m->content, 90),
                't'    => optional($m->created_at)->diffForHumans(),
                'tone' => $this->roleTone($m->role),
                'role' => $m->role,
            ])
            ->values()
            ->toArray();

        return [
            'messages_today'  => $messagesToday,
            'messages_series' => $series,
            'sessions_active' => $sessionsActive,
            'tasks_open'      => $tasksOpen,
            'reminders_active'=> $remindersActive,
            'expense_today'   => $expenseToday,
            'expense_week'    => $expenseWeek,
            'recent_activity' => $recentActivity,
        ];
    }

    private function agents(): array
    {
        $registry = app(ToolRegistry::class);

        $callsByName = AiMessage::query()
            ->where('role', AiRoleEnum::Tool->value)
            ->whereNotNull('tool_name')
            ->where('created_at', '>=', now()->subHours(24))
            ->select('tool_name', DB::raw('COUNT(*) as c'))
            ->groupBy('tool_name')
            ->pluck('c', 'tool_name')
            ->toArray();

        $totalCalls = max(1, array_sum($callsByName));

        $tones = ['cyan', 'azure', 'pos', 'warn', 'aurora'];
        $i = 0;
        $tools = [];

        foreach ($registry->all() as $tool) {
            $name = $tool->getName();
            $calls = (int) ($callsByName[$name] ?? 0);
            $tools[] = [
                'name'        => $name,
                'role'        => $this->toolRole($name),
                'description' => $tool->getDescription(),
                'event'       => $tool->eventAction(),
                'tone'        => $tones[$i % count($tones)],
                'calls_24h'   => $calls,
                'load'        => (int) round(($calls / $totalCalls) * 100),
                'status'      => $calls > 0 ? 'active' : 'idle',
            ];
            $i++;
        }

        return [
            'tools'      => $tools,
            'total_24h'  => array_sum($callsByName),
            'tool_count' => count($tools),
        ];
    }

    private function telegram(): array
    {
        $sessions = AiSession::query()
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get();

        $chats = $sessions->map(function (AiSession $s) {
            $lastMessage = AiMessage::query()
                ->where('ai_session_id', $s->id)
                ->latest('id')
                ->first();

            return [
                'id'              => $s->id,
                'chat_id'         => $s->telegram_chat_id,
                'is_telegram'     => $s->telegram_chat_id !== null,
                'title'           => $s->title ?: ($s->telegram_chat_id ? 'chat · ' . $s->telegram_chat_id : 'session #' . $s->id),
                'message_count'   => $s->messages_count,
                'last'            => $lastMessage ? $this->snippet($lastMessage->content, 80) : '—',
                'last_role'       => $lastMessage?->role,
                't'               => optional($s->updated_at)->diffForHumans(),
                'tone'            => $s->telegram_chat_id ? 'cyan' : 'azure',
            ];
        })->values()->toArray();

        $selectedId = $sessions->first()->id ?? null;
        $messages = $selectedId ? $this->loadMessages($selectedId) : [];

        $messagesPerMinute = AiMessage::query()
            ->where('created_at', '>=', now()->subMinutes(60))
            ->count();

        return [
            'chats'              => $chats,
            'selected_id'        => $selectedId,
            'messages'           => $messages,
            'messages_per_min'   => $messagesPerMinute > 0 ? round($messagesPerMinute / 60, 2) : 0,
            'webhook_path'       => config('services.telegram.webhook_path', 'telegram/webhook'),
            'bot_username'       => config('services.telegram.bot_username'),
            'total_sessions'     => AiSession::query()->count(),
            'telegram_sessions'  => AiSession::query()->whereNotNull('telegram_chat_id')->count(),
        ];
    }

    /**
     * Endpoint: GET /dashboard/sessions/{id}/messages — fetch messages for any session.
     */
    public function sessionMessages(Request $request, int $id): JsonResponse
    {
        $session = AiSession::query()->findOrFail($id);

        return response()->json([
            'session_id'  => $session->id,
            'title'       => $session->title,
            'chat_id'     => $session->telegram_chat_id,
            'is_telegram' => $session->telegram_chat_id !== null,
            'messages'    => $this->loadMessages($session->id, 80),
        ]);
    }

    private function loadMessages(int $sessionId, int $limit = 30): array
    {
        return AiMessage::query()
            ->where('ai_session_id', $sessionId)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AiMessage $m) => [
                'id'         => $m->id,
                'role'       => $m->role,
                'who'        => $this->roleLabel($m->role, $m->tool_name),
                'text'       => $this->snippet($m->content, 1200),
                't'          => optional($m->created_at)->format('H:i'),
                'date'       => optional($m->created_at)->toDateString(),
                'tool'       => $m->tool_name,
                'tool_calls' => $m->tool_calls,
            ])
            ->toArray();
    }

    private function queue(): array
    {
        $jobs = [];
        $queues = [];
        $failed = 0;

        if (Schema::hasTable('jobs')) {
            $rawJobs = DB::table('jobs')
                ->orderByDesc('id')
                ->limit(15)
                ->get();

            foreach ($rawJobs as $j) {
                $payload = json_decode($j->payload ?? '{}', true);
                $name = $payload['displayName'] ?? ($payload['data']['commandName'] ?? 'job');
                $jobs[] = [
                    'id'      => 'job_' . $j->id,
                    'q'       => $j->queue,
                    'job'     => class_basename($name),
                    'attempts'=> (int) $j->attempts,
                    'reserved'=> (bool) $j->reserved_at,
                    'status'  => $j->reserved_at ? 'running' : 'queued',
                    'tone'    => $j->reserved_at ? 'cyan' : 'azure',
                ];
            }

            $queueRows = DB::table('jobs')
                ->select('queue', DB::raw('COUNT(*) as depth'))
                ->groupBy('queue')
                ->get();

            foreach ($queueRows as $row) {
                $queues[] = [
                    'name'  => $row->queue,
                    'depth' => (int) $row->depth,
                    'tone'  => $row->depth > 10 ? 'warn' : 'cyan',
                ];
            }
        }

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->count();
        }

        if ($queues === []) {
            $queues[] = ['name' => 'default', 'depth' => 0, 'tone' => 'pos'];
        }

        $totalDepth = array_sum(array_column($queues, 'depth'));

        return [
            'queues'     => $queues,
            'jobs'       => $jobs,
            'total_depth'=> $totalDepth,
            'failed'     => $failed,
            'driver'     => config('queue.default'),
        ];
    }

    private function cost(): array
    {
        $today = CarbonImmutable::today();
        $weekStart = $today->subDays(6)->startOfDay();
        $monthStart = $today->startOfMonth();

        $totalToday = (float) AiExpense::query()->whereDate('spent_at', $today)->sum('amount');
        $totalWeek  = (float) AiExpense::query()->where('spent_at', '>=', $weekStart)->sum('amount');
        $totalMonth = (float) AiExpense::query()->where('spent_at', '>=', $monthStart)->sum('amount');

        $byCategory = AiExpense::query()
            ->select('category', DB::raw('SUM(amount) as total'))
            ->where('spent_at', '>=', $monthStart)
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $totalForBreakdown = max(0.0001, (float) $byCategory->sum('total'));
        $tones = ['cyan', 'azure', 'aurora', 'warn', 'pos', 'danger'];
        $breakdown = [];
        foreach ($byCategory as $i => $row) {
            $cost = (float) $row->total;
            $breakdown[] = [
                'name' => $row->category ?: 'uncategorized',
                'cost' => $cost,
                'pct'  => (int) round(($cost / $totalForBreakdown) * 100),
                'tone' => $tones[$i % count($tones)],
            ];
        }

        $weekSeries = [];
        $byDay = AiExpense::query()
            ->select(DB::raw("DATE(spent_at) as d"), DB::raw('SUM(amount) as t'))
            ->where('spent_at', '>=', $weekStart)
            ->groupBy('d')
            ->pluck('t', 'd')
            ->toArray();

        for ($i = 6; $i >= 0; $i--) {
            $d = $today->subDays($i)->toDateString();
            $weekSeries[] = (float) ($byDay[$d] ?? 0);
        }

        $recent = AiExpense::query()
            ->latest('spent_at')
            ->limit(8)
            ->get()
            ->map(fn (AiExpense $e) => [
                'amount'   => (float) $e->amount,
                'category' => $e->category,
                'note'     => $e->note,
                'spent_at' => optional($e->spent_at)->toDateTimeString(),
            ])
            ->toArray();

        return [
            'today'      => $totalToday,
            'week'       => $totalWeek,
            'month'      => $totalMonth,
            'budget_day' => 25.00,
            'breakdown'  => $breakdown,
            'week_series'=> $weekSeries,
            'recent'     => $recent,
        ];
    }

    private function tasks(): array
    {
        $byStatus = AiTask::query()
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        $upcoming = AiTask::query()
            ->whereIn('status', [AiTask::STATUS_PENDING, AiTask::STATUS_IN_PROGRESS])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END, due_at ASC')
            ->limit(10)
            ->get()
            ->map(fn (AiTask $t) => [
                'id'       => $t->id,
                'title'    => $t->title,
                'details'  => $t->details,
                'status'   => $t->status,
                'priority' => $t->priority,
                'due_at'   => optional($t->due_at)->toDateTimeString(),
                'due_human'=> optional($t->due_at)->diffForHumans(),
            ])
            ->toArray();

        $today = AiTask::query()
            ->whereDate('due_at', CarbonImmutable::today())
            ->orderBy('due_at')
            ->get()
            ->map(fn (AiTask $t) => [
                'id'       => $t->id,
                'title'    => $t->title,
                'time'     => optional($t->due_at)->format('H:i'),
                'status'   => $t->status,
                'priority' => $t->priority,
            ])
            ->toArray();

        return [
            'pending'     => (int) ($byStatus[AiTask::STATUS_PENDING] ?? 0),
            'in_progress' => (int) ($byStatus[AiTask::STATUS_IN_PROGRESS] ?? 0),
            'done'        => (int) ($byStatus[AiTask::STATUS_DONE] ?? 0),
            'cancelled'   => (int) ($byStatus[AiTask::STATUS_CANCELLED] ?? 0),
            'upcoming'    => $upcoming,
            'today'       => $today,
        ];
    }

    private function reminders(): array
    {
        $reminders = AiReminder::query()
            ->where('is_active', true)
            ->orderBy('remind_at')
            ->limit(20)
            ->get()
            ->map(fn (AiReminder $r) => [
                'id'           => $r->id,
                'message'      => $r->message,
                'frequency'    => $r->frequency,
                'time_of_day'  => $r->time_of_day,
                'day_of_week'  => $r->day_of_week,
                'remind_at'    => optional($r->remind_at)->toDateTimeString(),
                'remind_human' => optional($r->remind_at)->diffForHumans(),
                'last_sent_at' => optional($r->last_sent_at)->toDateTimeString(),
            ])
            ->toArray();

        $byFreq = AiReminder::query()
            ->where('is_active', true)
            ->select('frequency', DB::raw('COUNT(*) as c'))
            ->groupBy('frequency')
            ->pluck('c', 'frequency')
            ->toArray();

        return [
            'list'   => $reminders,
            'active' => array_sum($byFreq),
            'once'   => (int) ($byFreq[AiReminder::FREQUENCY_ONCE] ?? 0),
            'daily'  => (int) ($byFreq[AiReminder::FREQUENCY_DAILY] ?? 0),
            'weekly' => (int) ($byFreq[AiReminder::FREQUENCY_WEEKLY] ?? 0),
        ];
    }

    private function learning(): array
    {
        $tracks = AiLearningTrack::query()
            ->withCount(['lessons', 'quizAttempts'])
            ->orderByDesc('last_activity_at')
            ->limit(10)
            ->get()
            ->map(function (AiLearningTrack $tr) {
                $lessonsDone = AiLearningLesson::query()
                    ->where('track_id', $tr->id)
                    ->whereNotNull('completed_at')
                    ->count();

                return [
                    'id'         => $tr->id,
                    'topic'      => $tr->topic,
                    'goal'       => $tr->goal,
                    'level'      => $tr->level,
                    'status'     => $tr->status,
                    'duration'   => $tr->duration_days,
                    'daily_min'  => $tr->daily_minutes,
                    'lessons'    => $tr->lessons_count,
                    'lessons_done' => $lessonsDone,
                    'quizzes'    => $tr->quiz_attempts_count,
                    'started_at' => optional($tr->started_at)->toDateTimeString(),
                    'last_activity' => optional($tr->last_activity_at)->diffForHumans(),
                ];
            })
            ->toArray();

        $totalLessons = AiLearningLesson::query()->count();
        $doneLessons  = AiLearningLesson::query()->whereNotNull('completed_at')->count();
        $quizzes      = AiLearningQuizAttempt::query()->count();
        $quizPass     = AiLearningQuizAttempt::query()
            ->where('passed', true)
            ->count();

        return [
            'tracks'        => $tracks,
            'total_tracks'  => AiLearningTrack::query()->count(),
            'active_tracks' => AiLearningTrack::query()->where('status', AiLearningTrack::STATUS_ACTIVE)->count(),
            'lessons_total' => $totalLessons,
            'lessons_done'  => $doneLessons,
            'lessons_pct'   => $totalLessons > 0 ? (int) round(($doneLessons / $totalLessons) * 100) : 0,
            'quiz_attempts' => $quizzes,
            'quiz_pass_rate'=> $quizzes > 0 ? (int) round(($quizPass / $quizzes) * 100) : 0,
        ];
    }

    private function sessions(): array
    {
        $sessions = AiSession::query()
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get()
            ->map(fn (AiSession $s) => [
                'id'           => $s->id,
                'title'        => $s->title ?: ('session #' . $s->id),
                'telegram_chat'=> $s->telegram_chat_id,
                'messages'     => $s->messages_count,
                'compactions'  => $s->compaction_count,
                'updated'      => optional($s->updated_at)->diffForHumans(),
            ])
            ->toArray();

        return [
            'list'  => $sessions,
            'total' => AiSession::query()->count(),
        ];
    }

    /**
     * Build a node graph from the project state — sessions, tasks, expenses, reminders.
     * Replaces the dashboard's "memory graph" with a real data-driven graph.
     */
    private function memory(): array
    {
        $W = 760;
        $H = 460;

        $userTitle = config('app.name', 'hamdix');

        $nodes = [];
        $edges = [];

        $nodes[] = [
            'id' => 'self', 'x' => $W / 2, 'y' => $H / 2, 'r' => 22,
            't' => 'self', 'label' => $userTitle, 'tone' => 'cyan',
        ];

        $contexts = [
            ['id' => 'sessions',  'x' => 200, 'y' => 140, 'label' => 'sessions',  'tone' => 'azure'],
            ['id' => 'tasks',     'x' => 560, 'y' => 130, 'label' => 'tasks',     'tone' => 'azure'],
            ['id' => 'reminders', 'x' => 180, 'y' => 340, 'label' => 'reminders', 'tone' => 'azure'],
            ['id' => 'learning',  'x' => 580, 'y' => 340, 'label' => 'learning',  'tone' => 'azure'],
            ['id' => 'expenses',  'x' => 380, 'y' => 360, 'label' => 'expenses',  'tone' => 'pos'],
        ];

        foreach ($contexts as $c) {
            $nodes[] = array_merge($c, ['r' => 16, 't' => 'context']);
            $edges[] = ['self', $c['id']];
        }

        $coords = [
            'sessions'  => [[100, 80], [280, 60]],
            'tasks'     => [[640, 60], [720, 80]],
            'reminders' => [[100, 400], [260, 400]],
            'learning'  => [[660, 220], [700, 400]],
            'expenses'  => [[300, 420], [460, 420]],
        ];

        $sessions = AiSession::query()->latest('updated_at')->limit(2)->get();
        foreach ($sessions as $i => $s) {
            $id = 'sess_' . $s->id;
            [$x, $y] = $coords['sessions'][$i] ?? [120 + $i * 80, 80];
            $nodes[] = ['id' => $id, 'x' => $x, 'y' => $y, 'r' => 11, 't' => 'session', 'label' => Str::limit($s->title ?: ('s#' . $s->id), 12, ''), 'tone' => 'default'];
            $edges[] = ['sessions', $id];
        }

        $tasks = AiTask::query()->whereIn('status', [AiTask::STATUS_PENDING, AiTask::STATUS_IN_PROGRESS])
            ->latest('id')->limit(2)->get();
        foreach ($tasks as $i => $t) {
            $id = 'task_' . $t->id;
            [$x, $y] = $coords['tasks'][$i] ?? [640 + $i * 60, 80];
            $nodes[] = ['id' => $id, 'x' => $x, 'y' => $y, 'r' => 11, 't' => 'task', 'label' => Str::limit($t->title, 14, ''), 'tone' => 'warn'];
            $edges[] = ['tasks', $id];
        }

        $reminders = AiReminder::query()->where('is_active', true)->latest('id')->limit(2)->get();
        foreach ($reminders as $i => $r) {
            $id = 'rem_' . $r->id;
            [$x, $y] = $coords['reminders'][$i] ?? [120 + $i * 80, 400];
            $nodes[] = ['id' => $id, 'x' => $x, 'y' => $y, 'r' => 11, 't' => 'reminder', 'label' => Str::limit($r->message, 12, ''), 'tone' => 'pos'];
            $edges[] = ['reminders', $id];
        }

        $tracks = AiLearningTrack::query()->latest('id')->limit(2)->get();
        foreach ($tracks as $i => $tr) {
            $id = 'track_' . $tr->id;
            [$x, $y] = $coords['learning'][$i] ?? [660 + $i * 60, 220];
            $nodes[] = ['id' => $id, 'x' => $x, 'y' => $y, 'r' => 11, 't' => 'track', 'label' => Str::limit($tr->topic, 12, ''), 'tone' => 'cyan'];
            $edges[] = ['learning', $id];
        }

        $expCats = AiExpense::query()
            ->select('category', DB::raw('COUNT(*) as c'))
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderByDesc('c')
            ->limit(2)
            ->get();
        foreach ($expCats as $i => $e) {
            $id = 'exp_' . md5((string) $e->category);
            [$x, $y] = $coords['expenses'][$i] ?? [300 + $i * 80, 420];
            $nodes[] = ['id' => $id, 'x' => $x, 'y' => $y, 'r' => 11, 't' => 'category', 'label' => Str::limit($e->category, 12, ''), 'tone' => 'default'];
            $edges[] = ['expenses', $id];
        }

        return [
            'width'    => $W,
            'height'   => $H,
            'nodes'    => $nodes,
            'edges'    => $edges,
            'entities' => count($nodes),
            'edges_n'  => count($edges),
        ];
    }

    private function roleLabel(?string $role, ?string $toolName): string
    {
        return match ($role) {
            AiRoleEnum::User->value      => 'user',
            AiRoleEnum::Assistant->value => 'assistant',
            AiRoleEnum::System->value    => 'system',
            AiRoleEnum::Tool->value      => 'tool · ' . ($toolName ?? '?'),
            default                       => $role ?? '—',
        };
    }

    private function roleTone(?string $role): string
    {
        return match ($role) {
            AiRoleEnum::User->value      => 'cyan',
            AiRoleEnum::Assistant->value => 'azure',
            AiRoleEnum::Tool->value      => 'warn',
            AiRoleEnum::System->value    => 'default',
            default                       => 'default',
        };
    }

    private function snippet(?string $content, int $len = 80): string
    {
        if ($content === null) {
            return '';
        }
        $content = trim(preg_replace('/\s+/', ' ', $content));
        return Str::limit($content, $len);
    }

    private function toolRole(string $name): string
    {
        if (str_contains($name, 'task'))     return 'task manager';
        if (str_contains($name, 'expense'))  return 'expense tracker';
        if (str_contains($name, 'reminder')) return 'reminder';
        if (str_contains($name, 'learning')) return 'tutor';
        if (str_contains($name, 'session'))  return 'session control';
        if (str_contains($name, 'web') || str_contains($name, 'fetch') || str_contains($name, 'search')) return 'web';
        return 'tool';
    }
}
