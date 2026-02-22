<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/library', [\App\Http\Controllers\LibraryController::class, 'index'])->name('library.index');

    // Knowledge Base / Library Routes
    Route::get('/library/book/{book}', [\App\Http\Controllers\Library\LibraryController::class, 'show'])->name('library.show');
    Route::get('/library/book/{book}/folder/{folderId?}', [\App\Http\Controllers\Library\LibraryController::class, 'show'])->name('library.folder.show');

    Route::post('/library/folders', [\App\Http\Controllers\Library\FolderController::class, 'store'])->name('library.folders.store');
    Route::put('/library/folders/{folder}', [\App\Http\Controllers\Library\FolderController::class, 'update'])->name('library.folders.update');
    Route::delete('/library/folders/{folder}', [\App\Http\Controllers\Library\FolderController::class, 'destroy'])->name('library.folders.destroy');

    Route::post('/library/files', [\App\Http\Controllers\Library\FileController::class, 'store'])->name('library.files.store');
    Route::get('/library/files/{file}/download', [\App\Http\Controllers\Library\FileController::class, 'show'])->name('library.files.download'); // Changed to download for clarity
    Route::put('/library/files/{file}', [\App\Http\Controllers\Library\FileController::class, 'update'])->name('library.files.update');
    Route::delete('/library/files/{file}', [\App\Http\Controllers\Library\FileController::class, 'destroy'])->name('library.files.destroy');
    Route::post('/library/files/download-zip', [\App\Http\Controllers\Library\FileController::class, 'downloadZip'])->name('library.files.download-zip');
    Route::post('/library/files/move', [\App\Http\Controllers\Library\FileController::class, 'move'])->name('library.files.move');

    // Navigator API (JSON)
    Route::get('/library/navigator/books', [\App\Http\Controllers\Library\NavigatorController::class, 'books'])->name('library.navigator.books');
    Route::get('/library/navigator/books/{book}/folders', [\App\Http\Controllers\Library\NavigatorController::class, 'folders'])->name('library.navigator.folders');
    Route::get('/library/navigator/documents', [\App\Http\Controllers\Library\NavigatorController::class, 'documents'])->name('library.navigator.documents');

    // Favorites
    Route::post('/library/favorites/toggle', [\App\Http\Controllers\Library\FavoriteController::class, 'toggle'])->name('library.favorites.toggle');
    // Documents
    Route::post('/library/documents', [\App\Http\Controllers\Library\DocumentController::class, 'store'])->name('library.documents.store');
    Route::get('/library/documents/{document}', [\App\Http\Controllers\Library\DocumentController::class, 'show'])->name('library.documents.show');
    Route::get('/library/documents/{document}/download', [\App\Http\Controllers\Library\DocumentController::class, 'download'])->name('library.documents.download');
    Route::put('/library/documents/{document}', [\App\Http\Controllers\Library\DocumentController::class, 'update'])->name('library.documents.update');
    Route::delete('/library/documents/{document}', [\App\Http\Controllers\Library\DocumentController::class, 'destroy'])->name('library.documents.destroy');
    // Document Versions
    Route::get('/library/documents/{document}/versions', [\App\Http\Controllers\Library\DocumentController::class, 'versions'])->name('library.documents.versions');
    Route::post('/library/documents/{document}/versions', [\App\Http\Controllers\Library\DocumentController::class, 'storeVersion'])->name('library.documents.versions.store');
    Route::get('/library/documents/{document}/versions/{version}', [\App\Http\Controllers\Library\DocumentController::class, 'showVersion'])->name('library.documents.versions.show');
    Route::delete('/library/documents/{document}/versions/{version}', [\App\Http\Controllers\Library\DocumentController::class, 'destroyVersion'])->name('library.documents.versions.destroy');

    // Document Relationships
    Route::post('/library/documents/{document}/relationships', [\App\Http\Controllers\Library\DocumentRelationshipController::class, 'store'])->name('library.documents.relationships.store');
    Route::delete('/library/documents/{document}/relationships/{relationship}', [\App\Http\Controllers\Library\DocumentRelationshipController::class, 'destroy'])->name('library.documents.relationships.destroy');
    Route::post('/library/documents/{document}/relationships/{relationship}/resolve-pendency', [\App\Http\Controllers\Library\DocumentRelationshipController::class, 'resolvePendency'])->name('library.documents.relationships.resolve-pendency');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin routes (protected by 'admin' gate)
Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->except(['show', 'create', 'edit']);
    Route::post('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
});

require __DIR__ . '/auth.php';


