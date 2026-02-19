<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryFolder;
use Illuminate\Http\Request;

class NavigatorController extends Controller
{
    /**
     * List all books (root level of navigation).
     */
    public function books()
    {
        $books = LibraryBook::select('id', 'title')->orderBy('title')->get();

        return response()->json($books);
    }

    /**
     * List folders inside a book, optionally filtered by parent_id.
     * Also indicates whether each folder has children (for expand arrow).
     */
    public function folders(LibraryBook $book, Request $request)
    {
        $parentId = $request->query('parent_id');

        $folders = $book->folders()
            ->select('id', 'name', 'parent_id', 'library_book_id')
            ->where('parent_id', $parentId)
            ->orderBy('name')
            ->get()
            ->map(function ($folder) {
                $folder->has_children = $folder->children()->exists();
                return $folder;
            });

        return response()->json($folders);
    }
}
