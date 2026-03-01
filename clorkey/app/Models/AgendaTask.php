<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgendaTask extends Model
{
    use SoftDeletes;

    const STATUS_TODO = 'todo';
    const STATUS_DOING = 'doing';
    const STATUS_STOPPED = 'stopped';
    const STATUS_DONE = 'done';
    const STATUSES = [self::STATUS_TODO, self::STATUS_DOING, self::STATUS_STOPPED, self::STATUS_DONE];

    protected $guarded = [];

    protected $casts = [
        'participants' => 'array',
        'date' => 'date',
        'recurrence_config' => 'array',
        'sort_order' => 'integer',
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
