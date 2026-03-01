<script setup>
import { ref, computed, watch } from 'vue';
import draggable from 'vuedraggable';
import { useQuadroStore } from '@/stores/quadroStore';
import { useToast } from '@/composables/useToast';
import TodoListRow from './TodoListRow.vue';

defineProps({
    users: { type: Array, default: () => [] },
});

const store = useQuadroStore();
const { showToast, toastMessage, triggerToast } = useToast();

// Local reactive copy for vuedraggable's v-model
const localList = ref([]);

// Sync from store whenever sortedTasks changes
watch(
    () => store.sortedTasks,
    (newVal) => {
        localList.value = [...newVal];
    },
    { immediate: true },
);

async function onDragEnd() {
    try {
        await store.reorderTasks(localList.value);
    } catch {
        triggerToast('Erro ao salvar a ordem.');
    }
}
</script>

<template>
    <div class="rounded-lg border bg-white shadow-sm">
        <!-- List header -->
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <h3 class="text-sm font-semibold text-gray-700">Todas as Tarefas</h3>
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                {{ localList.length }}
            </span>
        </div>

        <!-- Draggable list -->
        <draggable
            v-model="localList"
            item-key="id"
            handle=".drag-handle"
            ghost-class="opacity-30"
            animation="200"
            @end="onDragEnd"
        >
            <template #item="{ element }">
                <TodoListRow
                    :task="element"
                    :users="users"
                />
            </template>
        </draggable>

        <!-- Empty state -->
        <div v-if="localList.length === 0" class="px-4 py-8 text-center text-sm text-gray-400">
            Nenhuma tarefa
        </div>
    </div>

    <!-- Toast -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-4 opacity-0"
        >
            <div v-if="showToast"
                class="fixed bottom-6 right-6 z-50 rounded-lg bg-red-600 px-4 py-3 text-sm font-medium text-white shadow-lg">
                {{ toastMessage }}
            </div>
        </Transition>
    </Teleport>
</template>
