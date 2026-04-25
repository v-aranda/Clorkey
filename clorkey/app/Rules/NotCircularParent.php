<?php

namespace App\Rules;

use App\Models\AgendaTask;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotCircularParent implements ValidationRule
{
    public function __construct(private readonly ?int $taskId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $this->taskId === null) {
            return;
        }

        $proposedParentId = (int) $value;

        if ($proposedParentId === $this->taskId) {
            $fail('Uma tarefa não pode ser pai de si mesma.');
            return;
        }

        if ($this->isDescendant($proposedParentId)) {
            $fail('Não é possível criar uma referência circular entre tarefas pai/filho.');
        }
    }

    private function isDescendant(int $proposedParentId): bool
    {
        $visited = [];
        $queue = [$this->taskId];

        while (!empty($queue)) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            $children = AgendaTask::where('parent_id', $current)->pluck('id')->all();

            foreach ($children as $childId) {
                if ((int) $childId === $proposedParentId) {
                    return true;
                }
                $queue[] = (int) $childId;
            }
        }

        return false;
    }
}
