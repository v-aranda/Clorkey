<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class LibraryBook extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (Str::startsWith($this->image, ['http://', 'https://', '//'])) {
            return $this->image;
        }

        // Always serve local/public asset for non-HTTP paths
        $path = Str::startsWith($this->image, ['/']) ? $this->image : '/' . ltrim($this->image, '/');
        return URL::to($path);
    }

    public function folders()
    {
        return $this->hasMany(LibraryFolder::class);
    }

    public function files()
    {
        return $this->hasMany(LibraryFile::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'library_book_id');
    }
}
