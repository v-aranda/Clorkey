<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaActivity extends Model
{
    protected $guarded = [];

    protected $casts = [
        'participants' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(AgendaTask::class, 'agenda_task_id');
    }
}
