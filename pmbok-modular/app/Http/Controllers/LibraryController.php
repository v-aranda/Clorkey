<?php

namespace App\Http\Controllers;

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

        return \Inertia\Inertia::render('Library/Index', [
            'books' => $books,
            'filters' => request()->all(['search']),
        ]);
    }
}
