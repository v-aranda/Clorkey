<script setup>
import { ref, onBeforeUnmount, onMounted } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import axios from 'axios';
import { Plus, Send, ShieldCheck, ImageIcon, Video, FileText, X } from 'lucide-vue-next';

const props = defineProps({
    taskId: { type: [String, Number], required: true },
    users:  { type: Array, default: () => [] },
});

const emit = defineEmits(['sent']);

// ─── State ───────────────────────────────────────────────────────────────────

const showPlusMenu      = ref(false);
const showValidation    = ref(false);
const validationTarget  = ref('');
const attachments       = ref([]); // [{ file, previewUrl, type:'image'|'video'|'doc', name }]
const sending           = ref(false);

const imageInputRef = ref(null);
const videoInputRef = ref(null);
const docInputRef   = ref(null);

// ─── TipTap editor ───────────────────────────────────────────────────────────

const editor = useEditor({
    extensions: [
        StarterKit,
        Placeholder.configure({ placeholder: 'Escreva uma mensagem...' }),
    ],
    editorProps: {
        attributes: {
            class: 'focus:outline-none text-sm text-gray-800 leading-relaxed max-h-32 overflow-y-auto',
        },
        handleKeyDown: (_view, event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                handleSend();
                return true;
            }
            return false;
        },
    },
    content: '',
});

onBeforeUnmount(() => editor.value?.destroy());

// ─── + Menu ──────────────────────────────────────────────────────────────────

const plusMenuItems = [
    { key: 'validation', label: 'Validação',  icon: ShieldCheck,  color: 'text-amber-500', bg: 'bg-amber-50 hover:bg-amber-100' },
    { key: 'image',      label: 'Imagem',     icon: ImageIcon,    color: 'text-sky-500',   bg: 'bg-sky-50 hover:bg-sky-100'     },
    { key: 'video',      label: 'Vídeo',      icon: Video,        color: 'text-violet-500',bg: 'bg-violet-50 hover:bg-violet-100'},
    { key: 'document',   label: 'Documento',  icon: FileText,     color: 'text-emerald-500',bg: 'bg-emerald-50 hover:bg-emerald-100'},
];

function selectMenuItem(key) {
    showPlusMenu.value = false;
    if (key === 'validation') {
        showValidation.value = true;
        validationTarget.value = '';
    } else if (key === 'image')    imageInputRef.value?.click();
    else if (key === 'video')      videoInputRef.value?.click();
    else if (key === 'document')   docInputRef.value?.click();
}

// ─── File handling ────────────────────────────────────────────────────────────

async function buildAttachment(file, type) {
    if (type === 'image') {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve({ file, previewUrl: e.target.result, type: 'image', name: file.name });
            reader.readAsDataURL(file);
        });
    }
    if (type === 'video') {
        return { file, previewUrl: URL.createObjectURL(file), type: 'video', name: file.name };
    }
    return { file, previewUrl: null, type: 'doc', name: file.name };
}

async function onFileSelect(e, type) {
    const files = Array.from(e.target.files || []);
    const built = await Promise.all(files.map(f => buildAttachment(f, type)));
    attachments.value.push(...built);
    e.target.value = '';
}

function removeAttachment(idx) { attachments.value.splice(idx, 1); }

// ─── Send ─────────────────────────────────────────────────────────────────────

function isEmpty() {
    const text = editor.value?.getText()?.trim() ?? '';
    return !text && !attachments.value.length;
}

async function handleSend() {
    if (isEmpty() && !showValidation.value) return;
    if (showValidation.value) {
        await sendValidation();
    } else {
        await sendMessage();
    }
}

async function sendMessage() {
    if (isEmpty()) return;
    sending.value = true;
    try {
        const form = new FormData();
        form.append('content', editor.value?.getHTML() || '');
        attachments.value.forEach((a, i) => form.append(`files[${i}]`, a.file));
        const resp = await axios.post(`/agenda/tasks/${props.taskId}/messages`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        emit('sent', resp.data.message || null);
        editor.value?.commands.clearContent();
        attachments.value = [];
    } catch (e) { console.error(e); }
    finally { sending.value = false; }
}

async function sendValidation() {
    if (!validationTarget.value) return;
    sending.value = true;
    try {
        const resp = await axios.post(`/agenda/tasks/${props.taskId}/validations`, {
            target_user_id: validationTarget.value,
            note: editor.value?.getText() || '',
        });
        emit('sent', resp.data.validation || null);
        editor.value?.commands.clearContent();
        attachments.value = [];
        showValidation.value = false;
        validationTarget.value = '';
    } catch (e) { console.error(e); }
    finally { sending.value = false; }
}

// ─── Outside-click closes + menu ─────────────────────────────────────────────

function onDocClick(e) {
    if (!e.target.closest('[data-plus-menu]')) showPlusMenu.value = false;
}
onMounted(()        => document.addEventListener('click', onDocClick));
onBeforeUnmount(()  => document.removeEventListener('click', onDocClick));
</script>

<template>
    <div class="flex flex-col gap-1.5">

        <!-- Validation bar (above the input row) -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div v-if="showValidation" class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2">
                <ShieldCheck class="h-3.5 w-3.5 shrink-0 text-amber-500" />
                <span class="text-xs font-medium text-amber-700 shrink-0">Validador:</span>
                <select
                    v-model="validationTarget"
                    class="flex-1 min-w-0 rounded-lg border border-amber-200 bg-white px-2 py-1 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-amber-300"
                >
                    <option value="">Selecionar...</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
                <button @click="showValidation = false; validationTarget = ''" class="shrink-0 text-amber-400 hover:text-amber-600 transition-colors">
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>
        </Transition>

        <!-- Attachment previews -->
        <div v-if="attachments.length" class="flex flex-wrap gap-2 px-1">
            <div v-for="(a, idx) in attachments" :key="idx" class="relative group">
                <template v-if="a.type === 'image'">
                    <img :src="a.previewUrl" class="h-14 w-14 rounded-lg object-cover border border-gray-200" :alt="a.name" />
                </template>
                <template v-else-if="a.type === 'video'">
                    <video :src="a.previewUrl" class="h-14 w-14 rounded-lg object-cover border border-gray-200" muted />
                </template>
                <template v-else>
                    <div class="flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2 py-1.5 max-w-[140px]">
                        <FileText class="h-4 w-4 shrink-0 text-gray-400" />
                        <span class="truncate text-xs text-gray-600">{{ a.name }}</span>
                    </div>
                </template>
                <button
                    @click="removeAttachment(idx)"
                    class="absolute -top-1.5 -right-1.5 hidden group-hover:flex h-4 w-4 items-center justify-center rounded-full bg-gray-700 text-white shadow"
                >
                    <X class="h-2.5 w-2.5" />
                </button>
            </div>
        </div>

        <!-- Main input row: [+]  [editor]  [→] -->
        <div class="flex items-end gap-2">

            <!-- Hidden file inputs -->
            <input ref="imageInputRef" type="file" multiple accept="image/*"                                    class="hidden" @change="onFileSelect($event, 'image')" />
            <input ref="videoInputRef" type="file" multiple accept="video/*"                                    class="hidden" @change="onFileSelect($event, 'video')" />
            <input ref="docInputRef"   type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" class="hidden" @change="onFileSelect($event, 'doc')" />

            <!-- + button with pop-up menu -->
            <div class="relative shrink-0 self-end pb-0.5" data-plus-menu>
                <button
                    type="button"
                    @click.stop="showPlusMenu = !showPlusMenu"
                    :class="[
                        'flex h-8 w-8 items-center justify-center rounded-full transition-colors',
                        showPlusMenu ? 'bg-gray-200 text-gray-700' : 'bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700'
                    ]"
                    title="Adicionar"
                >
                    <Plus class="h-4 w-4 transition-transform duration-150" :class="showPlusMenu ? 'rotate-45' : ''" />
                </button>

                <!-- Pop-up menu (opens upward) -->
                <Transition
                    enter-active-class="transition duration-150 ease-out origin-bottom-left"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition duration-100 ease-in origin-bottom-left"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="showPlusMenu"
                        class="absolute bottom-full left-0 mb-2 flex gap-2 rounded-2xl border border-gray-200 bg-white p-2 shadow-lg z-50"
                    >
                        <button
                            v-for="item in plusMenuItems"
                            :key="item.key"
                            type="button"
                            @click="selectMenuItem(item.key)"
                            :class="['flex flex-col items-center gap-1 rounded-xl px-3 py-2 transition-colors', item.bg]"
                            :title="item.label"
                        >
                            <component :is="item.icon" :class="['h-5 w-5', item.color]" />
                            <span class="text-[10px] font-medium text-gray-600">{{ item.label }}</span>
                        </button>
                    </div>
                </Transition>
            </div>

            <!-- TipTap editor (flex-1) -->
            <div
                class="flex-1 min-w-0 rounded-2xl border border-gray-200 bg-white px-4 py-2.5 transition-colors focus-within:border-indigo-300 focus-within:ring-1 focus-within:ring-indigo-200"
            >
                <EditorContent :editor="editor" />
            </div>

            <!-- Send button (icon only) -->
            <button
                type="button"
                @click="handleSend"
                :disabled="sending"
                class="shrink-0 self-end flex h-9 w-9 items-center justify-center rounded-full bg-indigo-600 text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                title="Enviar (Enter)"
            >
                <Send class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>

<style>
/* TipTap placeholder */
.tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: #9ca3af;
    pointer-events: none;
    height: 0;
}
/* Remove default TipTap outline */
.tiptap { outline: none; }
/* Paragraph spacing in chat context */
.tiptap p { margin: 0; }
.tiptap p + p { margin-top: 0.25rem; }
</style>
