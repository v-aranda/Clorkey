<?php

namespace App\Http\Controllers;

use App\Models\AgendaTask;
use App\Models\TaskMessage;
use App\Models\TaskMessageAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskMessageController extends Controller
{
    /**
     * Return all messages for the given task, newest last.
     */
    public function index(AgendaTask $task): JsonResponse
    {
        $messages = $task->messages()
            ->with(['user', 'attachments'])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => $this->formatMessage($m));

        return response()->json(['messages' => $messages]);
    }

    /**
     * Store a new message (with optional file attachments).
     */
    public function store(Request $request, AgendaTask $task): JsonResponse
    {
        $request->validate([
            'content'   => ['nullable', 'string', 'max:10000'],
            'files'     => ['nullable', 'array', 'max:10'],
            'files.*'   => ['file', 'max:51200'], // 50 MB per file
        ]);

        // Require at least content or a file
        if (empty(trim($request->input('content', ''))) && ! $request->hasFile('files')) {
            return response()->json(['error' => 'Mensagem vazia.'], 422);
        }

        $message = $task->messages()->create([
            'user_id' => auth()->id(),
            'content' => $request->input('content') ?: null,
        ]);

        // Handle file attachments
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $type = $this->detectFileType($file->getMimeType());
                $dir  = "agenda-attachments/{$task->id}";
                $path = $file->store($dir, 'public');

                $message->attachments()->create([
                    'path'          => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'type'          => $type,
                    'size'          => $file->getSize(),
                ]);
            }
        }

        $message->load(['user', 'attachments']);

        return response()->json(['message' => $this->formatMessage($message)], 201);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatMessage(TaskMessage $m): array
    {
        return [
            'id'         => $m->id,
            'user_id'    => $m->user_id,
            'user_name'  => $m->user?->name ?? 'Usuário',
            'user_avatar'=> $m->user?->avatar_url,
            'content'    => $m->content,
            'attachments'=> $m->attachments->map(fn ($a) => [
                'id'            => $a->id,
                'url'           => Storage::disk('public')->url($a->path),
                'type'          => $a->type,
                'original_name' => $a->original_name,
                'size'          => $a->size,
            ])->values()->all(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    private function detectFileType(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        return 'doc';
    }
}
