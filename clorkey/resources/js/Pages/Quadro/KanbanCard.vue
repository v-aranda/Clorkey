<script setup>
import { useQuadroStore } from '@/stores/quadroStore';
import { useToast } from '@/composables/useToast';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Pause, Play, CalendarDays } from 'lucide-vue-next';

const props = defineProps({
    task: { type: Object, required: true },
    status: { type: String, required: true },
    users: { type: Array, default: () => [] },
});

const store = useQuadroStore();
const { triggerToast } = useToast();

function onDragStart(e) {
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', String(props.task.id));
}

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
</script>

<template>
    <div
        draggable="true"
        @dragstart="onDragStart"
        :class="[
            'group cursor-grab rounded-lg border bg-white p-3 shadow-sm transition-all hover:shadow-md active:cursor-grabbing',
            task.status === 'stopped' ? 'border-l-4 border-l-red-400 border-t-gray-200 border-r-gray-200 border-b-gray-200' : 'border-gray-200',
        ]"
    >
        <!-- Header: name + parado badge -->
        <div class="flex items-start justify-between gap-2">
            <p class="text-sm font-medium text-gray-800">{{ task.name }}</p>
            <span
                v-if="task.status === 'stopped'"
                class="inline-flex shrink-0 items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700"
            >
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500" />
                Parado
            </span>
        </div>

        <!-- Description preview -->
        <p v-if="task.description" class="mt-1 line-clamp-2 text-xs text-gray-500">
            {{ stripHtml(task.description) }}
        </p>

        <!-- Meta row -->
        <div class="mt-2 flex items-center justify-between">
            <!-- Date badge if scheduled -->
            <div v-if="task.date" class="flex items-center gap-1 text-xs text-gray-400">
                <CalendarDays class="h-3 w-3" />
                <span>{{ task.date }}</span>
                <span v-if="task.start_time" class="ml-0.5">{{ task.start_time }}</span>
            </div>
            <div v-else />

            <!-- Participants -->
            <div class="flex -space-x-1">
                <UserAvatar
                    v-for="pid in (task.participants || []).slice(0, 3)"
                    :key="pid"
                    :user="getUserById(pid) || { name: '?' }"
                    size="xs"
                    class="ring-2 ring-white"
                />
                <span v-if="(task.participants || []).length > 3"
                    class="flex h-5 w-5 items-center justify-center rounded-full bg-gray-200 text-[10px] font-medium text-gray-600 ring-2 ring-white">
                    +{{ (task.participants || []).length - 3 }}
                </span>
            </div>
        </div>

        <!-- Hover action buttons (based on task.status, not column) -->
        <div class="mt-2 flex justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
            <!-- doing task: Pause button (red) -->
            <button
                v-if="task.status === 'doing'"
                @click.stop="changeStatus('stopped')"
                class="rounded-md bg-red-100 p-1.5 text-red-600 transition-colors hover:bg-red-200"
                title="Pausar"
            >
                <Pause class="h-3.5 w-3.5" />
            </button>

            <!-- stopped task: Play button (yellow) -->
            <button
                v-if="task.status === 'stopped'"
                @click.stop="changeStatus('doing')"
                class="rounded-md bg-amber-100 p-1.5 text-amber-600 transition-colors hover:bg-amber-200"
                title="Retomar"
            >
                <Play class="h-3.5 w-3.5" />
            </button>
        </div>
    </div>
</template>
