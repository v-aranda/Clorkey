<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

    public function links()
    {
        return $this->hasMany(DocumentLink::class)->orderBy('position');
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function sourceRelationships()
    {
        return $this->hasMany(DocumentRelationship::class, 'source_document_id');
    }

    public function targetRelationships()
    {
        return $this->hasMany(DocumentRelationship::class, 'target_document_id');
    }
}
