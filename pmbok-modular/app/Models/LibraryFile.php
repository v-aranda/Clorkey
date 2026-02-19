<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryFile extends Model
{
    protected $guarded = [];

    protected $appends = ['preview_url', 'file_url'];

    public function getPreviewUrlAttribute()
    {
        if ($this->preview_path) {
            return \Illuminate\Support\Facades\Storage::disk('s3')->url($this->preview_path);
        }
        return null;
    }

    public function getFileUrlAttribute()
    {
        if ($this->path) {
            return \Illuminate\Support\Facades\Storage::disk('s3')->url($this->path);
        }
        return null;
    }

    public function book()
    {
        return $this->belongsTo(LibraryBook::class, 'library_book_id');
    }

    public function folder()
    {
        return $this->belongsTo(LibraryFolder::class, 'library_folder_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
