<?php

namespace App\Http\Controllers;

use App\Models\AgendaDiary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiaryController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'active';
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $search = trim((string) $request->string('search')->toString());

        $entries = AgendaDiary::query()
            ->where('user_id', auth()->id())
            ->when(
                $status === 'inactive',
                fn ($q) => $q->onlyTrashed(),
                fn ($q) => $q->withoutTrashed()
            )
            ->whereNotNull('content')
            ->where('content', '<>', '')
            ->when($search !== '', function ($q) use ($search) {
                $digits = preg_replace('/[^0-9]/', '', $search);

                $q->where(function ($inner) use ($search, $digits) {
                    $inner->whereRaw("TO_CHAR(date, 'YYYY-MM-DD') ILIKE ?", ["%{$search}%"])
                        ->orWhereRaw("TO_CHAR(date, 'DD/MM/YYYY') ILIKE ?", ["%{$search}%"]);

                    if ($digits !== '') {
                        $inner->orWhereRaw("REPLACE(TO_CHAR(date, 'DD/MM/YYYY'), '/', '') ILIKE ?", ["%{$digits}%"])
                            ->orWhereRaw("REPLACE(TO_CHAR(date, 'YYYY-MM-DD'), '-', '') ILIKE ?", ["%{$digits}%"]);
                    }
                });
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (AgendaDiary $entry) => [
                'id' => $entry->id,
                'date' => $entry->date?->format('Y-m-d'),
                'title' => $entry->date?->format('d/m/Y') ?? '-',
                'weekday' => $entry->date ? ucfirst($entry->date->translatedFormat('l')) : '-',
                'deleted' => $entry->trashed(),
                'content' => $entry->content ?? '',
                'deleted_at' => $entry->deleted_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Diary/Index', [
            'entries' => $entries,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function destroy(AgendaDiary $entry): RedirectResponse
    {
        abort_unless((int) $entry->user_id === (int) auth()->id(), 403);

        $entry->delete();

        return back()->with('success', 'Registro do diário removido com sucesso.');
    }

    public function restore(int $entry): RedirectResponse
    {
        $diaryEntry = AgendaDiary::withTrashed()
            ->where('id', $entry)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($diaryEntry->trashed()) {
            $diaryEntry->restore();
        }

        return back()->with('success', 'Registro do diário restaurado com sucesso.');
    }

    public function show(int $entry): JsonResponse
    {
        $diaryEntry = AgendaDiary::query()
            ->withTrashed()
            ->where('id', $entry)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return response()->json([
            'entry' => [
                'id' => $diaryEntry->id,
                'date' => $diaryEntry->date?->format('Y-m-d'),
                'title' => $diaryEntry->date?->format('d/m/Y') ?? '-',
                'weekday' => $diaryEntry->date ? ucfirst($diaryEntry->date->translatedFormat('l')) : '-',
                'deleted' => $diaryEntry->trashed(),
                'content' => $diaryEntry->content ?? '',
                'deleted_at' => $diaryEntry->deleted_at?->format('d/m/Y H:i'),
            ],
        ]);
    }
}
