<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryFolder extends Model
{
    protected $guarded = [];

    public function book()
    {
        return $this->belongsTo(LibraryBook::class, 'library_book_id');
    }

    public function parent()
    {
        return $this->belongsTo(LibraryFolder::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(LibraryFolder::class, 'parent_id');
    }

    public function files()
    {
        return $this->hasMany(LibraryFile::class, 'library_folder_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'library_folder_id');
    }
}
