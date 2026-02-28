<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgendaTaskRequest;
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
            $allUserTasks = AgendaTask::where('user_id', auth()->id())->orderBy('date')->orderBy('start_time')->limit(200)->get();
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
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $base = [
            'user_id'     => auth()->id(),
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'start_time'  => $data['start_time'],
            'end_time'         => $data['end_time'] ?? null,
            'participants' => $participants,
        ];

        if ($recurrence) {
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
                    'user_id'             => $base['user_id'],
                    'name'                => $base['name'],
                    'description'         => $base['description'],
                    'start_time'          => $occ['start_time'],
                    'end_time'            => null,
                    'participants'        => json_encode($base['participants']),
                    'date'                => $occ['date'],
                    'recurrence_group_id' => $groupId,
                    'recurrence_config'   => json_encode($recurrence),
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ], $occurrences));
            } else {
                $dates = $this->expandRecurrence($data['date'], $recurrence);

                if (count($dates) > 500) {
                    return back()->withErrors([
                        'recurrence' => 'A recorrência gera mais de 500 ocorrências. Reduza o período ou aumente o intervalo.',
                    ]);
                }

                AgendaTask::insert(array_map(fn($date) => [
                    'user_id'             => $base['user_id'],
                    'name'                => $base['name'],
                    'description'         => $base['description'],
                    'start_time'          => $base['start_time'],
                    'end_time'            => $base['end_time'],
                    'participants'        => json_encode($base['participants']),
                    'date'                => $date,
                    'recurrence_group_id' => $groupId,
                    'recurrence_config'   => json_encode($recurrence),
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ], $dates));
            }
        } else {
            AgendaTask::create([...$base, 'date' => $data['date']]);
        }

        return redirect()
            ->route('agenda.index', ['date' => $data['date']])
            ->with('success', 'Tarefa criada com sucesso.');
    }

    public function destroy(AgendaTask $agendaTask): RedirectResponse
    {
        if ($agendaTask->user_id !== auth()->id()) {
            abort(403);
        }

        $agendaTask->delete();

        return back()->with('success', 'Tarefa removida com sucesso.');
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
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn($task) => $this->formatTask($task));

        return response()->json(['tasks' => $tasks]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatTask(AgendaTask $task): array
    {
        return [
            'id'                  => $task->id,
            'name'                => $task->name,
            'description'         => $task->description,
            'date'                => $task->date?->format('Y-m-d'),
            'start_time'          => $task->start_time ? substr($task->start_time, 0, 5) : null,
            'end_time'            => $task->end_time ? substr($task->end_time, 0, 5) : null,
            'recurrence_group_id' => $task->recurrence_group_id,
            'participants'        => collect($task->participants ?? [])
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * Expand a recurrence config into an array of date strings (YYYY-MM-DD).
     */
    private function expandRecurrence(string $start, array $rec): array
    {
        $current = Carbon::parse($start);
        $end     = Carbon::parse($rec['end_date']);
        $dates   = [];

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
        $interval  = max(1, (int) ($rec['interval'] ?? 1));
        $endDate   = Carbon::parse($rec['end_date']);
        [$sh, $sm] = array_map('intval', explode(':', substr($startTime, 0, 5)));
        [$eh, $em] = array_map('intval', explode(':', substr($endTime,   0, 5)));

        $occurrences = [];
        $currentDay  = Carbon::parse($startDate);

        while ($currentDay->lte($endDate) && count($occurrences) <= 500) {
            $cursor = $currentDay->copy()->setTime($sh, $sm);
            $dayEnd = $currentDay->copy()->setTime($eh, $em);

            while ($cursor->lte($dayEnd) && count($occurrences) <= 500) {
                $occurrences[] = [
                    'date'       => $cursor->toDateString(),
                    'start_time' => $cursor->format('H:i'),
                ];
                $cursor->addHours($interval);
            }

            $currentDay->addDay();
        }

        return $occurrences;
    }
}
