<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentLinkRequest;
use App\Models\Document;
use App\Models\DocumentLink;

class DocumentLinkController extends Controller
{
    public function index(Document $document)
    {
        $links = $document->links()->with('source')->get();

        return response()->json($links);
    }

    public function store(StoreDocumentLinkRequest $request, Document $document)
    {
        // Verify source exists
        $sourceClass = $request->source_type;
        $source = $sourceClass::find($request->source_id);

        if (!$source) {
            return response()->json(['message' => 'Fonte não encontrada.'], 404);
        }

        $link = $document->links()->create([
            'link_type' => $request->link_type,
            'source_type' => $request->source_type,
            'source_id' => $request->source_id,
            'source_section' => $request->source_section,
            'source_meta' => $request->source_meta,
            'position' => $request->position ?? $document->links()->count(),
        ]);

        $link->load('source');

        return response()->json($link, 201);
    }

    public function destroy(Document $document, DocumentLink $link)
    {
        // Ensure link belongs to this document
        if ($link->document_id !== $document->id) {
            return response()->json(['message' => 'Link não pertence a este documento.'], 403);
        }

        $link->delete();

        return response()->json(['message' => 'Ligação removida com sucesso.']);
    }
}
