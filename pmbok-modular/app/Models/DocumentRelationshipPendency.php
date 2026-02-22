<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRelationshipPendency extends Model
{
    protected $guarded = [];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function relationship()
    {
        return $this->belongsTo(DocumentRelationship::class, 'relationship_id');
    }

    public function triggerDocument()
    {
        return $this->belongsTo(Document::class, 'trigger_document_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
