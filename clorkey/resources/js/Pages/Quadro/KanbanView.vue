<script setup>
import { computed } from 'vue';
import { useQuadroStore } from '@/stores/quadroStore';
import KanbanColumn from './KanbanColumn.vue';

defineProps({
    users: { type: Array, default: () => [] },
    activeTaskId: { type: [Number, String], default: null },
});

const emit = defineEmits(['open-detail']);

const store = useQuadroStore();

const columns = [
    { key: 'todo', dropStatus: 'todo', label: 'A Fazer', color: 'border-t-blue-400', badge: 'bg-blue-100 text-blue-700' },
    { key: 'doing', dropStatus: 'doing', label: 'Fazendo', color: 'border-t-amber-400', badge: 'bg-amber-100 text-amber-700' },
    { key: 'done', dropStatus: 'done', label: 'Feito', color: 'border-t-emerald-400', badge: 'bg-emerald-100 text-emerald-700' },
];

// "Fazendo" column shows both doing + stopped tasks
const columnTasks = computed(() => ({
    todo: store.tasksByStatus.todo,
    doing: [...store.tasksByStatus.doing, ...store.tasksByStatus.stopped],
    done: store.tasksByStatus.done,
}));
</script>

<template>
    <div class="flex gap-4 overflow-x-auto pb-4 h-full min-h-0">
        <KanbanColumn v-for="col in columns" :key="col.key" :status="col.dropStatus" :label="col.label"
            :color="col.color" :badge="col.badge" :tasks="columnTasks[col.key]" :users="users"
            :active-task-id="activeTaskId" @open-detail="(t) => emit('open-detail', t)" />
    </div>
</template>
