<?php

namespace App\Http\Requests;

use App\Models\AgendaTask;
use App\Rules\NotCircularParent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgendaTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $context = $this->input('context');
        $requiresDateTime = $context !== 'assigned';

        if ($context === 'drag_drop') {
            $requiresDateTime = true;
        }

        if ($context === 'quadro_status') {
            $requiresDateTime = false;
        }

        if ($context === 'gantt_resize') {
            $requiresDateTime = false;
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'date' => $requiresDateTime
                ? ['required', 'date']
                : ['sometimes', 'nullable', 'date'],
            'start_time' => $requiresDateTime
                ? ['required', 'date_format:H:i']
                : ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i', 'after:start_time'],
            'participants' => ['sometimes', 'nullable', 'array'],
            'participants.*' => ['integer', 'exists:users,id'],
            'context' => ['nullable', 'string'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(AgendaTask::STATUSES)],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:agenda_tasks,id', new NotCircularParent($this->route('agendaTask')?->id)],
            'deadline' => ['sometimes', 'nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('participants')) {
            $merge['participants'] = collect($this->input('participants', []))
                ->filter(fn($id) => $id !== null && $id !== '')
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        if ($this->has('date')) {
            $merge['date'] = $this->filled('date') && trim((string) $this->input('date')) !== ''
                ? $this->input('date')
                : null;
        }

        if ($this->has('start_time')) {
            $merge['start_time'] = $this->filled('start_time') && trim((string) $this->input('start_time')) !== ''
                ? $this->input('start_time')
                : null;
        }

        if ($this->has('end_time')) {
            $merge['end_time'] = $this->filled('end_time') && trim((string) $this->input('end_time')) !== ''
                ? $this->input('end_time')
                : null;
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }
}
