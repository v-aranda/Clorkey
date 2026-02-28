<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\LibraryFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FolderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'library_book_id' => 'required|exists:library_books,id',
            'parent_id' => 'nullable|exists:library_folders,id',
        ]);

        $folder = LibraryFolder::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Pasta criada com sucesso.');
    }

    public function update(Request $request, LibraryFolder $folder)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $folder->update($validated);

        return back()->with('success', 'Pasta atualizada com sucesso.');
    }

    public function destroy(LibraryFolder $folder)
    {
        // Recursively delete contents or enable database cascading (already set in migration)
        $folder->delete();

        return back()->with('success', 'Pasta removida com sucesso.');
    }
}
