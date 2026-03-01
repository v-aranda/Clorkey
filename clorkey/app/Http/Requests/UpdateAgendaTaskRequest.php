<?php

namespace App\Http\Requests;

use App\Models\AgendaTask;
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $participants = collect($this->input('participants', []))
            ->filter(fn($id) => $id !== null && $id !== '')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'participants' => $this->has('participants') ? $participants : $this->input('participants'),
            'date' => $this->filled('date') && trim((string) $this->input('date')) !== ''
                ? $this->input('date')
                : null,
            'start_time' => $this->filled('start_time') && trim((string) $this->input('start_time')) !== ''
                ? $this->input('start_time')
                : null,
            'end_time' => $this->filled('end_time') && trim((string) $this->input('end_time')) !== ''
                ? $this->input('end_time')
                : null,
        ]);
    }
}
