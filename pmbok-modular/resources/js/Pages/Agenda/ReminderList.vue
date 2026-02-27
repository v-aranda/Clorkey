<script setup>
import { ref, computed } from 'vue';
import { Flag, Bell, Plus, X, Loader2 } from 'lucide-vue-next';
import ParticipantSelector from './ParticipantSelector.vue';

const props = defineProps({
    holidays: { type: Array, default: () => [] },    // { date, name, type }
    reminders: { type: Array, default: () => [] },   // { id, title, date, participants, user_id }
    selectedDate: { type: String, default: '' },     // YYYY-MM-DD
    users: { type: Array, default: () => [] },
    currentUserId: { type: Number, default: null },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['reminder-created', 'reminder-deleted']);

// ─── New Reminder Form ────────────────────────────────────────────────────────
const showForm = ref(false);
const newTitle = ref('');
const newParticipants = ref([]);
const saving = ref(false);
const formError = ref('');

function openForm() {
    showForm.value = true;
    newTitle.value = '';
    // Pre-fill creator so the reminder is visible to them by default
    newParticipants.value = props.currentUserId ? [props.currentUserId] : [];
    formError.value = '';
}

function closeForm() {
    showForm.value = false;
}

async function submitReminder() {
    if (!newTitle.value.trim()) {
        formError.value = 'O título é obrigatório.';
        return;
    }
    saving.value = true;
    formError.value = '';
    try {
        emit('reminder-created', {
            title: newTitle.value.trim(),
            date: props.selectedDate,
            participants: [...newParticipants.value],
        });
        showForm.value = false;
    } finally {
        saving.value = false;
    }
}

function deleteReminder(id) {
    emit('reminder-deleted', id);
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
const hasContent = computed(() => props.holidays.length > 0 || props.reminders.length > 0 || showForm.value);
</script>

<template>
    <div class="mt-5">
        <!-- Section header -->
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Lembretes</span>
            <button
                v-if="!showForm"
                type="button"
                @click="openForm"
                class="flex items-center gap-1 rounded-md px-2 py-1 text-xs text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors"
                title="Adicionar lembrete"
            >
                <Plus class="h-3.5 w-3.5" />
                Novo
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-4">
            <Loader2 class="h-4 w-4 animate-spin text-gray-400" />
        </div>

        <template v-else>
            <!-- Holidays -->
            <div v-if="holidays.length" class="mb-2 space-y-1">
                <div
                    v-for="h in holidays"
                    :key="h.date + h.name"
                    class="flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2"
                >
                    <Flag class="h-3.5 w-3.5 shrink-0 text-amber-500" />
                    <span class="text-xs font-medium text-amber-800 leading-tight">{{ h.name }}</span>
                    <span class="ml-auto text-[10px] text-amber-500 capitalize">{{ h.type === 'national' ? 'Nacional' : h.type }}</span>
                </div>
            </div>

            <!-- User Reminders -->
            <div v-if="reminders.length" class="mb-2 space-y-1">
                <div
                    v-for="r in reminders"
                    :key="r.id"
                    class="flex items-center gap-2 rounded-lg bg-blue-50 border border-blue-100 px-3 py-2"
                >
                    <Bell class="h-3.5 w-3.5 shrink-0 text-blue-400" />
                    <span class="flex-1 text-xs font-medium text-blue-800 leading-tight truncate">{{ r.title }}</span>
                    <button
                        v-if="r.user_id === currentUserId"
                        type="button"
                        @click="deleteReminder(r.id)"
                        class="ml-1 rounded p-0.5 text-blue-300 hover:text-red-400 transition-colors"
                        title="Remover lembrete"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <p v-if="!hasContent" class="text-xs text-gray-400 py-2 text-center">
                Nenhum lembrete para este dia.
            </p>

            <!-- New reminder form -->
            <div v-if="showForm" class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-3 space-y-2">
                <input
                    v-model="newTitle"
                    type="text"
                    placeholder="Título do lembrete..."
                    class="flex h-8 w-full rounded-md border border-input bg-white px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    @keyup.enter="submitReminder"
                    @keyup.esc="closeForm"
                    autofocus
                />
                <p v-if="formError" class="text-xs text-red-500">{{ formError }}</p>

                <ParticipantSelector
                    :users="users"
                    v-model="newParticipants"
                />

                <div class="flex gap-2 pt-1">
                    <button
                        type="button"
                        @click="submitReminder"
                        :disabled="saving"
                        class="flex-1 inline-flex items-center justify-center rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700 disabled:opacity-50 transition-colors"
                    >
                        <Loader2 v-if="saving" class="h-3 w-3 animate-spin mr-1" />
                        Salvar
                    </button>
                    <button
                        type="button"
                        @click="closeForm"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors"
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
