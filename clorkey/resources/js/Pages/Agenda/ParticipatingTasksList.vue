<script setup>
import { Plus } from 'lucide-vue-next';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    users: { type: Array, default: () => [] },
});

const emit = defineEmits(['open-task', 'create-task']);

function normalizeId(value) {
    const n = Number(value);
    return Number.isNaN(n) ? null : n;
}

function userOrPlaceholder(id) {
    const nid = normalizeId(id);
    return props.users.find(u => normalizeId(u.id) === nid)
        ?? { id: nid, name: 'Usuário', avatar_url: null };
}

function formatDateLabel(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-').map(Number);
    if (parts.length !== 3) return dateStr;
    const [year, month, day] = parts;
    if (!year || !month || !day) return dateStr;
    return new Intl.DateTimeFormat('pt-BR', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    }).format(new Date(year, month - 1, day));
}

function formatTimeLabel(timeStr) {
    if (!timeStr) return '--:--';
    return timeStr.slice(0, 5);
}
</script>

<template>
    <div>
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">Minha lista</h3>
            <button
                type="button"
                @click="emit('create-task')"
                class="inline-flex items-center gap-1 rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition-colors hover:bg-gray-800"
            >
                <Plus class="h-3.5 w-3.5" />
                Nova tarefa
            </button>
        </div>
        <div v-if="loading" class="text-center text-sm text-gray-500">Carregando...</div>
        <div v-else>
            <div v-if="tasks.length === 0" class="text-sm text-gray-400">Você não está participando de nenhuma tarefa.</div>
            <ul v-else class="space-y-3">
                <li
                    v-for="t in tasks"
                    :key="t.id"
                    class="p-3 border rounded-md hover:bg-gray-50"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="font-semibold text-gray-800">{{ t.name }}</div>
                            <div class="text-xs text-gray-500">{{ formatDateLabel(t.date) }} • {{ formatTimeLabel(t.start_time) }}</div>

                            <div v-if="t.participants && t.participants.length" class="mt-2">
                                <div class="flex items-center">
                                    <div class="flex -space-x-2">
                                        <template
                                            v-for="(pid, idx) in (t.participants.slice ? t.participants.slice(0, 3) : t.participants)"
                                            :key="`p-${t.id}-${idx}`"
                                        >
                                            <div class="rounded-full ring-2 ring-white bg-white">
                                                <UserAvatar :user="userOrPlaceholder(pid)" size="xs" />
                                            </div>
                                        </template>
                                    </div>
                                    <div v-if="t.participants.length > 3" class="ml-2 text-xs text-gray-500">
                                        +{{ t.participants.length - 3 }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ml-3 flex items-center gap-2">
                            <button
                                @click.stop="$emit('open-task', t, $event)"
                                class="text-sm text-indigo-600"
                            >
                                Ver
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
