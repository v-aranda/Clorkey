<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import { Bold, Italic, Strikethrough, List, ListOrdered, Undo2, Redo2 } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    selectedDate: { type: String, default: '' },
});

const emit = defineEmits(['diary-saved']);

const loading = ref(false);
const saveStatus = ref('saved'); // 'saved' | 'saving' | 'error'
const isHydrating = ref(false);
const isDeletedEntry = ref(false);
const hasEntry = ref(false);
const entryId = ref(null);
const deletedByName = ref('');
let saveTimeout = null;
let latestLoadId = 0;

function isDateInPast(date) {
    if (!date) return false;
    const today = new Date();
    const localToday = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const selected = new Date(`${date}T00:00:00`);
    if (Number.isNaN(selected.getTime())) return false;
    return selected < localToday;
}

const isPastDate = computed(() => {
    return isDateInPast(props.selectedDate);
});

const editor = useEditor({
    extensions: [
        StarterKit,
        Placeholder.configure({
            placeholder: 'Escreva suas anotações do dia...',
        }),
    ],
    content: '',
    editorProps: {
        attributes: {
            class: 'tiptap min-h-[220px] px-3 py-2 text-sm focus:outline-none',
        },
    },
    onUpdate: () => {
        if (isHydrating.value) return;
        queueSave();
    },
});

const selectedDateLabel = computed(() => {
    if (!props.selectedDate) return '';
    const [year, month, day] = props.selectedDate.split('-').map(Number);
    if (!year || !month || !day) return props.selectedDate;
    return new Intl.DateTimeFormat('pt-BR', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
});

async function loadDiary(date) {
    if (!date || !editor.value) return;

    const loadId = ++latestLoadId;
    loading.value = true;

    try {
        const resp = await axios.get(route('agenda.diary.show'), {
            params: { date, include_deleted_content: true },
        });
        if (loadId !== latestLoadId) return;

        isDeletedEntry.value = Boolean(resp.data?.entry?.deleted);
        entryId.value = resp.data?.entry?.id ?? null;
        hasEntry.value = entryId.value != null;
        deletedByName.value = resp.data?.entry?.deleted_by_name || '';
        const content = resp.data?.entry?.content || '';

        isHydrating.value = true;
        editor.value.commands.setContent(content, false);
        isHydrating.value = false;
        saveStatus.value = 'saved';
    } catch (error) {
        if (loadId !== latestLoadId) return;
        isDeletedEntry.value = false;
        hasEntry.value = false;
        entryId.value = null;
        deletedByName.value = '';
        isHydrating.value = true;
        editor.value.commands.setContent('', false);
        isHydrating.value = false;
        saveStatus.value = 'error';
    } finally {
        if (loadId === latestLoadId) {
            loading.value = false;
        }
    }
}

async function saveDiary(date) {
    if (!date || !editor.value || isDateInPast(date)) return;
    const content = editor.value.getHTML();
    saveStatus.value = 'saving';

    try {
        await axios.put(route('agenda.diary.upsert'), { date, content });
        if (props.selectedDate === date) {
            saveStatus.value = 'saved';
            emit('diary-saved', { date, content });
        }
    } catch (error) {
        if (props.selectedDate === date) {
            saveStatus.value = 'error';
        }
    }
}

function queueSave() {
    if (!props.selectedDate || isPastDate.value) return;

    saveStatus.value = 'saving';

    if (saveTimeout) clearTimeout(saveTimeout);
    saveTimeout = setTimeout(async () => {
        const date = props.selectedDate;
        saveTimeout = null;
        await saveDiary(date);
    }, 700);
}

async function flushPendingSave(date) {
    if (!date || isDateInPast(date)) return;
    if (!saveTimeout) return;

    clearTimeout(saveTimeout);
    saveTimeout = null;
    await saveDiary(date);
}

watch(
    [() => props.selectedDate, () => editor.value],
    async ([date, editorInstance], [previousDate]) => {
        if (!date || !editorInstance) return;

        if (previousDate && previousDate !== date) {
            await flushPendingSave(previousDate);
        }

        await loadDiary(date);
    },
    { immediate: true }
);

watch(
    [() => isPastDate.value, () => isDeletedEntry.value, () => editor.value],
    ([past, _deleted, editorInstance]) => {
        if (!editorInstance) return;
        editorInstance.setEditable(!past);
        if (past) {
            if (saveTimeout) {
                clearTimeout(saveTimeout);
                saveTimeout = null;
            }
            saveStatus.value = 'saved';
        }
    },
    { immediate: true }
);

async function restoreEntry() {
    if (!entryId.value) return;
    try {
        const backendOrigin = new URL(route('diary.index')).origin;
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        await axios.post(`${backendOrigin}/diary/entries/${entryId.value}/restore`, {}, {
            headers: token ? { 'X-CSRF-TOKEN': token } : {},
        });
        isDeletedEntry.value = false;
        deletedByName.value = '';
        await loadDiary(props.selectedDate);
    } catch (error) {
        // keep state if restore fails
    }
}

onBeforeUnmount(async () => {
    await flushPendingSave(props.selectedDate);
    editor.value?.destroy();
});
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400">Diário</p>
                <p class="text-sm font-medium capitalize text-gray-700">{{ selectedDateLabel }}</p>
            </div>
            <span v-if="loading" class="text-xs text-gray-400">Carregando...</span>
            <template v-else-if="isPastDate">
                <span v-if="isDeletedEntry" class="text-xs text-gray-500">
                    Registro excluído{{ deletedByName ? ` por ${deletedByName}` : '' }}
                </span>
                <span v-else-if="hasEntry" class="text-xs text-gray-500">Somente leitura</span>
            </template>
            <template v-else>
                <span v-if="saveStatus === 'saving'" class="text-xs text-amber-500">Salvando...</span>
                <span v-else-if="saveStatus === 'error'" class="text-xs text-red-500">Erro ao salvar</span>
                <span v-else-if="hasEntry" class="text-xs text-emerald-600">Salvo</span>
            </template>
        </div>

        <!-- Past + deleted: amber banner with restore -->
        <div v-if="!loading && isDeletedEntry && isPastDate" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            <div class="flex items-center justify-between gap-3">
                <span>Este registro foi excluído{{ deletedByName ? ` por ${deletedByName}` : '' }}.</span>
                <button
                    type="button"
                    class="rounded-md border border-amber-300 bg-white px-3 py-1 text-xs font-semibold text-amber-800 shadow-sm hover:bg-amber-50 transition-colors"
                    @click="restoreEntry"
                >
                    Restaurar
                </button>
            </div>
        </div>

        <!-- Past + no entry: info message -->
        <div v-else-if="!loading && isPastDate && !hasEntry" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-4 text-center text-sm text-gray-400">
            Nenhum registro para esta data.
        </div>

        <!-- Past + active entry: read-only content (no toolbar) -->
        <div v-else-if="!loading && isPastDate && hasEntry && !isDeletedEntry" class="rounded-md overflow-hidden">
            <EditorContent :editor="editor" />
        </div>

        <!-- Today/future: editable editor with toolbar -->
        <div v-else-if="!loading && !isPastDate" class="rounded-md overflow-hidden border border-input shadow-sm">
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
                    @click="editor?.chain().focus().undo().run()"
                    :disabled="!editor?.can().chain().focus().undo().run()"
                    class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 disabled:opacity-40"
                    title="Desfazer"
                ><Undo2 class="h-3.5 w-3.5" /></button>
                <button
                    type="button"
                    @click="editor?.chain().focus().redo().run()"
                    :disabled="!editor?.can().chain().focus().redo().run()"
                    class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 disabled:opacity-40"
                    title="Refazer"
                ><Redo2 class="h-3.5 w-3.5" /></button>
            </div>

            <EditorContent :editor="editor" />
        </div>
    </div>
</template>

<style scoped>
.tiptap :deep(p.is-editor-empty:first-child)::before {
    content: attr(data-placeholder);
    float: left;
    color: #9ca3af;
    pointer-events: none;
    height: 0;
}

.tiptap :deep(ul),
.tiptap :deep(ol) {
    padding-left: 1rem;
}

.tiptap :deep(p),
.tiptap :deep(li) {
    margin: 0.15rem 0;
}
</style>
