<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
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
            'targetRelationships.sourceDocument'
        ]);

        return Inertia::render('Documents/Edit', [
            'document' => $document,
        ]);
    }

    public function update(UpdateDocumentRequest $request, Document $document)
    {
        $data = [];

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

        if ($request->wantsJson() || $request->ajax() || str_contains($request->header('Accept'), 'application/json')) {
            return response()->json(['success' => true, 'message' => 'Documento salvo com sucesso.']);
        }

        return back()->with('success', 'Documento salvo com sucesso.');
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
