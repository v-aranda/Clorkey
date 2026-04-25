<?php

namespace App\Http\Controllers;

use App\Models\AgendaTask;
use App\Models\AgendaReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgendaReminderController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['date' => 'required|date_format:Y-m-d']);

        $userId = Auth::id();

        $reminders = AgendaReminder::where('date', $request->date)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhereJsonContains('participants', $userId);
            })
            ->with('user:id,name,email')
            ->orderBy('created_at')
            ->get()
            ->map(function ($r) {
                return [
                    'id'           => $r->id,
                    'title'        => $r->title,
                    'date'         => $r->date->format('Y-m-d'),
                    'participants' => $r->participants ?? [],
                    'user_id'      => $r->user_id,
                    'user_name'    => $r->user->name ?? null,
                    'kind'         => 'manual',
                ];
            });

        $taskReminders = AgendaTask::query()
            ->whereNotNull('deadline')
            ->where('deadline', $request->date)
            ->where('status', '!=', AgendaTask::STATUS_DONE)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhereJsonContains('participants', $userId);
            })
            ->with('user:id,name,email')
            ->orderBy('name')
            ->get()
            ->map(function (AgendaTask $task) use ($request) {
                return [
                    'id'           => 'task-deadline-' . $task->id,
                    'title'        => $task->name,
                    'date'         => $request->date,
                    'participants' => $task->participants ?? [],
                    'user_id'      => $task->user_id,
                    'user_name'    => $task->user->name ?? null,
                    'kind'         => 'task_deadline',
                    'task_id'      => $task->id,
                ];
            });

        return response()->json(
            $reminders
                ->concat($taskReminders)
                ->sortBy(fn ($item) => ($item['kind'] ?? '') . '|' . ($item['title'] ?? ''))
                ->values()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'date'           => 'required|date_format:Y-m-d',
            'participants'   => 'nullable|array',
            'participants.*' => 'integer|exists:users,id',
        ]);

        $participants = collect($data['participants'] ?? [])
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        $reminder = AgendaReminder::create([
            'user_id'      => Auth::id(),
            'title'        => $data['title'],
            'date'         => $data['date'],
            'participants' => $participants ?: null,
        ]);

        $reminder->load('user:id,name,email');

        return response()->json([
            'id'           => $reminder->id,
            'title'        => $reminder->title,
            'date'         => $reminder->date->format('Y-m-d'),
            'participants' => $reminder->participants ?? [],
            'user_id'      => $reminder->user_id,
            'user_name'    => $reminder->user->name ?? null,
        ], 201);
    }

    public function destroy(AgendaReminder $reminder)
    {
        if ($reminder->user_id !== Auth::id()) {
            abort(403);
        }

        $reminder->delete();

        return response()->json(['ok' => true]);
    }
}
