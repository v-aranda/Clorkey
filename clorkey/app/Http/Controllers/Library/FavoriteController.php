<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\LibraryFavorite;
use App\Models\LibraryFile;
use App\Models\LibraryFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /**
     * Toggle favorite status for a file or folder.
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'type' => 'required|in:file,folder',
            'id' => 'required|integer',
        ]);

        $type = $request->type === 'file'
            ? LibraryFile::class
            : LibraryFolder::class;

        $existing = LibraryFavorite::where('user_id', Auth::id())
            ->where('favoritable_type', $type)
            ->where('favoritable_id', $request->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['favorited' => false]);
        }

        LibraryFavorite::create([
            'user_id' => Auth::id(),
            'favoritable_type' => $type,
            'favoritable_id' => $request->id,
        ]);

        return response()->json(['favorited' => true]);
    }

    /**
     * List all favorites for the authenticated user (used in Library Index).
     */
    public function index()
    {
        $favorites = LibraryFavorite::where('user_id', Auth::id())
            ->with('favoritable')
            ->latest()
            ->get()
            ->filter(fn($fav) => $fav->favoritable !== null)
            ->map(function ($fav) {
                $item = $fav->favoritable;
                $isFile = $item instanceof LibraryFile;

                return [
                    'id' => $fav->id,
                    'type' => $isFile ? 'file' : 'folder',
                    'item_id' => $item->id,
                    'name' => $isFile ? $item->name : $item->name,
                    'mime_type' => $isFile ? $item->mime_type : null,
                    'preview_url' => $isFile ? $item->preview_url : null,
                    'file_url' => $isFile ? $item->file_url : null,
                    'book_id' => $item->library_book_id,
                    'book_title' => $item->book?->title ?? '',
                    'folder_id' => $isFile ? $item->library_folder_id : $item->parent_id,
                ];
            })
            ->values();

        return $favorites;
    }
}
