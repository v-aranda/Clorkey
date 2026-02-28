<script setup>
import { ref, watch, nextTick, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { EditorContent } from '@tiptap/vue-3';
import { X, Bold, Italic, Strikethrough, List, ListOrdered, ImageIcon, Link2, Users, Repeat2 } from 'lucide-vue-next';
import ParticipantSelector from './ParticipantSelector.vue';
import { useTiptapEditor } from '@/composables/useTiptapEditor';

const props = defineProps({
    show: { type: Boolean, default: false },
    users: { type: Array, default: () => [] },
    initialHour: { type: Number, default: null },
    initialDate: { type: String, default: '' },
});

const emit = defineEmits(['close', 'created']);

// ─── Task form ────────────────────────────────────────────────────────────────
const taskForm = useForm({
    name: '',
    description: '',
    date: '',
    start_time: '',
    end_time: '',
    participants: [],
    recurrence: null,
});

const { editor, taskImageInput, triggerImageUpload, onImageSelected, insertLink } = useTiptapEditor({
    users: computed(() => props.users),
    onMentionSelect: (userId) => {
        const nid = Number(userId);
        if (!taskForm.participants.includes(nid)) {
            taskForm.participants.push(nid);
        }
    },
});

// ─── Recurrence state ─────────────────────────────────────────────────────────
const recurrenceEnabled  = ref(false);
const recurrenceType     = ref('weekly');
const recurrenceInterval = ref(1);
const recurrenceDays     = ref([]);
const recurrenceEndDate  = ref('');

const recurrenceTypes = [
    { value: 'daily',   label: 'Dias'   },
    { value: 'weekly',  label: 'Semana' },
    { value: 'monthly', label: 'Meses'  },
    { value: 'yearly',  label: 'Anos'   },
];

const weekDayLabels = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
const weekDayFull   = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

const intervalMax = computed(() => {
    if (recurrenceType.value === 'monthly') return 12;
    if (recurrenceType.value === 'yearly')  return 10;
    return 30;
});

const intervalUnit = computed(() => {
    switch (recurrenceType.value) {
        case 'daily':   return recurrenceInterval.value === 1 ? 'dia'   : 'dias';
        case 'monthly': return recurrenceInterval.value === 1 ? 'mês'   : 'meses';
        case 'yearly':  return recurrenceInterval.value === 1 ? 'ano'   : 'anos';
        default:        return '';
    }
});

function toggleDay(dow) {
    const idx = recurrenceDays.value.indexOf(dow);
    if (idx >= 0) {
        recurrenceDays.value.splice(idx, 1);
    } else {
        recurrenceDays.value.push(dow);
    }
}

watch(() => taskForm.date, (val) => {
    if (val && recurrenceType.value === 'weekly' && recurrenceDays.value.length === 0) {
        recurrenceDays.value = [new Date(val + 'T12:00:00').getDay()];
    }
});

watch(recurrenceType, (val) => {
    if (val === 'weekly' && recurrenceDays.value.length === 0 && taskForm.date) {
        recurrenceDays.value = [new Date(taskForm.date + 'T12:00:00').getDay()];
    }
    recurrenceInterval.value = Math.min(recurrenceInterval.value, intervalMax.value);
});

// ─── Reset on open ────────────────────────────────────────────────────────────
watch(() => props.show, (val) => {
    if (!val) return;
    taskForm.reset();
    taskForm.clearErrors();
    taskForm.date = props.initialDate || '';
    taskForm.start_time = props.initialHour != null
        ? `${String(props.initialHour).padStart(2, '0')}:00`
        : '';
    taskForm.end_time = null;
    taskForm.participants = [];
    taskForm.recurrence = null;
    recurrenceEnabled.value  = false;
    recurrenceType.value     = 'weekly';
    recurrenceInterval.value = 1;
    recurrenceDays.value     = taskForm.date
        ? [new Date(taskForm.date + 'T12:00:00').getDay()]
        : [];
    recurrenceEndDate.value  = '';
    nextTick(() => { editor.value?.commands.setContent(''); });
});

// ─── Submit ───────────────────────────────────────────────────────────────────
function submitTask() {
    if (editor.value) {
        taskForm.description = editor.value.getHTML();
    }
    if (!taskForm.end_time || taskForm.end_time.trim() === '') {
        taskForm.end_time = null;
    }

    taskForm.recurrence = recurrenceEnabled.value ? {
        type:         recurrenceType.value,
        interval:     recurrenceInterval.value,
        days_of_week: recurrenceType.value === 'weekly' ? [...recurrenceDays.value] : undefined,
        end_date:     recurrenceEndDate.value,
    } : null;

    taskForm.post(route('agenda.tasks.store'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('created');
            emit('close');
        },
    });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-black/50" @click="$emit('close')" />

            <!-- Panel -->
            <div class="fixed inset-y-0 right-0 z-50 flex w-full max-w-xl flex-col bg-white shadow-xl border-l">

                <!-- Header -->
                <div class="flex items-center justify-between border-b px-6 py-4 shrink-0">
                    <h2 class="text-lg font-semibold text-gray-900">Nova Tarefa</h2>
                    <button @click="$emit('close')" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Scrollable body -->
                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <form @submit.prevent="submitTask" class="space-y-5">

                        <!-- Task Name -->
                        <div>
                            <label class="text-sm font-medium text-gray-700">Nome da Tarefa</label>
                            <input
                                v-model="taskForm.name"
                                type="text"
                                placeholder="Ex: Reunião de planejamento"
                                class="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <p v-if="taskForm.errors.name" class="text-sm text-red-500 mt-1">{{ taskForm.errors.name }}</p>
                        </div>

                        <!-- Date & Start Time (2 cols) -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Data</label>
                                <input
                                    v-model="taskForm.date"
                                    type="date"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                                <p v-if="taskForm.errors.date" class="text-sm text-red-500 mt-1">{{ taskForm.errors.date }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Horário</label>
                                <input
                                    v-model="taskForm.start_time"
                                    type="time"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                                <p v-if="taskForm.errors.start_time" class="text-sm text-red-500 mt-1">{{ taskForm.errors.start_time }}</p>
                            </div>
                        </div>

                        <!-- Recurrence section -->
                        <div class="rounded-lg border border-gray-200 overflow-hidden">
                            <!-- Toggle header -->
                            <button
                                type="button"
                                @click="recurrenceEnabled = !recurrenceEnabled"
                                class="flex w-full items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 transition-colors"
                            >
                                <div class="flex items-center gap-2">
                                    <Repeat2 class="h-4 w-4 text-gray-500" />
                                    <span class="text-sm font-medium text-gray-700">Recorrência</span>
                                </div>
                                <!-- Toggle switch visual -->
                                <div :class="[
                                    'relative h-5 w-9 rounded-full transition-colors duration-200 pointer-events-none',
                                    recurrenceEnabled ? 'bg-gray-900' : 'bg-gray-300'
                                ]">
                                    <div :class="[
                                        'absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform duration-200',
                                        recurrenceEnabled ? 'translate-x-4' : 'translate-x-0.5'
                                    ]" />
                                </div>
                            </button>

                            <!-- Recurrence body -->
                            <div v-if="recurrenceEnabled" class="px-4 py-4 space-y-4 border-t border-gray-100">

                                <!-- Repeat until -->
                                <div>
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Repetir até</label>
                                    <input
                                        v-model="recurrenceEndDate"
                                        type="date"
                                        class="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    />
                                    <p v-if="taskForm.errors['recurrence.end_date']" class="text-xs text-red-500 mt-1">
                                        {{ taskForm.errors['recurrence.end_date'] }}
                                    </p>
                                </div>

                                <!-- Type tabs -->
                                <div>
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Periodicidade</label>
                                    <div class="mt-1.5 flex gap-1 rounded-lg bg-gray-100 p-1">
                                        <button
                                            v-for="t in recurrenceTypes"
                                            :key="t.value"
                                            type="button"
                                            @click="recurrenceType = t.value"
                                            :class="[
                                                'flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-all duration-150',
                                                recurrenceType === t.value
                                                    ? 'bg-white text-gray-900 shadow-sm'
                                                    : 'text-gray-500 hover:text-gray-700'
                                            ]"
                                        >{{ t.label }}</button>
                                    </div>
                                </div>

                                <!-- Weekly: day-of-week picker -->
                                <div v-if="recurrenceType === 'weekly'">
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Dias da semana</label>
                                    <div class="mt-2 flex gap-1.5">
                                        <button
                                            v-for="(label, idx) in weekDayLabels"
                                            :key="idx"
                                            type="button"
                                            @click="toggleDay(idx)"
                                            :title="weekDayFull[idx]"
                                            :class="[
                                                'flex-1 h-9 rounded-lg text-xs font-bold transition-all duration-150',
                                                recurrenceDays.includes(idx)
                                                    ? 'bg-gray-900 text-white shadow-sm ring-2 ring-gray-900 ring-offset-1'
                                                    : 'bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700'
                                            ]"
                                        >{{ label }}</button>
                                    </div>
                                    <p v-if="taskForm.errors['recurrence.days_of_week']" class="text-xs text-red-500 mt-1">
                                        {{ taskForm.errors['recurrence.days_of_week'] }}
                                    </p>
                                </div>

                                <!-- Interval picker: daily / monthly / yearly -->
                                <div v-else>
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Intervalo</label>
                                    <div class="mt-1.5 flex items-center gap-3">
                                        <span class="text-sm text-gray-600 shrink-0">A cada</span>
                                        <div class="flex items-center gap-1 rounded-lg bg-gray-100 p-1">
                                            <button
                                                type="button"
                                                @click="recurrenceInterval = Math.max(1, recurrenceInterval - 1)"
                                                class="h-7 w-7 rounded-md bg-white shadow-sm text-gray-700 font-bold hover:bg-gray-50 transition-colors leading-none"
                                            >−</button>
                                            <span class="w-8 text-center text-sm font-semibold text-gray-900 select-none">{{ recurrenceInterval }}</span>
                                            <button
                                                type="button"
                                                @click="recurrenceInterval = Math.min(intervalMax, recurrenceInterval + 1)"
                                                class="h-7 w-7 rounded-md bg-white shadow-sm text-gray-700 font-bold hover:bg-gray-50 transition-colors leading-none"
                                            >+</button>
                                        </div>
                                        <span class="text-sm text-gray-600">{{ intervalUnit }}</span>
                                    </div>
                                    <p v-if="taskForm.errors['recurrence.interval']" class="text-xs text-red-500 mt-1">
                                        {{ taskForm.errors['recurrence.interval'] }}
                                    </p>
                                </div>

                                <!-- Global recurrence error -->
                                <p v-if="taskForm.errors.recurrence" class="text-sm text-red-500">
                                    {{ taskForm.errors.recurrence }}
                                </p>

                            </div>
                        </div>

                        <!-- Description (TipTap) -->
                        <div>
                            <label class="text-sm font-medium text-gray-700">Descrição</label>
                            <div class="mt-1.5 rounded-md border border-input shadow-sm overflow-hidden">
                                <!-- Mini toolbar -->
                                <div class="flex items-center gap-0.5 border-b border-gray-100 px-2 py-1.5 bg-gray-50/80">
                                    <button
                                        type="button"
                                        @click="editor?.chain().focus().toggleBold().run()"
                                        :class="['rounded p-1.5 transition-colors', editor?.isActive('bold') ? 'bg-gray-200 text-gray-900' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700']"
                                        title="Negrito"
                                    ><Bold class="h-3.5 w-3.5" /></button>
                                    <button
                                        type="button"
                                        @click="editor?.chain().focus().toggleItalic().run()"
                                        :class="['rounded p-1.5 transition-colors', editor?.isActive('italic') ? 'bg-gray-200 text-gray-900' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700']"
                                        title="Itálico"
                                    ><Italic class="h-3.5 w-3.5" /></button>
                                    <button
                                        type="button"
                                        @click="editor?.chain().focus().toggleStrike().run()"
                                        :class="['rounded p-1.5 transition-colors', editor?.isActive('strike') ? 'bg-gray-200 text-gray-900' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700']"
                                        title="Tachado"
                                    ><Strikethrough class="h-3.5 w-3.5" /></button>
                                    <div class="mx-1 h-4 w-px bg-gray-200"></div>
                                    <button
                                        type="button"
                                        @click="editor?.chain().focus().toggleBulletList().run()"
                                        :class="['rounded p-1.5 transition-colors', editor?.isActive('bulletList') ? 'bg-gray-200 text-gray-900' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700']"
                                        title="Lista"
                                    ><List class="h-3.5 w-3.5" /></button>
                                    <button
                                        type="button"
                                        @click="editor?.chain().focus().toggleOrderedList().run()"
                                        :class="['rounded p-1.5 transition-colors', editor?.isActive('orderedList') ? 'bg-gray-200 text-gray-900' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700']"
                                        title="Lista numerada"
                                    ><ListOrdered class="h-3.5 w-3.5" /></button>
                                    <div class="mx-1 h-4 w-px bg-gray-200"></div>
                                    <button
                                        type="button"
                                        @click="triggerImageUpload"
                                        class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700"
                                        title="Inserir imagem"
                                    ><ImageIcon class="h-3.5 w-3.5" /></button>
                                    <button
                                        type="button"
                                        @click="insertLink"
                                        :class="['rounded p-1.5 transition-colors', editor?.isActive('link') ? 'bg-gray-200 text-gray-900' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700']"
                                        title="Inserir link"
                                    ><Link2 class="h-3.5 w-3.5" /></button>
                                </div>
                                <!-- Editor -->
                                <EditorContent :editor="editor" />
                                <input ref="taskImageInput" type="file" accept="image/*" class="hidden" @change="onImageSelected" />
                            </div>
                            <p v-if="taskForm.errors.description" class="text-sm text-red-500 mt-1">{{ taskForm.errors.description }}</p>
                        </div>

                        <!-- Participants -->
                        <div>
                            <label class="text-sm font-medium text-gray-700 flex items-center gap-1.5">
                                <Users class="h-3.5 w-3.5" />
                                Participantes
                            </label>
                            <ParticipantSelector
                                :users="users"
                                v-model="taskForm.participants"
                            />
                        </div>

                        <!-- Submit -->
                        <div class="flex gap-3 pt-4 border-t">
                            <button
                                type="submit"
                                :disabled="taskForm.processing"
                                class="flex-1 inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-gray-800 disabled:opacity-50 transition-colors"
                            >
                                {{ taskForm.processing ? 'Salvando...' : (recurrenceEnabled ? 'Criar Série' : 'Criar Tarefa') }}
                            </button>
                            <button
                                type="button"
                                @click="$emit('close')"
                                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-colors"
                            >
                                Cancelar
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </Teleport>
</template>

<style>
/* TipTap editor styles */
.ProseMirror p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: #9ca3af;
    pointer-events: none;
    height: 0;
}
.ProseMirror .editor-image {
    max-width: 100%;
    height: auto;
    border-radius: 0.375rem;
    margin: 0.5rem 0;
}
.ProseMirror .editor-link {
    color: #2563eb;
    text-decoration: underline;
    cursor: pointer;
}
.ProseMirror .mention {
    background-color: #dbeafe;
    color: #1d4ed8;
    border-radius: 0.25rem;
    padding: 0.125rem 0.375rem;
    font-weight: 500;
    font-size: 0.875rem;
}
.mention-dropdown {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    min-width: 200px;
}
.mention-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    transition: background-color 0.15s;
}
.mention-item:hover, .mention-item.active { background-color: #f3f4f6; }
.mention-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 9999px;
    background-color: #e5e7eb;
    font-size: 0.625rem;
    font-weight: 700;
    color: #6b7280;
}
.mention-name { font-size: 0.875rem; color: #374151; }
.mention-empty { padding: 0.5rem 0.75rem; font-size: 0.75rem; color: #9ca3af; }
</style>
