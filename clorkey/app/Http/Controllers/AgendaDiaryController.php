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
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $entry = AgendaDiary::query()
            ->where('user_id', auth()->id())
            ->whereDate('date', $data['date'])
            ->first();

        return response()->json([
            'entry' => [
                'date' => $data['date'],
                'content' => $entry?->content ?? '',
            ],
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'content' => ['nullable', 'string'],
        ]);

        $entry = AgendaDiary::query()->updateOrCreate(
            [
                'user_id' => auth()->id(),
                'date' => $data['date'],
            ],
            [
                'content' => $data['content'] ?? '',
            ]
        );

        return response()->json([
            'entry' => [
                'id' => $entry->id,
                'date' => $entry->date?->format('Y-m-d') ?? $data['date'],
                'content' => $entry->content ?? '',
            ],
            'message' => 'Diário salvo com sucesso.',
        ]);
    }
}
