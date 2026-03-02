<script setup>
import { ref } from 'vue';
import { useQuadroStore } from '@/stores/quadroStore';
import { useToast } from '@/composables/useToast';
import UserAvatar from '@/Components/UserAvatar.vue';
import { GripVertical, Play, Pause, Check, CalendarDays } from 'lucide-vue-next';

const props = defineProps({
    task: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    isActive: { type: Boolean, default: false },
});

const emit = defineEmits(['open-detail']);

const store = useQuadroStore();
const { showToast, toastMessage, triggerToast } = useToast();

// Track mouse position to distinguish click from drag
const mouseDownPos = ref(null);

function onMouseDown(e) {
    // Skip tracking if the mousedown originated from the drag handle or action buttons
    // so that SortableJS can still receive the event for drag-and-drop
    const target = e.target.closest('.drag-handle, button');
    if (target) return;
    mouseDownPos.value = { x: e.clientX, y: e.clientY };
}

function onMouseUp(e) {
    if (!mouseDownPos.value) return;
    const dx = Math.abs(e.clientX - mouseDownPos.value.x);
    const dy = Math.abs(e.clientY - mouseDownPos.value.y);
    mouseDownPos.value = null;
    // Only open detail if cursor barely moved (real click, not a drag)
    if (dx < 5 && dy < 5) {
        emit('open-detail', props.task);
    }
}

const statusConfig = {
    todo: { label: 'A Fazer', classes: 'bg-blue-100 text-blue-700' },
    doing: { label: 'Fazendo', classes: 'bg-amber-100 text-amber-700' },
    stopped: { label: 'Parado', classes: 'bg-red-100 text-red-700' },
    done: { label: 'Feito', classes: 'bg-emerald-100 text-emerald-700' },
};

function stripHtml(html) {
    if (!html) return '';
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}

function getUserById(id) {
    return props.users.find(u => u.id === id);
}

async function changeStatus(newStatus) {
    try {
        await store.updateTaskStatus(props.task.id, newStatus);
    } catch {
        triggerToast('Erro ao atualizar status.');
    }
}

function getStatusInfo(status) {
    return statusConfig[status] || statusConfig.todo;
}
</script>

<template>
    <div :class="[
        'flex cursor-pointer items-center gap-3 border-b px-4 py-3 transition-all last:border-b-0',
        isActive ? 'bg-indigo-50/40 border-transparent relative z-10 shadow-[0_0_15px_rgba(99,102,241,0.3)] rounded-lg' : 'border-gray-50 hover:bg-gray-50'
    ]" @mousedown="onMouseDown" @mouseup="onMouseUp">
        <!-- LEFT SIDE: Drag handle + Action buttons -->
        <div class="flex shrink-0 items-center gap-1.5">
            <!-- Drag handle -->
            <div
                class="drag-handle cursor-grab text-gray-300 transition-colors hover:text-gray-500 active:cursor-grabbing">
                <GripVertical class="h-5 w-5" />
            </div>

            <!-- Action buttons per status -->
            <!-- todo: Play → doing -->
            <button v-if="task.status === 'todo'" @click.stop="changeStatus('doing')"
                class="rounded-md bg-amber-100 p-1.5 text-amber-600 transition-colors hover:bg-amber-200"
                title="Iniciar">
                <Play class="h-4 w-4" />
            </button>

            <!-- doing: Check → done, Pause → stopped -->
            <template v-if="task.status === 'doing'">
                <button @click.stop="changeStatus('done')"
                    class="rounded-md bg-emerald-100 p-1.5 text-emerald-600 transition-colors hover:bg-emerald-200"
                    title="Concluir">
                    <Check class="h-4 w-4" />
                </button>
                <button @click.stop="changeStatus('stopped')"
                    class="rounded-md bg-red-100 p-1.5 text-red-600 transition-colors hover:bg-red-200" title="Pausar">
                    <Pause class="h-4 w-4" />
                </button>
            </template>

            <!-- stopped: Play → doing -->
            <button v-if="task.status === 'stopped'" @click.stop="changeStatus('doing')"
                class="rounded-md bg-amber-100 p-1.5 text-amber-600 transition-colors hover:bg-amber-200"
                title="Retomar">
                <Play class="h-4 w-4" />
            </button>

            <!-- done: no action buttons -->
        </div>

        <!-- CENTER: Task info -->
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-gray-800">{{ task.name }}</p>
            <div class="mt-0.5 flex items-center gap-3">
                <p v-if="task.description" class="truncate text-xs text-gray-500">{{ stripHtml(task.description) }}</p>
                <div v-if="task.date" class="flex shrink-0 items-center gap-1 text-xs text-gray-400">
                    <CalendarDays class="h-3 w-3" />
                    <span>{{ task.date }}</span>
                    <span v-if="task.start_time">{{ task.start_time }}</span>
                </div>
            </div>
        </div>

        <!-- Participants -->
        <div class="flex shrink-0 -space-x-1">
            <UserAvatar v-for="pid in (task.participants || []).slice(0, 3)" :key="pid"
                :user="getUserById(pid) || { name: '?' }" size="xs" class="ring-2 ring-white" />
            <span v-if="(task.participants || []).length > 3"
                class="flex h-5 w-5 items-center justify-center rounded-full bg-gray-200 text-[10px] font-medium text-gray-600 ring-2 ring-white">
                +{{ (task.participants || []).length - 3 }}
            </span>
        </div>

        <!-- RIGHT SIDE: Status badge -->
        <span
            :class="['inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', getStatusInfo(task.status).classes]">
            {{ getStatusInfo(task.status).label }}
        </span>

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
    </div>
</template>
