<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, MessageSquare, Info } from 'lucide-vue-next';
import UserAvatar from '@/Components/UserAvatar.vue';
import TaskChat from '@/Components/TaskChat.vue';
import MessageComposer from '@/Components/MessageComposer.vue';

const props = defineProps({
    task: { type: Object, default: null },
    show: { type: Boolean, default: false },
    users: { type: Array, default: () => [] },
    currentUser: { type: Object, default: null },
});

const emit = defineEmits(['close', 'deleted']);

const detailViewTab = ref('chat');

const deleteForm = useForm({});

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

function normalizeId(value) {
    const n = Number(value);
    return Number.isNaN(n) ? null : n;
}

function userOrPlaceholder(id) {
    const nid = normalizeId(id);
    return props.users.find(u => normalizeId(u.id) === nid)
        ?? { id: nid, name: 'Usuário', avatar_url: null };
}

function deleteTask() {
    if (!props.task?.id) return;
    if (!window.confirm('Tem certeza que deseja remover esta tarefa?')) return;
    deleteForm.delete(route('agenda.tasks.destroy', props.task.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('deleted', props.task.id);
            emit('close');
        },
    });
}
</script>

<template>
    <div :class="[
        'transition-all duration-300 ease-in-out bg-white border-l shadow-sm shrink-0 flex flex-col',
        show ? 'w-[400px] opacity-100' : 'w-0 opacity-0 overflow-hidden border-l-0'
    ]">
        <div v-if="show" class="w-[400px] flex flex-col h-full shrink-0">
            <!-- Header -->
            <div class="flex items-start justify-between border-b px-6 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{
                        formatDateLabel(task?.date) }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-900">{{ task?.name }}</h2>
                    <div v-if="task?.participants && task.participants.length" class="mt-3">
                        <div class="flex items-center">
                            <div class="flex -space-x-2">
                                <template
                                    v-for="(pid, idx) in (task.participants.slice ? task.participants.slice(0, 3) : task.participants)"
                                    :key="`detail-p-${pid}-${idx}`">
                                    <div class="rounded-full ring-2 ring-white bg-white">
                                        <UserAvatar :user="userOrPlaceholder(pid)" size="xs" />
                                    </div>
                                </template>
                            </div>
                            <div v-if="task.participants.length > 3" class="ml-2 text-xs text-gray-500">
                                +{{ task.participants.length - 3 }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button v-if="task?.id" @click="deleteTask" :disabled="deleteForm.processing"
                        class="inline-flex items-center rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-100 disabled:opacity-60">
                        {{ deleteForm.processing ? 'Removendo...' : 'Excluir' }}
                    </button>
                    <button @click="$emit('close')"
                        class="rounded-md p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600">
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-200 px-6 shrink-0">
                <button @click="detailViewTab = 'chat'" :class="[
                    'px-4 py-2.5 -mb-px text-sm font-medium flex items-center gap-2 border-b-2 transition-colors',
                    detailViewTab === 'chat'
                        ? 'border-indigo-500 text-indigo-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                ]">
                    <MessageSquare class="h-4 w-4" />
                    <span>Chat</span>
                </button>
                <button @click="detailViewTab = 'info'" :class="[
                    'px-4 py-2.5 -mb-px text-sm font-medium flex items-center gap-2 border-b-2 transition-colors',
                    detailViewTab === 'info'
                        ? 'border-indigo-500 text-indigo-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                ]">
                    <Info class="h-4 w-4" />
                    <span>Informações</span>
                </button>
            </div>

            <!-- Body -->
            <div class="flex-1 flex flex-col min-h-0">

                <!-- Chat tab -->
                <template v-if="detailViewTab === 'chat'">
                    <div class="flex-1 min-h-0">
                        <TaskChat v-if="task?.id" :task-id="task.id" :users="users" :current-user="currentUser" />
                        <div v-else class="flex items-center justify-center h-full text-sm text-gray-400">Selecione uma
                            tarefa para ver o chat.</div>
                    </div>

                    <!-- Composer -->
                    <div class="border-t px-4 py-3 bg-white shrink-0">
                        <MessageComposer v-if="task?.id" :task-id="task.id" :users="users" />
                    </div>
                </template>

                <!-- Info tab -->
                <div v-else class="flex-1 overflow-y-auto px-6 py-6">
                    <div v-if="task?.description" class="prose prose-sm max-w-none text-gray-700"
                        v-html="task.description"></div>
                    <p v-else class="text-sm text-gray-400">Nenhuma descrição informada.</p>
                </div>
            </div>

        </div>
    </div>
</template>
