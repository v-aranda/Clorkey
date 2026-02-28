<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentRelationship;
use App\Models\DocumentRelationshipPendency;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentRelationshipController extends Controller
{
    public function store(Request $request, Document $document)
    {
        $validated = $request->validate([
            'target_document_id' => 'required|exists:documents,id',
            'source_paragraph_id' => 'required|string',
            'target_paragraph_id' => 'nullable|string|required_without:target_paragraph_ids',
            'target_paragraph_ids' => 'nullable|array|required_without:target_paragraph_id|min:1',
            'target_paragraph_ids.*' => 'required|string',
        ]);

        $targetParagraphIds = collect($validated['target_paragraph_ids'] ?? [])->filter();
        if ($targetParagraphIds->isEmpty() && !empty($validated['target_paragraph_id'])) {
            $targetParagraphIds = collect([$validated['target_paragraph_id']]);
        }
        $targetParagraphIds = $targetParagraphIds->map(fn ($id) => trim($id))->filter()->unique()->values();

        if ($targetParagraphIds->isEmpty()) {
            throw ValidationException::withMessages([
                'target_paragraph_ids' => 'Selecione ao menos um parágrafo relacionado.',
            ]);
        }

        $sourceParagraphId = trim($validated['source_paragraph_id']);
        $targetDocumentId = (int) $validated['target_document_id'];
        $sourceDocumentId = (int) $document->id;

        // Regra: um parágrafo não pode se relacionar com ele mesmo.
        if ($sourceDocumentId === $targetDocumentId && $targetParagraphIds->contains($sourceParagraphId)) {
            throw ValidationException::withMessages([
                'target_paragraph_ids' => 'Um parágrafo não pode se relacionar com ele mesmo.',
            ]);
        }

        // Regra: não permitir duplicado (A->B) nem inverso (B->A).
        $existing = DocumentRelationship::query()
            ->where(function ($query) use ($sourceDocumentId, $sourceParagraphId, $targetDocumentId, $targetParagraphIds) {
                $query->where('source_document_id', $sourceDocumentId)
                    ->where('source_paragraph_id', $sourceParagraphId)
                    ->where('target_document_id', $targetDocumentId)
                    ->whereIn('target_paragraph_id', $targetParagraphIds->all());
            })
            ->orWhere(function ($query) use ($sourceDocumentId, $sourceParagraphId, $targetDocumentId, $targetParagraphIds) {
                $query->where('source_document_id', $targetDocumentId)
                    ->whereIn('source_paragraph_id', $targetParagraphIds->all())
                    ->where('target_document_id', $sourceDocumentId)
                    ->where('target_paragraph_id', $sourceParagraphId);
            })
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'target_paragraph_ids' => 'Já existe um relacionamento entre os parágrafos selecionados (incluindo ordem inversa).',
            ]);
        }

        $rows = $targetParagraphIds->map(fn ($targetParagraphId) => [
            'source_document_id' => $sourceDocumentId,
            'target_document_id' => $targetDocumentId,
            'source_paragraph_id' => $sourceParagraphId,
            'target_paragraph_id' => $targetParagraphId,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::transaction(function () use ($rows) {
            DocumentRelationship::query()->insert($rows);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'created_count' => count($rows),
            ]);
        }

        return back()->with('success', 'Relacionamento salvo com sucesso.');
    }

    public function destroy(Document $document, DocumentRelationship $relationship)
    {
        $user = auth()->user();

        $isRelatedToDocument = $relationship->source_document_id === $document->id
            || $relationship->target_document_id === $document->id;

        if (! $isRelatedToDocument) {
            abort(403);
        }

        $isAdmin = $user?->isAdmin() ?? false;
        $isCreator = $user && (int) $relationship->created_by === (int) $user->id;
        $isSourceDocumentContext = $relationship->source_document_id === $document->id;

        if (! ($isAdmin || $isCreator || $isSourceDocumentContext)) {
            abort(403);
        }

        $relationship->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Relacionamento removido com sucesso.');
    }

    public function resolvePendency(Request $request, Document $document, DocumentRelationship $relationship)
    {
        $user = auth()->user();

        $isRelatedToDocument = $relationship->source_document_id === $document->id
            || $relationship->target_document_id === $document->id;

        if (! $isRelatedToDocument) {
            abort(403);
        }

        $isAdmin = $user?->isAdmin() ?? false;
        $isCreator = $user && (int) $relationship->created_by === (int) $user->id;
        $isSourceDocumentContext = $relationship->source_document_id === $document->id;

        if (! ($isAdmin || $isCreator || $isSourceDocumentContext)) {
            abort(403);
        }

        $validated = $request->validate([
            'pendency_id' => 'nullable|integer',
        ]);

        $pendencyQuery = DocumentRelationshipPendency::query()
            ->where('relationship_id', $relationship->id)
            ->whereNull('reviewed_at');

        if (!empty($validated['pendency_id'])) {
            $pendencyQuery->where('id', $validated['pendency_id']);
        } else {
            $pendencyQuery->orderByDesc('id');
        }

        $pendency = $pendencyQuery->first();
        if (!$pendency) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma pendência em aberto para este relacionamento.',
            ], 404);
        }

        $pendency->update([
            'reviewed_by' => $user?->id,
            'reviewed_at' => now(),
        ]);

        $hasPending = DocumentRelationshipPendency::query()
            ->where('relationship_id', $relationship->id)
            ->whereNull('reviewed_at')
            ->exists();

        return response()->json([
            'success' => true,
            'reviewed_pendency_id' => $pendency->id,
            'relationship_id' => $relationship->id,
            'has_pending' => $hasPending,
        ]);
    }
}
