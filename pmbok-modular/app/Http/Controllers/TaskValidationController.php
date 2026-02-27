<?php

namespace App\Http\Controllers;

use App\Models\AgendaTask;
use App\Models\TaskValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskValidationController extends Controller
{
    /**
     * Return all pending validations for the task.
     */
    public function index(AgendaTask $task): JsonResponse
    {
        $validations = $task->validations()
            ->with(['requester', 'target'])
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($v) => $this->formatValidation($v));

        return response()->json(['validations' => $validations]);
    }

    /**
     * Create a new validation request.
     */
    public function store(Request $request, AgendaTask $task): JsonResponse
    {
        $request->validate([
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'note'           => ['nullable', 'string', 'max:2000'],
        ]);

        $validation = $task->validations()->create([
            'requester_id'   => auth()->id(),
            'target_user_id' => $request->integer('target_user_id'),
            'note'           => $request->input('note') ?: null,
            'status'         => 'pending',
        ]);

        $validation->load(['requester', 'target']);

        return response()->json(['validation' => $this->formatValidation($validation)], 201);
    }

    /**
     * Approve a pending validation (only the target user can approve).
     */
    public function approve(AgendaTask $task, TaskValidation $validation): JsonResponse
    {
        abort_unless($validation->agenda_task_id === $task->id, 404);
        abort_unless($validation->target_user_id === auth()->id(), 403, 'Apenas o destinatário pode aprovar.');
        abort_unless($validation->status === 'pending', 422, 'Esta validação já foi resolvida.');

        $validation->update([
            'status'      => 'approved',
            'resolved_at' => now(),
        ]);

        return response()->json(['validation' => $this->formatValidation($validation->fresh(['requester', 'target']))]);
    }

    /**
     * Reject a pending validation (only the target user can reject).
     */
    public function reject(AgendaTask $task, TaskValidation $validation): JsonResponse
    {
        abort_unless($validation->agenda_task_id === $task->id, 404);
        abort_unless($validation->target_user_id === auth()->id(), 403, 'Apenas o destinatário pode rejeitar.');
        abort_unless($validation->status === 'pending', 422, 'Esta validação já foi resolvida.');

        $validation->update([
            'status'      => 'rejected',
            'resolved_at' => now(),
        ]);

        return response()->json(['validation' => $this->formatValidation($validation->fresh(['requester', 'target']))]);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function formatValidation(TaskValidation $v): array
    {
        return [
            'id'             => $v->id,
            'requester_id'   => $v->requester_id,
            'requester_name' => $v->requester?->name ?? 'Usuário',
            'target_user_id' => $v->target_user_id,
            'target_name'    => $v->target?->name ?? 'Usuário',
            'note'           => $v->note,
            'status'         => $v->status,
            'created_at'     => $v->created_at?->toIso8601String(),
        ];
    }
}
