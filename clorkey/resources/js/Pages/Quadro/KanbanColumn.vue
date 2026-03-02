<script setup>
import { ref } from 'vue';
import { useQuadroStore } from '@/stores/quadroStore';
import { useToast } from '@/composables/useToast';
import KanbanCard from './KanbanCard.vue';

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, required: true },
    color: { type: String, default: '' },
    bg: { type: String, default: '' },
    badge: { type: String, default: '' },
    tasks: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    activeTaskId: { type: [Number, String], default: null },
});

const emit = defineEmits(['open-detail']);

const store = useQuadroStore();
const { showToast, toastMessage, triggerToast } = useToast();
const isDragOver = ref(false);

function onDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    isDragOver.value = true;
}

function onDragLeave() {
    isDragOver.value = false;
}

async function onDrop(e) {
    e.preventDefault();
    isDragOver.value = false;

    const taskId = parseInt(e.dataTransfer.getData('text/plain'), 10);
    if (!taskId) return;

    try {
        await store.updateTaskStatus(taskId, props.status);
    } catch (err) {
        triggerToast('Erro ao atualizar status da tarefa.');
    }
}
</script>

<template>
    <div class="flex min-w-[280px] flex-1 flex-col rounded-lg border-t-4 bg-white shadow-sm transition-all" :class="[
        color,
        isDragOver ? 'ring-2 ring-indigo-400 ring-offset-2' : '',
    ]" @dragover="onDragOver" @dragleave="onDragLeave" @drop="onDrop">
        <!-- Column Header -->
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <h3 class="text-sm font-semibold text-gray-700">{{ label }}</h3>
            <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', badge]">
                {{ tasks.length }}
            </span>
        </div>

        <!-- Cards -->
        <div class="flex-1 space-y-2 overflow-y-auto p-3">
            <KanbanCard v-for="task in tasks" :key="task.id" :task="task" :status="status" :users="users"
                :is-active="activeTaskId === task.id" @open-detail="(t) => emit('open-detail', t)" />

            <!-- Empty state -->
            <div v-if="tasks.length === 0"
                class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-200 py-8 text-center"
                :class="isDragOver ? 'border-indigo-400 bg-indigo-50' : ''">
                <p class="text-sm text-gray-400">
                    {{ isDragOver ? 'Solte aqui' : 'Nenhuma tarefa' }}
                </p>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <Teleport to="body">
        <Transition enter-active-class="transition ease-out duration-300" enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100" leave-active-class="transition ease-in duration-200"
            leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-4 opacity-0">
            <div v-if="showToast"
                class="fixed bottom-6 right-6 z-50 rounded-lg bg-red-600 px-4 py-3 text-sm font-medium text-white shadow-lg">
                {{ toastMessage }}
            </div>
        </Transition>
    </Teleport>
</template>
