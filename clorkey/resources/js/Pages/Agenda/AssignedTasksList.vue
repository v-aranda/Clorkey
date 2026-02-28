<script setup>
import { computed, ref } from 'vue';
import { CalendarX2, GripVertical, Plus } from 'lucide-vue-next';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['open-task', 'create-task']);

const draggingTaskId = ref(null);

const hasTasks = computed(() => props.tasks.length > 0);

function creatorName(task) {
    return task?.creator?.name || 'Usuário';
}

function statusLabel(task) {
    if (task?.status) return task.status;
    if (task?.priority) return `Prioridade ${task.priority}`;
    return 'Sem horário';
}

function onDragStart(event, task) {
    if (!event?.dataTransfer || !task?.id) return;

    draggingTaskId.value = task.id;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('application/x-agenda-task', String(task.id));
    event.dataTransfer.setData('text/plain', String(task.id));
}

function onDragEnd() {
    draggingTaskId.value = null;
}
</script>

<template>
    <section class="flex h-full flex-col">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-foreground">Tarefas atribuídas</h3>
            <Button size="sm" variant="outline" @click="emit('create-task')">
                <Plus class="mr-1 h-3.5 w-3.5" />
                Nova
            </Button>
        </div>

        <div v-if="loading" class="rounded-lg border border-border bg-muted/40 px-3 py-4 text-center text-sm text-muted-foreground">
            Carregando tarefas atribuídas...
        </div>

        <div
            v-else-if="!hasTasks"
            class="flex min-h-40 flex-1 flex-col items-center justify-center rounded-lg border border-dashed border-border bg-muted/30 px-4 text-center"
        >
            <CalendarX2 class="mb-2 h-5 w-5 text-muted-foreground" />
            <p class="text-sm font-medium text-foreground">Nenhuma tarefa pendente de agendamento</p>
            <p class="mt-1 text-xs text-muted-foreground">Quando algo for atribuído sem data e horário, aparece aqui.</p>
        </div>

        <ul v-else class="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
            <li
                v-for="task in tasks"
                :key="task.id"
                :draggable="true"
                :aria-label="`Arrastar tarefa ${task.name}`"
                :aria-grabbed="draggingTaskId === task.id"
                :class="[
                    'group rounded-lg border border-border bg-card p-3 shadow-sm transition-all',
                    draggingTaskId === task.id
                        ? 'cursor-grabbing border-primary/60 bg-secondary/70 opacity-70 ring-2 ring-primary/40'
                        : 'cursor-grab hover:border-primary/30 hover:bg-accent/40'
                ]"
                @dragstart="onDragStart($event, task)"
                @dragend="onDragEnd"
                @click="emit('open-task', task, $event)"
            >
                <div class="flex items-start gap-2">
                    <GripVertical class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground/80" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-card-foreground">{{ task.name }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">Criado por {{ creatorName(task) }}</p>
                        <div class="mt-2">
                            <Badge variant="secondary" class="text-[11px]">
                                {{ statusLabel(task) }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </li>
        </ul>
    </section>
</template>
