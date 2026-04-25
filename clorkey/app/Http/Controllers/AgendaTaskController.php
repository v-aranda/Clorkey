<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgendaTaskRequest;
use App\Http\Requests\UpdateAgendaTaskRequest;
use App\Models\AgendaTask;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AgendaTaskController extends Controller
{
    public function index(): Response
    {
        $date = request('date') ? Carbon::createFromFormat('Y-m-d', request('date'))->startOfDay() : today();

        $tasksQuery = AgendaTask::where('user_id', auth()->id())
            ->with('user:id,name,email,avatar_path')
            ->whereDate('date', $date)
            ->orderBy('start_time')
            ->get();

        try {
            \Log::info('Agenda@index - tasks loaded', [
                'user_id' => auth()->id(),
                'today' => today()->toDateString(),
                'db_count' => $tasksQuery->count(),
                'db_tasks' => $tasksQuery->map(fn($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'date' => $t->date?->toDateString(),
                    'start_time' => $t->start_time,
                ])->all(),
            ]);
            $allUserTasks = AgendaTask::where('user_id', auth()->id())
                ->orderBy('date')
                ->orderBy('start_time')
                ->limit(200)
                ->get();
            \Log::info('Agenda@index - all user tasks', [
                'user_id' => auth()->id(),
                'all_count' => $allUserTasks->count(),
                'all_tasks' => $allUserTasks->map(fn($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'date' => $t->date?->toDateString(),
                    'start_time' => $t->start_time,
                ])->all(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Agenda@index - logging failed: ' . $e->getMessage());
        }

        $tasks = $tasksQuery->map(fn($task) => $this->formatTask($task));

        return Inertia::render('Agenda/Index', [
            'tasks' => $tasks,
            'users' => User::select('id', 'name', 'email', 'avatar_path')
                ->orderBy('name')
                ->get()
                ->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'avatar_url' => $u->avatar_url,
                ]),
        ]);
    }

    public function store(StoreAgendaTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $recurrence = $data['recurrence'] ?? null;

        $participants = collect($data['participants'] ?? [])
            ->push((int) auth()->id())
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $base = [
            'user_id' => auth()->id(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'participants' => $participants,
            'parent_id' => $data['parent_id'] ?? null,
        ];

        if ($recurrence && !empty($data['date']) && !empty($data['start_time'])) {
            $groupId = (string) Str::uuid();
            $now = now();

            if ($recurrence['type'] === 'hourly') {
                $occurrences = $this->expandHourlyRecurrence(
                    $data['date'],
                    $data['start_time'],
                    $data['end_time'],
                    $recurrence
                );

                if (count($occurrences) > 500) {
                    return back()->withErrors([
                        'recurrence' => 'A recorrência gera mais de 500 ocorrências. Reduza o período ou aumente o intervalo.',
                    ]);
                }

                AgendaTask::insert(array_map(fn($occ) => [
                    'user_id' => $base['user_id'],
                    'name' => $base['name'],
                    'description' => $base['description'],
                    'start_time' => $occ['start_time'],
                    'end_time' => null,
                    'participants' => json_encode($base['participants']),
                    'date' => $occ['date'],
                    'recurrence_group_id' => $groupId,
                    'recurrence_config' => json_encode($recurrence),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $occurrences));
            } else {
                $dates = $this->expandRecurrence($data['date'], $recurrence);

                if (count($dates) > 500) {
                    return back()->withErrors([
                        'recurrence' => 'A recorrência gera mais de 500 ocorrências. Reduza o período ou aumente o intervalo.',
                    ]);
                }

                AgendaTask::insert(array_map(fn($date) => [
                    'user_id' => $base['user_id'],
                    'name' => $base['name'],
                    'description' => $base['description'],
                    'start_time' => $base['start_time'],
                    'end_time' => $base['end_time'],
                    'participants' => json_encode($base['participants']),
                    'date' => $date,
                    'recurrence_group_id' => $groupId,
                    'recurrence_config' => json_encode($recurrence),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $dates));
            }
        } else {
            AgendaTask::create([...$base, 'date' => $data['date'] ?? null, 'deadline' => $data['deadline'] ?? null]);
        }

        $redirectDate = $data['date'] ?? today()->toDateString();

        return redirect()
            ->route('agenda.index', ['date' => $redirectDate])
            ->with('success', 'Tarefa criada com sucesso.');
    }

    public function update(UpdateAgendaTaskRequest $request, AgendaTask $agendaTask): RedirectResponse|JsonResponse
    {
        abort_unless($this->canManageTask($agendaTask), 403);

        $data = $request->validated();

        $agendaTask->fill([
            'name' => $data['name'] ?? $agendaTask->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $agendaTask->description,
            'date' => array_key_exists('date', $data) ? $data['date'] : $agendaTask->date,
            'start_time' => array_key_exists('start_time', $data) ? $data['start_time'] : $agendaTask->start_time,
            'end_time' => array_key_exists('end_time', $data) ? $data['end_time'] : $agendaTask->end_time,
            'participants' => array_key_exists('participants', $data) ? $data['participants'] : $agendaTask->participants,
            'status' => array_key_exists('status', $data) ? $data['status'] : $agendaTask->status,
            'parent_id' => array_key_exists('parent_id', $data) ? $data['parent_id'] : $agendaTask->parent_id,
            'deadline' => array_key_exists('deadline', $data) ? $data['deadline'] : $agendaTask->deadline,
        ]);

        // Keep schedule integrity: if one part of start datetime is missing, unset both.
        if (is_null($agendaTask->date) || is_null($agendaTask->start_time)) {
            $agendaTask->date = null;
            $agendaTask->start_time = null;
            $agendaTask->end_time = null;
        }

        $agendaTask->save();
        $agendaTask->loadMissing('user:id,name,email,avatar_path');

        if ($request->expectsJson()) {
            return response()->json([
                'task' => $this->formatTask($agendaTask),
                'message' => 'Tarefa atualizada com sucesso.',
            ]);
        }

        return back()->with('success', 'Tarefa atualizada com sucesso.');
    }

    public function destroy(AgendaTask $agendaTask): RedirectResponse|JsonResponse
    {
        if ($agendaTask->user_id !== auth()->id()) {
            abort(403);
        }

        if ($agendaTask->children()->exists()) {
            if (request()->expectsJson()) {
                return response()->json([
                    'message' => 'Não é possível excluir uma tarefa que possui subtarefas.',
                ], 422);
            }

            return back()->withErrors([
                'delete' => 'Não é possível excluir uma tarefa que possui subtarefas.',
            ]);
        }

        $agendaTask->delete();

        return back()->with('success', 'Tarefa removida com sucesso.');
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer'],
        ]);

        $userId = auth()->id();
        $taskIds = array_map('intval', $request->input('task_ids'));

        $tasks = AgendaTask::whereIn('id', $taskIds)
            ->where('user_id', $userId)
            ->get();

        if ($tasks->isEmpty()) {
            return response()->json(['message' => 'Nenhuma tarefa encontrada ou sem permissão.'], 404);
        }

        foreach ($tasks as $task) {
            if ($task->children()->exists()) {
                return response()->json([
                    'message' => "Não é possível excluir a tarefa \"{$task->name}\" pois ela possui subtarefas.",
                ], 422);
            }
        }

        $deletedIds = $tasks->pluck('id')->all();

        AgendaTask::whereIn('id', $deletedIds)->delete();

        return response()->json(['deleted_ids' => $deletedIds]);
    }

    public function recurrenceOccurrences(AgendaTask $agendaTask): JsonResponse
    {
        if ($agendaTask->user_id !== auth()->id()) {
            abort(403);
        }

        if (!$agendaTask->recurrence_group_id) {
            return response()->json(['tasks' => []]);
        }

        $tasks = AgendaTask::where('recurrence_group_id', $agendaTask->recurrence_group_id)
            ->with('user:id,name,email,avatar_path')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn($task) => $this->formatTask($task));

        return response()->json(['tasks' => $tasks]);
    }

    public function users()
    {
        return User::select('id', 'name', 'email', 'avatar_path')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url,
            ]);
    }

    public function list(Request $request): JsonResponse
    {
        $dateStr = $request->query('date') ?? today()->toDateString();
        try {
            $date = Carbon::createFromFormat('Y-m-d', $dateStr);
        } catch (\Throwable $e) {
            $date = today();
        }

        $tasks = AgendaTask::where('user_id', auth()->id())
            ->with('user:id,name,email,avatar_path')
            ->whereDate('date', $date)
            ->orderBy('start_time')
            ->get()
            ->map(fn($task) => $this->formatTask($task));

        return response()->json(['tasks' => $tasks]);
    }

    public function participating(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $tasks = AgendaTask::whereJsonContains('participants', $userId)
            ->with('user:id,name,email,avatar_path')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn($task) => $this->formatTask($task));

        return response()->json(['tasks' => $tasks]);
    }

    public function assigned(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $tasks = AgendaTask::query()
            ->with('user:id,name,email,avatar_path')
            ->whereJsonContains('participants', $userId)
            ->where(function ($query) {
                $query->whereNull('date')
                    ->orWhereNull('start_time');
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($task) => $this->formatTask($task));

        return response()->json(['tasks' => $tasks]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatTask(AgendaTask $task): array
    {
        return [
            'id' => $task->id,
            'parent_id' => $task->parent_id,
            'name' => $task->name,
            'description' => $task->description,
            'date' => $task->date?->format('Y-m-d'),
            'start_time' => $task->start_time ? substr($task->start_time, 0, 5) : null,
            'end_time' => $task->end_time ? substr($task->end_time, 0, 5) : null,
            'recurrence_group_id' => $task->recurrence_group_id,
            'participants' => collect($task->participants ?? [])
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all(),
            'status' => $task->status ?? 'todo',
            'creator' => [
                'id' => $task->user?->id,
                'name' => $task->user?->name ?? 'Usuário',
                'email' => $task->user?->email,
                'avatar_url' => $task->user?->avatar_url,
            ],
        ];
    }

    private function canManageTask(AgendaTask $task): bool
    {
        $userId = auth()->id();

        if ($task->user_id === $userId) {
            return true;
        }

        $participants = collect($task->participants ?? [])
            ->map(fn($id) => (int) $id)
            ->all();

        return in_array((int) $userId, $participants, true);
    }

    /**
     * Expand a recurrence config into an array of date strings (YYYY-MM-DD).
     */
    private function expandRecurrence(string $start, array $rec): array
    {
        $current = Carbon::parse($start);
        $end = Carbon::parse($rec['end_date']);
        $dates = [];

        switch ($rec['type']) {
            case 'daily':
                $interval = max(1, (int) ($rec['interval'] ?? 1));
                while ($current->lte($end) && count($dates) <= 500) {
                    $dates[] = $current->toDateString();
                    $current->addDays($interval);
                }
                break;

            case 'weekly':
                $days = array_map('intval', $rec['days_of_week'] ?? []);
                sort($days);
                $week = $current->copy()->startOfWeek(Carbon::SUNDAY);
                while ($week->lte($end) && count($dates) <= 500) {
                    foreach ($days as $dow) {
                        $d = $week->copy()->addDays($dow);
                        if ($d->gte($current) && $d->lte($end)) {
                            $dates[] = $d->toDateString();
                        }
                    }
                    $week->addWeek();
                }
                break;

            case 'monthly':
                $interval = max(1, (int) ($rec['interval'] ?? 1));
                while ($current->lte($end) && count($dates) <= 500) {
                    $dates[] = $current->toDateString();
                    $current->addMonths($interval);
                }
                break;

            case 'yearly':
                $interval = max(1, (int) ($rec['interval'] ?? 1));
                while ($current->lte($end) && count($dates) <= 500) {
                    $dates[] = $current->toDateString();
                    $current->addYears($interval);
                }
                break;
        }

        return array_values(array_unique($dates));
    }

    /**
     * Expand hourly recurrence: generates one occurrence per interval within each day,
     * from start_time to end_time, across all days from start_date to end_date.
     * Returns array of ['date' => 'Y-m-d', 'start_time' => 'H:i'].
     */
    private function expandHourlyRecurrence(string $startDate, string $startTime, string $endTime, array $rec): array
    {
        $interval = max(1, (int) ($rec['interval'] ?? 1));
        $endDate = Carbon::parse($rec['end_date']);
        [$sh, $sm] = array_map('intval', explode(':', substr($startTime, 0, 5)));
        [$eh, $em] = array_map('intval', explode(':', substr($endTime, 0, 5)));

        $occurrences = [];
        $currentDay = Carbon::parse($startDate);

        while ($currentDay->lte($endDate) && count($occurrences) <= 500) {
            $cursor = $currentDay->copy()->setTime($sh, $sm);
            $dayEnd = $currentDay->copy()->setTime($eh, $em);

            while ($cursor->lte($dayEnd) && count($occurrences) <= 500) {
                $occurrences[] = [
                    'date' => $cursor->toDateString(),
                    'start_time' => $cursor->format('H:i'),
                ];
                $cursor->addHours($interval);
            }

            $currentDay->addDay();
        }

        return $occurrences;
    }
}
