<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgendaTask extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'participants'      => 'array',
        'date'              => 'date',
        'recurrence_config' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(AgendaActivity::class)->orderBy('order');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TaskMessage::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(TaskValidation::class);
    }
}
