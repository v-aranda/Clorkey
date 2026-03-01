<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryFile;
use App\Models\LibraryFolder;
use App\Models\LibraryFavorite;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LibraryController extends Controller
{
    public function show(Request $request, LibraryBook $book, $folderId = null)
    {
        if (mb_strtoupper((string) $book->icon) === 'HEART') {
            return redirect()->route('diary.index');
        }

        $currentFolder = null;
        $breadcrumbs = [];

        if ($folderId) {
            $currentFolder = LibraryFolder::findOrFail($folderId);
            $temp = $currentFolder;
            while ($temp) {
                array_unshift($breadcrumbs, $temp);
                $temp = $temp->parent;
            }
        }

        $queryFolders = $book->folders()->where('parent_id', $folderId);
        $queryFiles = $book->files()->where('library_folder_id', $folderId);
        $queryDocuments = $book->documents()->where('library_folder_id', $folderId);

        // Get favorite IDs for current user as ['file-1', 'folder-2', ...]
        $favoriteIds = LibraryFavorite::where('user_id', Auth::id())
            ->get()
            ->map(function ($fav) {
                if ($fav->favoritable_type === LibraryFile::class)
                    return 'file-' . $fav->favoritable_id;
                if ($fav->favoritable_type === Document::class)
                    return 'document-' . $fav->favoritable_id;
                return 'folder-' . $fav->favoritable_id;
            })
            ->values()
            ->toArray();

        return Inertia::render('Library/Show', [
            'book' => $book,
            'currentFolder' => $currentFolder,
            'folders' => $queryFolders->get(),
            'files' => $queryFiles->get(),
            'documents' => $queryDocuments->get(),
            'breadcrumbs' => $breadcrumbs,
            'favoriteIds' => $favoriteIds,
        ]);
    }
}
