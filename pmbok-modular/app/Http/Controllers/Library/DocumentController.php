<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
use App\Models\DocumentRelationship;
use App\Models\DocumentRelationshipPendency;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DocumentController extends Controller
{
    public function store(StoreDocumentRequest $request)
    {
        $document = Document::create([
            'title' => $request->title,
            'content' => '',
            'css' => '',
            'library_book_id' => $request->library_book_id,
            'library_folder_id' => $request->library_folder_id,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('library.documents.show', $document)
            ->with('success', 'Documento criado com sucesso.');
    }

    public function show(Document $document)
    {
        $document->load([
            'book',
            'folder',
            'user',
            'links.source',
            'sourceRelationships.targetDocument',
            'sourceRelationships.pendencies',
            'targetRelationships.sourceDocument',
            'targetRelationships.pendencies',
        ]);

        return Inertia::render('Documents/Edit', [
            'document' => $document,
        ]);
    }

    public function update(UpdateDocumentRequest $request, Document $document)
    {
        $data = [];
        $oldContent = $document->content;
        $pendingRelationshipIds = [];
        $excludeRelationshipId = $request->integer('exclude_relationship_id') ?: null;

        if ($request->has('title')) {
            $data['title'] = $request->title;
        }
        if ($request->has('content')) {
            $data['content'] = $request->content;
        }
        if ($request->has('css')) {
            $data['css'] = $request->css;
        }

        $document->update($data);

        if (array_key_exists('content', $data) && $data['content'] !== $oldContent) {
            $pendingRelationshipIds = $this->markRelationshipPendenciesForChangedParagraphs(
                $document,
                $oldContent,
                $data['content'],
                $excludeRelationshipId
            );
        }

        if ($request->wantsJson() || $request->ajax() || str_contains($request->header('Accept'), 'application/json')) {
            return response()->json([
                'success' => true,
                'message' => 'Documento salvo com sucesso.',
                'pending_relationship_ids' => $pendingRelationshipIds,
            ]);
        }

        return back()->with('success', 'Documento salvo com sucesso.');
    }

    private function markRelationshipPendenciesForChangedParagraphs(
        Document $document,
        string $oldContent,
        string $newContent,
        ?int $excludeRelationshipId = null
    ): array
    {
        $trackedParagraphIds = DocumentRelationship::query()
            ->where('source_document_id', $document->id)
            ->pluck('source_paragraph_id')
            ->merge(
                DocumentRelationship::query()
                    ->where('target_document_id', $document->id)
                    ->pluck('target_paragraph_id')
            )
            ->filter()
            ->unique()
            ->values();

        if ($trackedParagraphIds->isEmpty()) {
            return [];
        }

        $oldMap = $this->extractTrackedParagraphTextMap($oldContent);
        $newMap = $this->extractTrackedParagraphTextMap($newContent);

        $changedParagraphIds = $trackedParagraphIds->filter(function ($paragraphId) use ($oldMap, $newMap) {
            return ($oldMap[$paragraphId] ?? null) !== ($newMap[$paragraphId] ?? null);
        });

        if ($changedParagraphIds->isEmpty()) {
            return [];
        }

        $changedParagraphIdSet = $changedParagraphIds->values()->all();

        $affectedRelationships = DocumentRelationship::query()
            ->where(function ($query) use ($document, $changedParagraphIdSet) {
                $query->where('source_document_id', $document->id)
                    ->whereIn('source_paragraph_id', $changedParagraphIdSet);
            })
            ->orWhere(function ($query) use ($document, $changedParagraphIdSet) {
                $query->where('target_document_id', $document->id)
                    ->whereIn('target_paragraph_id', $changedParagraphIdSet);
            })
            ->get(['id', 'source_document_id', 'source_paragraph_id', 'target_document_id', 'target_paragraph_id']);

        if ($excludeRelationshipId) {
            $affectedRelationships = $affectedRelationships->filter(
                fn ($relationship) => (int) $relationship->id !== (int) $excludeRelationshipId
            )->values();
        }

        if ($affectedRelationships->isEmpty()) {
            return [];
        }

        $rows = [];
        $pendingRelationshipIds = [];
        $existingOpenPendencies = DocumentRelationshipPendency::query()
            ->whereNull('reviewed_at')
            ->whereIn('relationship_id', $affectedRelationships->pluck('id')->all())
            ->get(['relationship_id', 'trigger_document_id', 'trigger_paragraph_id']);

        foreach ($affectedRelationships as $relationship) {
            $triggerParagraphId = null;

            if ((int) $relationship->source_document_id === (int) $document->id && in_array($relationship->source_paragraph_id, $changedParagraphIdSet, true)) {
                $triggerParagraphId = $relationship->source_paragraph_id;
            } elseif ((int) $relationship->target_document_id === (int) $document->id && in_array($relationship->target_paragraph_id, $changedParagraphIdSet, true)) {
                $triggerParagraphId = $relationship->target_paragraph_id;
            }

            if (!$triggerParagraphId) {
                continue;
            }

            $alreadyOpen = $existingOpenPendencies->contains(function ($pendency) use ($relationship, $document, $triggerParagraphId) {
                return (int) $pendency->relationship_id === (int) $relationship->id
                    && (int) $pendency->trigger_document_id === (int) $document->id
                    && (string) $pendency->trigger_paragraph_id === (string) $triggerParagraphId;
            });

            if ($alreadyOpen) {
                continue;
            }

            $rows[] = [
                'relationship_id' => $relationship->id,
                'trigger_document_id' => $document->id,
                'trigger_paragraph_id' => $triggerParagraphId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $pendingRelationshipIds[] = $relationship->id;
        }

        if (!empty($rows)) {
            DocumentRelationshipPendency::query()->insert($rows);
        }

        return array_values(array_unique($pendingRelationshipIds));
    }

    private function extractTrackedParagraphTextMap(?string $html): array
    {
        if (!is_string($html) || trim($html) === '') {
            return [];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*[self::p or self::h1 or self::h2 or self::h3 or self::li][@data-id or @id]');
        if (!$nodes) {
            return [];
        }

        $map = [];
        foreach ($nodes as $node) {
            $id = $node->attributes?->getNamedItem('data-id')?->nodeValue
                ?? $node->attributes?->getNamedItem('id')?->nodeValue;
            if (!$id) {
                continue;
            }
            $text = preg_replace('/\s+/u', ' ', trim($node->textContent ?? ''));
            $map[$id] = $text ?? '';
        }

        return $map;
    }

    public function download(Request $request, Document $document)
    {
        $content = $document->content;
        $title = $document->title;

        if ($request->has('version_id')) {
            $version = $document->versions()->findOrFail($request->version_id);
            $content = $version->content;
            if ($version->label) {
                $title .= ' - ' . $version->label;
            } else {
                $title .= ' - Versão ' . $version->created_at->format('Y-m-d_H-i');
            }
        }

        $tableCss = "
            table { border-collapse: collapse; width: 100%; margin-bottom: 1rem; }
            table, th, td { border: 1px solid black; padding: 0.5rem; }
        ";

        $html = '<html><head><style>' . $document->css . $tableCss . '</style></head><body>' . $content . '</body></html>';
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        return $pdf->download($title . '.pdf');
    }

    public function versions(Document $document)
    {
        $versions = $document->versions()
            ->with('user:id,name')
            ->select('id', 'document_id', 'user_id', 'label', 'created_at')
            ->latest()
            ->get();

        return response()->json($versions);
    }

    public function storeVersion(Request $request, Document $document)
    {
        $request->validate([
            'label' => 'nullable|string|max:255',
        ]);

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'user_id' => Auth::id(),
            'label' => $request->label ?: null,
            'content' => $document->content,
        ]);

        $version->load('user:id,name');

        return response()->json($version);
    }

    public function showVersion(Document $document, DocumentVersion $version)
    {
        abort_if($version->document_id !== $document->id, 404);

        return response()->json(['content' => $version->content]);
    }

    public function destroyVersion(Document $document, DocumentVersion $version)
    {
        abort_if($version->document_id !== $document->id, 404);

        $version->delete();

        return response()->json(['success' => true]);
    }

    public function destroy(Document $document)
    {
        $document->delete();

        // Redirect back to the book/folder where the document was
        if ($document->library_folder_id) {
            return redirect()->route('library.folder.show', [
                'book' => $document->library_book_id,
                'folderId' => $document->library_folder_id,
            ])->with('success', 'Documento removido com sucesso.');
        }

        return redirect()->route('library.show', $document->library_book_id)
            ->with('success', 'Documento removido com sucesso.');
    }
}
