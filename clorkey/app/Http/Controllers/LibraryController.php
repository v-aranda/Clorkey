<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Library\FavoriteController;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    public function index()
    {
        $search = request('search');

        $books = \App\Models\LibraryBook::query()
            ->when($search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->get();

        $favoriteController = new FavoriteController();
        $favorites = $favoriteController->index();

        return \Inertia\Inertia::render('Library/Index', [
            'books' => $books,
            'favorites' => $favorites,
            'filters' => request()->all(['search']),
        ]);
    }
}
