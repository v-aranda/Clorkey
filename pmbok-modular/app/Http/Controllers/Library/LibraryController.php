<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryFile;
use App\Models\LibraryFolder;
use App\Models\LibraryFavorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LibraryController extends Controller
{
    public function show(Request $request, LibraryBook $book, $folderId = null)
    {
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

        // Get favorite IDs for current user as ['file-1', 'folder-2', ...]
        $favoriteIds = LibraryFavorite::where('user_id', Auth::id())
            ->get()
            ->map(function ($fav) {
                $type = $fav->favoritable_type === LibraryFile::class ? 'file' : 'folder';
                return $type . '-' . $fav->favoritable_id;
            })
            ->values()
            ->toArray();

        return Inertia::render('Library/Show', [
            'book' => $book,
            'currentFolder' => $currentFolder,
            'folders' => $queryFolders->get(),
            'files' => $queryFiles->get(),
            'breadcrumbs' => $breadcrumbs,
            'favoriteIds' => $favoriteIds,
        ]);
    }
}
