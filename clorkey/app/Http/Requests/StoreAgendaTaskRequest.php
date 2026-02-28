<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendaTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                            => ['required', 'string', 'max:255'],
            'description'                     => ['nullable', 'string'],
            'date'                            => ['required', 'date'],
            'start_time'                      => ['required', 'date_format:H:i'],
            'end_time'                        => ['nullable', 'date_format:H:i', 'after:start_time'],
            'participants'                    => ['nullable', 'array'],
            'participants.*'                  => ['integer', 'exists:users,id'],
            // Recorrência
            'recurrence'                      => ['nullable', 'array'],
            'recurrence.type'                 => ['required_with:recurrence', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'recurrence.interval'             => ['required_if:recurrence.type,daily,monthly,yearly', 'nullable', 'integer', 'min:1', 'max:30'],
            'recurrence.days_of_week'         => ['required_if:recurrence.type,weekly', 'nullable', 'array', 'min:1'],
            'recurrence.days_of_week.*'       => ['integer', 'between:0,6'],
            'recurrence.end_date'             => ['required_with:recurrence', 'date', 'after:date'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                       => 'O nome da tarefa é obrigatório.',
            'date.required'                       => 'A data é obrigatória.',
            'start_time.required'                 => 'O horário de início é obrigatório.',
            'start_time.date_format'              => 'O horário deve estar no formato HH:MM.',
            'end_time.after'                      => 'O horário de término deve ser após o início.',
            'recurrence.type.required_with'       => 'Selecione o tipo de recorrência.',
            'recurrence.interval.required_if'     => 'Informe o intervalo de recorrência.',
            'recurrence.interval.min'             => 'O intervalo mínimo é 1.',
            'recurrence.interval.max'             => 'O intervalo máximo é 30.',
            'recurrence.days_of_week.required_if' => 'Selecione ao menos um dia da semana.',
            'recurrence.end_date.required_with'   => 'Informe a data de término da recorrência.',
            'recurrence.end_date.after'           => 'A data de término deve ser após a data de início.',
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
            'participants' => $participants,
            'end_time' => $this->filled('end_time') && trim((string) $this->input('end_time')) !== ''
                ? $this->input('end_time')
                : null,
        ]);
    }
}
