<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentRelationship;
use Illuminate\Http\Request;

class DocumentRelationshipController extends Controller
{
    public function store(Request $request, Document $document)
    {
        $validated = $request->validate([
            'target_document_id' => 'required|exists:documents,id|different:source_document_id',
            'source_paragraph_id' => 'required|string',
            'target_paragraph_id' => 'required|string',
        ]);

        $document->sourceRelationships()->create([
            'target_document_id' => $validated['target_document_id'],
            'source_paragraph_id' => $validated['source_paragraph_id'],
            'target_paragraph_id' => $validated['target_paragraph_id'],
            'created_by' => auth()->id(),
        ]);

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
}
