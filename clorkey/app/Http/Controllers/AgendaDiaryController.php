<?php

namespace App\Http\Controllers;

use App\Models\AgendaDiary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgendaDiaryController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'include_deleted_content' => ['sometimes'],
        ]);

        // Boolean mais permissivo para evitar 422 por serialização do front
        $includeDeletedContent = filter_var(
            $data['include_deleted_content'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $entry = AgendaDiary::query()
            ->withTrashed()
            ->where('user_id', auth()->id())
            ->whereDate('date', $data['date'])
            ->first();

        $isDeleted = (bool) ($entry?->trashed() ?? false);
        $content = $entry?->content ?? '';

        if ($isDeleted && !$includeDeletedContent) {
            $content = '';
        }

        return response()->json([
            'entry' => [
                'id' => $entry?->id,
                'date' => $data['date'],
                'content' => $content,
                'deleted' => $isDeleted,
                'deleted_by_name' => $entry?->user?->name,
            ],
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'content' => ['nullable', 'string'],
        ]);
        $content = (string) ($data['content'] ?? '');
        $isEmptyContent = $this->isEmptyDiaryContent($content);

        $entry = AgendaDiary::query()
            ->withTrashed()
            ->where('user_id', auth()->id())
            ->whereDate('date', $data['date'])
            ->first();

        if ($isEmptyContent) {
            if ($entry && !$entry->trashed()) {
                $entry->delete();
            }

            return response()->json([
                'entry' => [
                    'id' => $entry?->id,
                    'date' => $data['date'],
                    'content' => '',
                ],
                'message' => 'Diário atualizado com sucesso.',
            ]);
        }

        if (!$entry) {
            $entry = AgendaDiary::query()->create([
                'user_id' => auth()->id(),
                'date' => $data['date'],
                'content' => $content,
            ]);
        } else {
            if ($entry->trashed()) {
                $entry->restore();
            }
            $entry->content = $content;
            $entry->save();
        }

        return response()->json([
            'entry' => [
                'id' => $entry->id,
                'date' => $entry->date?->format('Y-m-d') ?? $data['date'],
                'content' => $entry->content ?? '',
            ],
            'message' => 'Diário salvo com sucesso.',
        ]);
    }

    private function isEmptyDiaryContent(string $content): bool
    {
        $plainText = strip_tags($content);
        $plainText = str_replace("\xc2\xa0", ' ', $plainText); // &nbsp; UTF-8
        $plainText = str_replace('&nbsp;', ' ', $plainText);

        return trim($plainText) === '';
    }
}
