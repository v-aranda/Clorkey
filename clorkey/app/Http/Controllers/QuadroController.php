<?php

namespace App\Http\Controllers;

use App\Models\AgendaTask;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuadroController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();

        $tasks = $this->queryUserTasks($userId)
            ->get()
            ->map(fn($task) => $this->formatTask($task));

        $users = User::select('id', 'name', 'email', 'avatar_path')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url,
            ]);

        return Inertia::render('Quadro/Index', [
            'tasks' => $tasks,
            'users' => $users,
        ]);
    }

    public function tasks(): JsonResponse
    {
        $userId = auth()->id();

        $tasks = $this->queryUserTasks($userId)
            ->get()
            ->map(fn($task) => $this->formatTask($task));

        return response()->json(['tasks' => $tasks]);
    }

    /**
     * Persist the new priority order coming from the list drag-and-drop.
     * Expects: { items: [{ id: int, sort_order: int }, ...] }
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.sort_order' => 'required|integer',
        ]);

        $userId = auth()->id();
        $items = collect($request->input('items'));
        $ids = $items->pluck('id')->all();

        // Verify all tasks belong to the user (creator or participant)
        $allowedTasks = AgendaTask::query()
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereJsonContains('participants', $userId);
            })
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($allowedTasks) !== count($ids)) {
            return response()->json(['message' => 'Uma ou mais tarefas não pertencem a você.'], 403);
        }

        // Batch update sort_order
        foreach ($items as $item) {
            AgendaTask::where('id', $item['id'])->update([
                'sort_order' => $item['sort_order'],
            ]);
        }

        return response()->json(['message' => 'Ordem atualizada com sucesso.']);
    }

    private function queryUserTasks(int $userId)
    {
        return AgendaTask::query()
            ->with('user:id,name,email,avatar_path')
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereJsonContains('participants', $userId);
            })
            ->orderBy('sort_order');
    }

    private function formatTask(AgendaTask $task): array
    {
        return [
            'id' => $task->id,
            'name' => $task->name,
            'description' => $task->description,
            'date' => $task->date?->format('Y-m-d'),
            'start_time' => $task->start_time ? substr($task->start_time, 0, 5) : null,
            'end_time' => $task->end_time ? substr($task->end_time, 0, 5) : null,
            'status' => $task->status ?? 'todo',
            'sort_order' => $task->sort_order ?? 0,
            'participants' => collect($task->participants ?? [])
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all(),
            'creator' => [
                'id' => $task->user?->id,
                'name' => $task->user?->name ?? 'Usuário',
                'email' => $task->user?->email,
                'avatar_url' => $task->user?->avatar_url,
            ],
        ];
    }
}

