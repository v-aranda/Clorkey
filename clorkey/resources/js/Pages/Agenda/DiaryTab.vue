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

const loading = ref(false);
const saveStatus = ref('saved'); // 'saved' | 'saving' | 'error'
const isHydrating = ref(false);
let saveTimeout = null;
let latestLoadId = 0;

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
        const resp = await axios.get(route('agenda.diary.show'), { params: { date } });
        if (loadId !== latestLoadId) return;

        isHydrating.value = true;
        editor.value.commands.setContent(resp.data?.entry?.content || '', false);
        isHydrating.value = false;
        saveStatus.value = 'saved';
    } catch (error) {
        if (loadId !== latestLoadId) return;
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
    if (!date || !editor.value) return;
    const content = editor.value.getHTML();
    saveStatus.value = 'saving';

    try {
        await axios.put(route('agenda.diary.upsert'), { date, content });
        if (props.selectedDate === date) {
            saveStatus.value = 'saved';
        }
    } catch (error) {
        if (props.selectedDate === date) {
            saveStatus.value = 'error';
        }
    }
}

function queueSave() {
    if (!props.selectedDate) return;

    saveStatus.value = 'saving';

    if (saveTimeout) clearTimeout(saveTimeout);
    saveTimeout = setTimeout(async () => {
        const date = props.selectedDate;
        saveTimeout = null;
        await saveDiary(date);
    }, 700);
}

async function flushPendingSave(date) {
    if (!date) return;
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
            <span v-else-if="saveStatus === 'saving'" class="text-xs text-amber-500">Salvando...</span>
            <span v-else-if="saveStatus === 'error'" class="text-xs text-red-500">Erro ao salvar</span>
            <span v-else class="text-xs text-emerald-600">Salvo</span>
        </div>

        <div class="rounded-md border border-input shadow-sm overflow-hidden">
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
