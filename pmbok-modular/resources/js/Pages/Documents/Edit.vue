<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import UniqueID from '@tiptap/extension-unique-id';
import { TextStyle } from '@tiptap/extension-text-style';
import { Color } from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import TextAlign from '@tiptap/extension-text-align';
import { Table } from '@tiptap/extension-table';
import { TableRow } from '@tiptap/extension-table-row';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import Placeholder from '@tiptap/extension-placeholder';
import TiptapLink from '@tiptap/extension-link';
import TaskList from '@tiptap/extension-task-list';
import TaskItem from '@tiptap/extension-task-item';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import { common, createLowlight } from 'lowlight';
import Image from '@tiptap/extension-image';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EditorToolbar from '@/Components/EditorToolbar.vue';
import DocumentRightPanel from '@/Components/DocumentRightPanel.vue';
import {
    ChevronLeft, CheckCircle2, RefreshCw, AlertCircle, CircleDashed,
    Menu, X, GitCompare,
    Columns, Rows, Plus, Minus, Trash2,
    Link2Off, ExternalLink, Download
} from 'lucide-vue-next';
import axios from 'axios';
import DiffMatchPatch from 'diff-match-patch';

const CustomTableCell = TableCell.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            backgroundColor: {
                default: null,
                parseHTML: element => element.getAttribute('data-background-color'),
                renderHTML: attributes => {
                    if (!attributes.backgroundColor) {
                        return {}
                    }
                    return {
                        'data-background-color': attributes.backgroundColor,
                        style: `background-color: ${attributes.backgroundColor}`,
                    }
                },
            },
        }
    },
});

const CustomTableHeader = TableHeader.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            backgroundColor: {
                default: null,
                parseHTML: element => element.getAttribute('data-background-color'),
                renderHTML: attributes => {
                    if (!attributes.backgroundColor) {
                        return {}
                    }
                    return {
                        'data-background-color': attributes.backgroundColor,
                        style: `background-color: ${attributes.backgroundColor}`,
                    }
                },
            },
        }
    },
});

const CustomTable = Table.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            borderColor: {
                default: null,
                parseHTML: element => element.getAttribute('data-border-color'),
                renderHTML: attributes => {
                    if (!attributes.borderColor) return {};
                    return {
                        'data-border-color': attributes.borderColor,
                        style: `--table-border-color: ${attributes.borderColor};`,
                    };
                },
            },
            borderWidth: {
                default: 1,
                parseHTML: element => {
                    const val = element.getAttribute('data-border-width');
                    return val !== null ? Math.max(0, Number(val)) : 1;
                },
                renderHTML: attributes => {
                    const bw = Math.max(0, Number(attributes.borderWidth ?? 1));
                    return {
                        'data-border-width': bw,
                        style: `--table-border-width: ${bw}px;`,
                    };
                },
            },
        }
    },
});

const props = defineProps({
    document: Object,
});

const form = useForm({
    title: props.document.title || 'Sem Título',
    content: props.document.content || '',
    css: props.document.css || '',
});

let saveTimeout = null;

const saveStatus = ref('saved');
const isSaving = ref(false);

const saveDocument = async () => {
    if (isSaving.value || saveStatus.value === 'saved') return;

    saveStatus.value = 'saving';
    isSaving.value = true;

    try {
        await axios.put(route('library.documents.update', props.document.id), {
            title: form.title,
            content: form.content,
            css: form.css
        });
        saveStatus.value = 'saved';
    } catch (error) {
        saveStatus.value = 'error';
    } finally {
        isSaving.value = false;
    }
};

const debouncedSave = () => {
    if (saveStatus.value !== 'saving') {
        saveStatus.value = 'unsaved';
    }
    if (saveTimeout) clearTimeout(saveTimeout);
    saveTimeout = setTimeout(() => {
        saveDocument();
    }, 1500);
};

const editor = useEditor({
    content: form.content,
    extensions: [
        StarterKit.configure({
            codeBlock: false,
        }),
        CodeBlockLowlight.configure({
            lowlight: createLowlight(common),
        }),
        TextStyle,
        Color,
        Highlight.configure({ multicolor: true }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        CustomTable.configure({
            resizable: true,
        }),
        TableRow,
        CustomTableHeader,
        CustomTableCell,
        UniqueID.configure({
            types: ['heading', 'paragraph'],
        }),
        Placeholder.configure({
            placeholder: 'Comece a digitar...',
        }),
        TiptapLink.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-blue-600 underline cursor-pointer hover:text-blue-800',
            },
        }),
        TaskList,
        TaskItem.configure({
            nested: true,
        }),
        Image.configure({
            inline: false,
            allowBase64: true,
            HTMLAttributes: {
                class: 'editor-image',
            },
        }),
    ],
    editorProps: {
        handleDrop(view, event, slice, moved) {
            if (!moved && event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length) {
                const files = Array.from(event.dataTransfer.files).filter(f => f.type.startsWith('image/'));
                if (files.length === 0) return false;
                event.preventDefault();
                files.forEach(file => {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const pos = view.posAtCoords({ left: event.clientX, top: event.clientY });
                        const node = view.state.schema.nodes.image.create({ src: e.target.result });
                        const tr = view.state.tr.insert(pos?.pos ?? view.state.selection.from, node);
                        view.dispatch(tr);
                    };
                    reader.readAsDataURL(file);
                });
                return true;
            }
            return false;
        },
        handlePaste(view, event) {
            const items = Array.from(event.clipboardData?.items || []);
            const images = items.filter(item => item.type.startsWith('image/'));
            if (images.length === 0) return false;
            event.preventDefault();
            images.forEach(item => {
                const file = item.getAsFile();
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (e) => {
                    const node = view.state.schema.nodes.image.create({ src: e.target.result });
                    const tr = view.state.tr.replaceSelectionWith(node);
                    view.dispatch(tr);
                };
                reader.readAsDataURL(file);
            });
            return true;
        },
    },
    onUpdate: () => {
        form.content = editor.value.getHTML();
        debouncedSave();
        generateToc();
    },
    onCreate: () => {
        generateToc();
    },
});

onBeforeUnmount(() => {
    // Moved to the second onBeforeUnmount with the table event listeners
});

// Document Right Panel logic
const tocItems = ref([]);
const showRightPanel = ref(true);
const versionHistory = ref([]);

async function loadVersions() {
    try {
        const { data } = await axios.get(route('library.documents.versions', props.document.id));
        versionHistory.value = data;
    } catch {
        // silently ignore
    }
}

async function saveVersion(label) {
    try {
        const { data } = await axios.post(route('library.documents.versions.store', props.document.id), { label });
        versionHistory.value.unshift(data);
    } catch {
        // silently ignore
    }
}

async function loadVersionContent(version) {
    const { data } = await axios.get(route('library.documents.versions.show', [props.document.id, version.id]));
    return data.content;
}

async function deleteVersion(version) {
    await axios.delete(route('library.documents.versions.destroy', [props.document.id, version.id]));
    versionHistory.value = versionHistory.value.filter(v => v.id !== version.id);
    if (versionModal.value.version?.id === version.id) {
        closeVersionModal();
    }
}

function getCurrentContent() {
    return editor.value ? editor.value.getHTML() : form.content;
}

// --- Version Modal ---

function stripHtml(html) {
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.innerText || div.textContent || '';
}

function buildDiff(oldHtml, newHtml) {
    const dmp = new DiffMatchPatch();

    function tokenize(text) {
        return text.match(/(<[^>]+>)|(\s+)|([^<\s]+)/g) || [];
    }

    const wordToChar = new Map();
    let nextChar = 0x100;

    function mapTokens(tokens) {
        return tokens.map(token => {
            if (!wordToChar.has(token)) {
                wordToChar.set(token, String.fromCodePoint(nextChar++));
            }
            return wordToChar.get(token);
        }).join('');
    }

    const chars1 = mapTokens(tokenize(oldHtml || ''));
    const chars2 = mapTokens(tokenize(newHtml || ''));
    const charToWord = new Map([...wordToChar.entries()].map(([k, v]) => [v, k]));

    const diffs = dmp.diff_main(chars1, chars2, false);
    dmp.diff_cleanupSemantic(diffs);

    return diffs.map(([op, chars]) => {
        const word = [...chars].map(c => charToWord.get(c) ?? c).join('');
        const tokens = word.match(/(<[^>]+>)|([^<]+)/g) || [];

        return tokens.map(token => {
            if (token.startsWith('<') && token.endsWith('>')) {
                return token; // Preserve HTML tags without wrapping
            }

            if (op === -1) return `<span class="version-diff-del">${token}</span>`;
            if (op === 1) return `<span class="version-diff-ins">${token}</span>`;
            return token;
        }).join('');
    }).join('');
}

function formatVersionDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', year: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

const versionModal = ref({
    open: false,
    version: null,
    content: '',
    diffContent: '',
    showDiff: true,
    loading: false,
});

async function openVersionModal(version) {
    versionModal.value.open = true;
    versionModal.value.version = version;
    versionModal.value.content = '';
    versionModal.value.diffContent = '';
    versionModal.value.loading = true;

    const selectedHtml = await loadVersionContent(version);
    versionModal.value.content = selectedHtml;

    const idx = versionHistory.value.findIndex(v => v.id === version.id);
    const newerVersion = idx === 0 ? null : versionHistory.value[idx - 1];
    const newerHtml = newerVersion
        ? await loadVersionContent(newerVersion)
        : editor.value.getHTML();

    versionModal.value.diffContent = buildDiff(selectedHtml, newerHtml);
    versionModal.value.loading = false;
}

function closeVersionModal() {
    versionModal.value.open = false;
    versionModal.value.version = null;
}

const generateToc = () => {
    if (!editor.value) return;

    const items = [];
    const transaction = editor.value.state.tr;
    editor.value.state.doc.descendants((node, pos) => {
        if (node.type.name === 'heading') {
            const id = node.attrs.id;
            const text = node.textContent;
            const level = node.attrs.level;

            if (id && text.trim()) {
                items.push({ id, text, level, pos });
            }
        }
    });

    tocItems.value = items;
};

const scrollToHeading = (id) => {
    const element = document.querySelector(`[data-id="${id}"]`);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    // Also scroll window slightly down to account for sticky header/dock
    setTimeout(() => window.scrollBy(0, -100), 50);
};

const toggleRightPanel = () => {
    showRightPanel.value = !showRightPanel.value;
};

const handleTitleChange = () => {
    debouncedSave();
};

const goBack = () => {
    if (props.document.library_folder_id) {
        router.get(route('library.folder.show', { book: props.document.library_book_id, folderId: props.document.library_folder_id }));
    } else {
        router.get(route('library.show', { book: props.document.library_book_id }));
    }
};

const exportToPdf = (versionId = null) => {
    let url = route('library.documents.download', props.document.id);
    if (versionId) {
        url += `?version_id=${versionId}`;
    }
    window.open(url, '_blank');
};

// Table Context Menu Logic
const tableContextMenu = ref({ show: false, x: 0, y: 0 });

// Link modal
const showLinkModal = ref(false);
const linkUrl = ref('');

const setLink = () => {
    if (!linkUrl.value) {
        editor.value.chain().focus().unsetLink().run();
        showLinkModal.value = false;
        return;
    }
    let url = linkUrl.value;
    if (!/^https?:\/\//.test(url)) url = 'https://' + url;
    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    showLinkModal.value = false;
    linkUrl.value = '';
};

const openLinkModal = () => {
    const prev = editor.value.getAttributes('link').href;
    linkUrl.value = prev || '';
    showLinkModal.value = true;
};

const handleContextMenu = (e) => {
    if (editor.value && editor.value.isActive('table')) {
        e.preventDefault();
        tableContextMenu.value = {
            show: true,
            x: e.clientX,
            y: e.clientY
        };
    }
};

const closeContextMenu = () => {
    tableContextMenu.value.show = false;
};

onMounted(() => {
    window.addEventListener('click', closeContextMenu);
    loadVersions();
});
onBeforeUnmount(() => {
    window.removeEventListener('click', closeContextMenu);
    editor.value?.destroy();
    if (saveTimeout) clearTimeout(saveTimeout);
});
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full h-10">
                <div class="flex items-center gap-4 flex-1">
                    <button @click="goBack"
                        class="p-2 hover:bg-gray-200 rounded-full transition-colors text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-ring">
                        <ChevronLeft class="w-5 h-5" />
                    </button>
                    <input v-model="form.title" @input="handleTitleChange"
                        class="text-xl font-semibold bg-transparent border-transparent hover:border-gray-300 focus:border-primary focus:ring-0 p-1 rounded-md text-gray-800 w-full max-w-lg transition-colors"
                        placeholder="Título do Documento" />
                </div>
                <div class="flex items-center gap-4">
                    <button @click="exportToPdf()"
                        class="flex items-center gap-1.5 text-sm font-medium px-3 py-1.5 rounded-md text-gray-700 hover:bg-gray-100 transition-colors"
                        title="Exportar PDF">
                        <Download class="w-4 h-4" />
                        <span class="hidden sm:inline">Exportar PDF</span>
                    </button>
                    <button @click="saveDocument"
                        class="flex items-center gap-2 text-sm font-medium px-3 py-1.5 rounded-md transition-colors"
                        :class="{
                            'text-green-600 hover:bg-green-50': saveStatus === 'saved',
                            'text-amber-600 hover:bg-amber-50': saveStatus === 'unsaved',
                            'text-blue-600': saveStatus === 'saving',
                            'text-red-600 hover:bg-red-50': saveStatus === 'error'
                        }">
                        <CheckCircle2 v-if="saveStatus === 'saved'" class="w-4 h-4" />
                        <CircleDashed v-else-if="saveStatus === 'unsaved'" class="w-4 h-4" />
                        <RefreshCw v-else-if="saveStatus === 'saving'" class="w-4 h-4 animate-spin" />
                        <AlertCircle v-else-if="saveStatus === 'error'" class="w-4 h-4" />

                        <span v-if="saveStatus === 'saved'">Salvo</span>
                        <span v-else-if="saveStatus === 'unsaved'">Alterações pendentes</span>
                        <span v-else-if="saveStatus === 'saving'">Salvando...</span>
                        <span v-else-if="saveStatus === 'error'">Erro ao salvar</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="flex flex-col md:flex-row w-full max-w-[1600px] mx-auto justify-between items-start relative gap-0">

            <!-- Left Toolbar Dock -->
            <EditorToolbar v-if="editor" :editor="editor" @open-link-modal="openLinkModal" />
            <!-- Editor Content -->
            <div class="flex-1 min-w-0 overflow-y-auto bg-gray-100 relative" style="min-height: calc(100vh - 65px);">
                <div class="py-10 flex justify-center">
                    <div class="a4-page" @contextmenu="handleContextMenu">
                        <editor-content :editor="editor" />
                    </div>
                </div>

                <!-- Version Modal Overlay -->
                <div v-if="versionModal.open"
                    class="absolute inset-2 z-20 bg-gray-100 rounded-2xl overflow-hidden shadow-xl ring-1 ring-black/5 flex flex-col">
                    <!-- Control bar -->
                    <div
                        class="sticky top-0 z-10 bg-violet-700 flex items-center gap-3 px-4 py-2 shadow-md flex-shrink-0">
                        <button @click="closeVersionModal"
                            class="flex items-center gap-1.5 text-sm text-white/80 hover:text-white transition-colors">
                            <X class="w-4 h-4" /> Fechar
                        </button>
                        <div class="h-4 w-px bg-white/25 flex-shrink-0" />
                        <div class="flex flex-col min-w-0">
                            <span class="text-sm font-semibold text-white truncate">
                                {{ versionModal.version?.label || 'Sem nome' }}
                            </span>
                            <span class="text-xs text-violet-200">
                                {{ versionModal.version?.user?.name }}
                                <template v-if="versionModal.version?.created_at">
                                    · {{ formatVersionDate(versionModal.version.created_at) }}
                                </template>
                            </span>
                        </div>
                        <button @click="versionModal.showDiff = !versionModal.showDiff" :class="versionModal.showDiff
                            ? 'bg-white/20 text-white hover:bg-white/30 ring-1 ring-white/30'
                            : 'bg-white/10 text-white/70 hover:bg-white/20'"
                            class="ml-auto flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors flex-shrink-0">
                            <GitCompare class="w-4 h-4" />
                            {{ versionModal.showDiff ? 'Versão limpa' : 'Ver mudanças' }}
                        </button>
                    </div>

                    <!-- A4 Content -->
                    <div class="flex-1 overflow-y-auto py-10 flex justify-center">
                        <div v-if="versionModal.loading" class="text-gray-400 text-sm mt-20">Carregando...</div>
                        <div v-else class="a4-page">
                            <div v-if="!versionModal.showDiff" class="tiptap version-readonly"
                                v-html="versionModal.content" />
                            <div v-else class="tiptap version-diff" v-html="versionModal.diffContent" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel Sidebar -->
            <DocumentRightPanel v-model:show="showRightPanel" :toc-items="tocItems" :version-history="versionHistory"
                :active-version-id="versionModal.version?.id" @scroll-to-heading="scrollToHeading"
                @open-version-modal="openVersionModal" @save-version="saveVersion" @delete-version="deleteVersion"
                @export-version="v => exportToPdf(v)" />


        </div>

        <!-- Floating Toggle Panel Button (visible when Panel is hidden) -->
        <button v-if="!showRightPanel" @click="toggleRightPanel"
            class="fixed right-6 top-24 z-20 p-2 bg-white rounded-full shadow-[0_4px_10px_-1px_rgba(0,0,0,0.1)] border hover:bg-gray-50 hover:text-primary transition text-gray-400"
            title="Mostrar Painel">
            <Menu class="w-5 h-5" />
        </button>

        <!-- Link Modal -->
        <Teleport to="body">
            <div v-if="showLinkModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/30"
                @click.self="showLinkModal = false">
                <div class="bg-white rounded-xl shadow-2xl w-96 p-5" @click.stop>
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Inserir Link</h3>
                    <div
                        class="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary">
                        <ExternalLink class="w-4 h-4 text-gray-400 flex-shrink-0" />
                        <input v-model="linkUrl" @keydown.enter="setLink()" placeholder="https://exemplo.com"
                            class="flex-1 text-sm border-none outline-none focus:ring-0 p-0 bg-transparent" autofocus />
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button v-if="editor.isActive('link')"
                            @click="editor.chain().focus().unsetLink().run(); showLinkModal = false"
                            class="px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors flex items-center gap-1.5">
                            <Link2Off class="w-3.5 h-3.5" /> Remover
                        </button>
                        <button @click="showLinkModal = false"
                            class="px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 rounded-lg transition-colors">Cancelar</button>
                        <button @click="setLink()"
                            class="px-4 py-1.5 text-sm text-white bg-primary hover:bg-primary/90 rounded-lg transition-colors">Salvar</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Table Context Menu -->
        <Teleport to="body">
            <div v-if="tableContextMenu.show"
                class="fixed z-[100] w-56 bg-white border border-gray-200 shadow-xl rounded-lg py-1 flex flex-col items-stretch text-sm"
                :style="{ top: `${tableContextMenu.y}px`, left: `${tableContextMenu.x}px` }" @click.stop>

                <!-- Columns -->
                <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Colunas</div>
                <button @click="editor.chain().focus().addColumnBefore().run(); closeContextMenu()"
                    class="flex items-center gap-3 px-3 py-2 text-gray-700 hover:bg-gray-50 transition w-full text-left">
                    <div class="relative">
                        <Columns class="w-4 h-4 text-emerald-600" />
                        <Plus
                            class="w-3 h-3 absolute -bottom-1 -right-1 text-emerald-700 bg-white rounded-full bg-opacity-80" />
                    </div>
                    Inserir Antes
                </button>
                <button @click="editor.chain().focus().addColumnAfter().run(); closeContextMenu()"
                    class="flex items-center gap-3 px-3 py-2 text-gray-700 hover:bg-gray-50 transition w-full text-left">
                    <div class="relative">
                        <Columns class="w-4 h-4 text-emerald-600" />
                        <Plus
                            class="w-3 h-3 absolute -bottom-1 -right-1 text-emerald-700 bg-white rounded-full bg-opacity-80" />
                    </div>
                    Inserir Depois
                </button>
                <button @click="editor.chain().focus().deleteColumn().run(); closeContextMenu()"
                    class="flex items-center gap-3 px-3 py-2 text-red-600 hover:bg-red-50 transition w-full text-left">
                    <div class="relative">
                        <Columns class="w-4 h-4 text-red-500" />
                        <Minus
                            class="w-3 h-3 absolute -bottom-1 -right-1 text-red-700 bg-white rounded-full bg-opacity-80" />
                    </div>
                    Excluir Coluna
                </button>

                <div class="h-px bg-gray-200 my-1"></div>

                <!-- Rows -->
                <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Linhas</div>
                <button @click="editor.chain().focus().addRowBefore().run(); closeContextMenu()"
                    class="flex items-center gap-3 px-3 py-2 text-gray-700 hover:bg-gray-50 transition w-full text-left">
                    <div class="relative">
                        <Rows class="w-4 h-4 text-blue-600" />
                        <Plus
                            class="w-3 h-3 absolute -bottom-1 -right-1 text-blue-700 bg-white rounded-full bg-opacity-80" />
                    </div>
                    Inserir Acima
                </button>
                <button @click="editor.chain().focus().addRowAfter().run(); closeContextMenu()"
                    class="flex items-center gap-3 px-3 py-2 text-gray-700 hover:bg-gray-50 transition w-full text-left">
                    <div class="relative">
                        <Rows class="w-4 h-4 text-blue-600" />
                        <Plus
                            class="w-3 h-3 absolute -bottom-1 -right-1 text-blue-700 bg-white rounded-full bg-opacity-80" />
                    </div>
                    Inserir Abaixo
                </button>
                <button @click="editor.chain().focus().deleteRow().run(); closeContextMenu()"
                    class="flex items-center gap-3 px-3 py-2 text-red-600 hover:bg-red-50 transition w-full text-left">
                    <div class="relative">
                        <Rows class="w-4 h-4 text-red-500" />
                        <Minus
                            class="w-3 h-3 absolute -bottom-1 -right-1 text-red-700 bg-white rounded-full bg-opacity-80" />
                    </div>
                    Excluir Linha
                </button>

                <div class="h-px bg-gray-200 my-1"></div>

                <button @click="editor.chain().focus().deleteTable().run(); closeContextMenu()"
                    class="flex items-center gap-3 px-3 py-2 text-red-600 font-medium hover:bg-red-50 transition w-full text-left">
                    <Trash2 class="w-4 h-4" /> Excluir Tabela Inteira
                </button>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style>
/* A4 Page Styling */
.a4-page {
    width: 210mm;
    min-height: 297mm;
    background: white;
    box-shadow: 0 4px 24px -2px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0, 0, 0, 0.04);
    border-radius: 4px;
    padding: 25mm 20mm;
    position: relative;
}

.a4-page .tiptap {
    outline: none;
    min-height: calc(297mm - 50mm);
    font-size: 11pt;
    line-height: 1.6;
    color: #1a1a1a;
}

.a4-page .tiptap>*:first-child {
    margin-top: 0;
}

/* Typography */
.a4-page .tiptap h1 {
    font-size: 2em;
    font-weight: 700;
    line-height: 1.2;
    margin: 1em 0 0.5em;
    color: #111827;
}

.a4-page .tiptap h2 {
    font-size: 1.5em;
    font-weight: 600;
    line-height: 1.3;
    margin: 1em 0 0.4em;
    color: #1f2937;
}

.a4-page .tiptap h3 {
    font-size: 1.25em;
    font-weight: 600;
    line-height: 1.4;
    margin: 0.8em 0 0.3em;
    color: #374151;
}

.a4-page .tiptap p {
    margin: 0.5em 0;
}

.a4-page .tiptap strong {
    font-weight: 700;
}

.a4-page .tiptap em {
    font-style: italic;
}

.a4-page .tiptap s {
    text-decoration: line-through;
}

.a4-page .tiptap mark {
    border-radius: 2px;
    padding: 0 2px;
    -webkit-box-decoration-break: clone;
    box-decoration-break: clone;
}

/* Lists */
.a4-page .tiptap ul {
    list-style-type: disc;
    padding-left: 1.5em;
    margin: 0.5em 0;
}

.a4-page .tiptap ol {
    list-style-type: decimal;
    padding-left: 1.5em;
    margin: 0.5em 0;
}

.a4-page .tiptap li {
    margin: 0.2em 0;
}

.a4-page .tiptap li p {
    margin: 0;
}

.a4-page .tiptap ul ul,
.a4-page .tiptap ol ul {
    list-style-type: circle;
}

.a4-page .tiptap ul ul ul,
.a4-page .tiptap ol ul ul,
.a4-page .tiptap ol ol ul {
    list-style-type: square;
}

/* Blockquote */
.a4-page .tiptap blockquote {
    border-left: 3px solid #d1d5db;
    padding: 0.4em 0 0.4em 1em;
    margin: 0.8em 0;
    color: #4b5563;
    font-style: italic;
}

.a4-page .tiptap blockquote p {
    margin: 0.2em 0;
}

/* Code */
.a4-page .tiptap code {
    background-color: #f3f4f6;
    border-radius: 4px;
    padding: 0.15em 0.4em;
    font-size: 0.9em;
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    color: #ef4444;
}

.a4-page .tiptap pre {
    background-color: #1e1e1e;
    color: #d4d4d4;
    border-radius: 8px;
    padding: 1em;
    margin: 0.8em 0;
    overflow-x: auto;
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 0.85em;
    line-height: 1.5;
}

.a4-page .tiptap pre code {
    background: none;
    color: inherit;
    padding: 0;
    border-radius: 0;
    font-size: inherit;
}

/* Lowlight syntax highlighting (VS Code Dark+ theme) */
.a4-page .tiptap pre .hljs-comment,
.a4-page .tiptap pre .hljs-quote {
    color: #6a9955;
    font-style: italic;
}

.a4-page .tiptap pre .hljs-keyword,
.a4-page .tiptap pre .hljs-selector-tag,
.a4-page .tiptap pre .hljs-built_in {
    color: #569cd6;
}

.a4-page .tiptap pre .hljs-string,
.a4-page .tiptap pre .hljs-attr {
    color: #ce9178;
}

.a4-page .tiptap pre .hljs-number,
.a4-page .tiptap pre .hljs-literal {
    color: #b5cea8;
}

.a4-page .tiptap pre .hljs-variable,
.a4-page .tiptap pre .hljs-template-variable {
    color: #9cdcfe;
}

.a4-page .tiptap pre .hljs-type,
.a4-page .tiptap pre .hljs-class .hljs-title {
    color: #4ec9b0;
}

.a4-page .tiptap pre .hljs-title,
.a4-page .tiptap pre .hljs-function .hljs-title {
    color: #dcdcaa;
}

.a4-page .tiptap pre .hljs-tag {
    color: #808080;
}

.a4-page .tiptap pre .hljs-name {
    color: #569cd6;
}

.a4-page .tiptap pre .hljs-attribute {
    color: #9cdcfe;
}

.a4-page .tiptap pre .hljs-regexp,
.a4-page .tiptap pre .hljs-link {
    color: #d16969;
}

.a4-page .tiptap pre .hljs-symbol,
.a4-page .tiptap pre .hljs-bullet {
    color: #d4d4d4;
}

.a4-page .tiptap pre .hljs-meta {
    color: #c586c0;
}

.a4-page .tiptap pre .hljs-deletion {
    color: #ce9178;
    background-color: rgba(206, 145, 120, 0.1);
}

.a4-page .tiptap pre .hljs-addition {
    color: #b5cea8;
    background-color: rgba(181, 206, 168, 0.1);
}

/* Horizontal Rule */
.a4-page .tiptap hr {
    border: none;
    border-top: 1px solid #e5e7eb;
    margin: 1.5em 0;
}

/* Images */
.a4-page .tiptap img.editor-image {
    max-width: 100%;
    height: auto;
    border-radius: 6px;
    margin: 1em 0;
    display: block;
}

.a4-page .tiptap img.editor-image.ProseMirror-selectednode {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
    border-radius: 6px;
}

/* Task List */
.a4-page .tiptap ul[data-type="taskList"] {
    list-style: none;
    padding-left: 0;
    margin: 0.5em 0;
}

.a4-page .tiptap ul[data-type="taskList"] li {
    display: flex;
    align-items: flex-start;
    gap: 0.5em;
    margin: 0.25em 0;
}

.a4-page .tiptap ul[data-type="taskList"] li>label {
    flex-shrink: 0;
    margin-top: 0.25em;
}

.a4-page .tiptap ul[data-type="taskList"] li>label input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #6366f1;
    cursor: pointer;
    border-radius: 3px;
}

.a4-page .tiptap ul[data-type="taskList"] li>div {
    flex: 1;
    min-width: 0;
}

.a4-page .tiptap ul[data-type="taskList"] li[data-checked="true"]>div {
    text-decoration: line-through;
    color: #9ca3af;
}

/* Links */
.a4-page .tiptap a {
    color: #2563eb;
    text-decoration: underline;
    text-underline-offset: 2px;
    cursor: pointer;
}

.a4-page .tiptap a:hover {
    color: #1d4ed8;
}

/* Placeholder */
.tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: #adb5bd;
    pointer-events: none;
    height: 0;
}

/* Custom scrollbar for TOC */
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e5e7eb;
    border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #d1d5db;
}

/* Table styles */
.tiptap table {
    border-collapse: collapse;
    table-layout: fixed;
    width: 100%;
    margin: 0;
    overflow: hidden;
}

.tiptap table td,
.tiptap table th {
    min-width: 1em;
    border: var(--table-border-width, 1px) solid var(--table-border-color, #ced4da);
    padding: 3px 5px;
    vertical-align: top;
    box-sizing: border-box;
    position: relative;
}

.tiptap table th {
    font-weight: bold;
    text-align: left;
    background-color: var(--table-border-color, #f1f3f5);
    /* Not ideal but fine, actually keep it #f1f3f5 or use another var */
}

.tiptap table .selectedCell:after {
    z-index: 2;
    position: absolute;
    content: "";
    left: 0;
    right: 0;
    top: 0;
    bottom: 0;
    background: rgba(200, 200, 255, 0.4);
    pointer-events: none;
}

.tiptap table .column-resize-handle {
    position: absolute;
    right: -2px;
    top: 0;
    bottom: -2px;
    width: 4px;
    background-color: #adf;
    pointer-events: none;
}

/* Version modal */
.version-diff-del {
    background-color: #fee2e2;
    color: #991b1b;
    text-decoration: line-through;
    border-radius: 2px;
    padding: 0 1px;
}

.version-diff-ins {
    background-color: #dcfce7;
    color: #166534;
    text-decoration: underline;
    border-radius: 2px;
    padding: 0 1px;
}

.version-readonly {
    pointer-events: none;
    user-select: text;
}

.version-diff {
    white-space: pre-wrap;
    word-break: break-word;
    font-size: 11pt;
    line-height: 1.8;
    color: #1a1a1a;
    min-height: calc(297mm - 50mm);
    user-select: text;
}
</style>
