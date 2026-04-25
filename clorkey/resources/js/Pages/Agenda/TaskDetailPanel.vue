<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { X, MessageSquare, Info } from 'lucide-vue-next';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
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
const confirmingTaskDeletion = ref(false);
const deleteMode = ref('single');
const recurrenceOccurrences = ref([]);
const selectedOccurrenceIds = ref([]);
const loadingOccurrences = ref(false);
const deletingTasks = ref(false);
const deleteError = ref('');

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

function formatShortDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-').map(Number);
    if (parts.length !== 3) return dateStr;
    const [year, month, day] = parts;
    if (!year || !month || !day) return dateStr;
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
}

function formatResolution(time, unit) {
    const amount = Number(time);
    if (!amount || !unit) return '';

    if (unit === 'hours') return `${amount} ${amount === 1 ? 'hora' : 'horas'}`;
    if (unit === 'months') return `${amount} ${amount === 1 ? 'mês' : 'meses'}`;
    return `${amount} ${amount === 1 ? 'dia' : 'dias'}`;
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

const canDeleteTask = computed(() =>
    normalizeId(props.currentUser?.id) === normalizeId(props.task?.creator?.id),
);

const hasRecurrenceGroup = computed(() => Boolean(props.task?.recurrence_group_id));

const selectedOccurrencesCount = computed(() => selectedOccurrenceIds.value.length);

function openDeleteModal() {
    if (!props.task?.id) return;

    confirmingTaskDeletion.value = true;
    deleteMode.value = 'single';
    recurrenceOccurrences.value = [];
    selectedOccurrenceIds.value = [props.task.id];
    deleteError.value = '';
}

function closeDeleteModal() {
    confirmingTaskDeletion.value = false;
    deleteMode.value = 'single';
    recurrenceOccurrences.value = [];
    selectedOccurrenceIds.value = [];
    deleteError.value = '';
}

async function loadRecurrenceOccurrences() {
    if (!props.task?.id || !hasRecurrenceGroup.value) return;
    if (loadingOccurrences.value || recurrenceOccurrences.value.length > 0) return;

    loadingOccurrences.value = true;
    deleteError.value = '';

    try {
        const { data } = await axios.get(`/agenda/tasks/${props.task.id}/recurrence-occurrences`, {
            headers: { Accept: 'application/json' },
        });

        recurrenceOccurrences.value = Array.isArray(data?.tasks) ? data.tasks : [];
        selectedOccurrenceIds.value = props.task?.id ? [props.task.id] : [];
    } catch (error) {
        deleteError.value = 'Não foi possível carregar as ocorrências desta recorrência.';
    } finally {
        loadingOccurrences.value = false;
    }
}

function toggleOccurrence(taskId) {
    const id = normalizeId(taskId);
    if (!id) return;

    if (selectedOccurrenceIds.value.includes(id)) {
        selectedOccurrenceIds.value = selectedOccurrenceIds.value.filter((item) => item !== id);
        return;
    }

    selectedOccurrenceIds.value = [...selectedOccurrenceIds.value, id];
}

function toggleAllOccurrences() {
    const allIds = recurrenceOccurrences.value
        .map((item) => normalizeId(item.id))
        .filter((id) => id !== null);

    if (selectedOccurrenceIds.value.length === allIds.length) {
        selectedOccurrenceIds.value = [];
        return;
    }

    selectedOccurrenceIds.value = allIds;
}

function formatOccurrenceLabel(task) {
    const dateLabel = formatShortDate(task?.date);
    const startTime = task?.start_time || '--:--';
    return `${dateLabel} às ${startTime}`;
}

async function confirmDeleteTask() {
    if (!props.task?.id) return;

    const taskIds = deleteMode.value === 'select'
        ? [...selectedOccurrenceIds.value]
        : [props.task.id];

    if (!taskIds.length) {
        deleteError.value = 'Selecione ao menos uma ocorrência para excluir.';
        return;
    }

    deletingTasks.value = true;
    deleteError.value = '';

    try {
        const { data } = await axios.post('/agenda/tasks/bulk-destroy', {
            task_ids: taskIds,
        }, {
            headers: { Accept: 'application/json' },
        });

        emit('deleted', data?.deleted_ids || taskIds);
        closeDeleteModal();
        emit('close');
    } catch (error) {
        deleteError.value = error?.response?.data?.message
            || error?.response?.data?.errors?.task_ids?.[0]
            || 'Não foi possível excluir a(s) tarefa(s). Tente novamente.';
    } finally {
        deletingTasks.value = false;
    }
}

watch(deleteMode, (mode) => {
    if (mode === 'select') {
        loadRecurrenceOccurrences();
    }
});

watch(() => props.show, (show) => {
    if (!show) {
        closeDeleteModal();
    }
});

watch(() => props.task?.id, () => {
    closeDeleteModal();
});
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
                    <button
                        v-if="task?.id && canDeleteTask"
                        @click="openDeleteModal"
                        :disabled="deletingTasks"
                        class="inline-flex items-center rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-100 disabled:opacity-60"
                    >
                        Excluir
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
                    <div v-if="task?.deadline || task?.resolution_time" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Prazo</p>
                        <p v-if="task?.resolution_time" class="mt-1 text-sm font-medium text-amber-900">
                            {{ formatResolution(task.resolution_time, task.resolution_unit) }}
                        </p>
                        <p v-else class="mt-1 text-sm font-medium text-amber-900">{{ formatShortDate(task.deadline) }}</p>
                    </div>
                    <div v-if="task?.description" class="prose prose-sm max-w-none text-gray-700"
                        v-html="task.description"></div>
                    <p v-else class="text-sm text-gray-400">Nenhuma descrição informada.</p>
                </div>
            </div>

            <Modal :show="confirmingTaskDeletion" @close="closeDeleteModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Confirmar exclusão da tarefa
                    </h2>

                    <p class="mt-1 text-sm text-gray-600">
                        Esta ação removerá permanentemente a tarefa e não poderá ser desfeita.
                    </p>

                    <div v-if="hasRecurrenceGroup" class="mt-6 space-y-3">
                        <p class="text-sm font-medium text-gray-900">Como deseja excluir esta recorrência?</p>

                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 transition-colors hover:bg-gray-50">
                            <input
                                v-model="deleteMode"
                                type="radio"
                                class="mt-1"
                                value="single"
                            >
                            <div>
                                <p class="text-sm font-medium text-gray-900">Somente esta tarefa</p>
                                <p class="mt-1 text-xs text-gray-500">Exclui apenas a ocorrência atual.</p>
                            </div>
                        </label>

                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 transition-colors hover:bg-gray-50">
                            <input
                                v-model="deleteMode"
                                type="radio"
                                class="mt-1"
                                value="select"
                            >
                            <div>
                                <p class="text-sm font-medium text-gray-900">Selecionar ocorrências</p>
                                <p class="mt-1 text-xs text-gray-500">Escolha manualmente quais passadas e futuras deseja excluir.</p>
                            </div>
                        </label>
                    </div>

                    <div v-if="deleteMode === 'select'" class="mt-6">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">Ocorrências ligadas a esta tarefa</p>
                            <button
                                type="button"
                                class="text-xs font-medium text-indigo-600 hover:text-indigo-500"
                                @click="toggleAllOccurrences"
                            >
                                {{ selectedOccurrencesCount === recurrenceOccurrences.length && recurrenceOccurrences.length ? 'Limpar seleção' : 'Selecionar todas' }}
                            </button>
                        </div>

                        <div v-if="loadingOccurrences" class="rounded-lg border border-gray-200 px-4 py-6 text-sm text-gray-500">
                            Carregando ocorrências...
                        </div>

                        <div v-else-if="recurrenceOccurrences.length" class="max-h-72 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-2">
                            <label
                                v-for="occurrence in recurrenceOccurrences"
                                :key="occurrence.id"
                                class="flex cursor-pointer items-start gap-3 rounded-md px-3 py-2 transition-colors hover:bg-gray-50"
                            >
                                <input
                                    :checked="selectedOccurrenceIds.includes(occurrence.id)"
                                    type="checkbox"
                                    class="mt-1"
                                    @change="toggleOccurrence(occurrence.id)"
                                >
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900">{{ formatOccurrenceLabel(occurrence) }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ occurrence.name }}</p>
                                </div>
                            </label>
                        </div>

                        <div v-else class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500">
                            Nenhuma ocorrência vinculada foi encontrada.
                        </div>
                    </div>

                    <p v-if="deleteError" class="mt-4 text-sm text-red-600">
                        {{ deleteError }}
                    </p>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeDeleteModal">
                            Cancelar
                        </SecondaryButton>

                        <DangerButton
                            class="ms-3"
                            :class="{ 'opacity-25': deletingTasks }"
                            :disabled="deletingTasks"
                            @click="confirmDeleteTask"
                        >
                            {{ deletingTasks ? 'Excluindo...' : 'Excluir tarefa(s)' }}
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </div>
    </div>
</template>
