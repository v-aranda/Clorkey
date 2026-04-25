<script setup>
import { ref, computed, watch } from 'vue';
import draggable from 'vuedraggable';
import { useQuadroStore } from '@/stores/quadroStore';
import { useToast } from '@/composables/useToast';
import TodoListRow from './TodoListRow.vue';

defineProps({
    users: { type: Array, default: () => [] },
    activeTaskId: { type: [Number, String], default: null },
});

const store = useQuadroStore();
const emit = defineEmits(['open-detail']);
const { showToast, toastMessage, triggerToast } = useToast();

// Local reactive copy for vuedraggable's v-model — reflects filtered view
const localList = ref([]);

watch(
    () => store.filteredTasks,
    (newVal) => {
        localList.value = [...newVal];
    },
    { immediate: true },
);

async function onDragEnd() {
    try {
        // When filter is active we need to merge the reordered filtered subset
        // back into the full sorted list before persisting sort_order.
        if (store.selectedParticipantId !== null) {
            const filteredIds = new Set(localList.value.map(t => t.id));
            const full = [...store.sortedTasks];
            const slots = full.map((t, i) => filteredIds.has(t.id) ? i : -1).filter(i => i >= 0);
            const merged = [...full];
            slots.forEach((idx, i) => { merged[idx] = localList.value[i]; });
            await store.reorderTasks(merged);
        } else {
            await store.reorderTasks(localList.value);
        }
    } catch {
        triggerToast('Erro ao salvar a ordem.');
    }
}
</script>

<template>
    <div class="rounded-lg border bg-white shadow-sm flex flex-col h-full min-h-0">
        <!-- List header -->
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 shrink-0">
            <h3 class="text-sm font-semibold text-gray-700">Todas as Tarefas</h3>
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                {{ localList.length }}
            </span>
        </div>

        <!-- Draggable list -->
        <draggable v-model="localList" item-key="id" handle=".drag-handle" ghost-class="opacity-30" animation="200"
            @end="onDragEnd" class="flex-1 overflow-y-auto min-h-0">
            <template #item="{ element }">
                <TodoListRow :task="element" :users="users" :is-active="element.id === activeTaskId"
                    @open-detail="(t) => emit('open-detail', t)" />
            </template>
        </draggable>

        <!-- Empty state -->
        <div v-if="localList.length === 0" class="px-4 py-8 text-center text-sm text-gray-400">
            Nenhuma tarefa
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
