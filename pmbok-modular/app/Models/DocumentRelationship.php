<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRelationship extends Model
{
    protected $guarded = [];

    public function sourceDocument()
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function targetDocument()
    {
        return $this->belongsTo(Document::class, 'target_document_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
