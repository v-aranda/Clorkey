<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
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
        $document->load(['book', 'folder', 'user', 'links.source']);

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

        return back()->with('success', 'Documento salvo com sucesso.');
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
