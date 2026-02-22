<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
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
import RelationshipOverlayViewer from '@/Components/RelationshipOverlayViewer.vue';
import {
    ChevronLeft, CheckCircle2, RefreshCw, AlertCircle, CircleDashed,
    Menu, X, GitCompare,
    Columns, Rows, Plus, Minus, Trash2,
    Link2Off, ExternalLink, Download, FileText, RefreshCcw, Search
} from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
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
        const response = await axios.put(route('library.documents.update', props.document.id), {
            title: form.title,
            content: form.content,
            css: form.css
        });

        const pendingIds = response?.data?.pending_relationship_ids;
        if (Array.isArray(pendingIds) && pendingIds.length > 0) {
            const pendingSet = new Set(pendingIds.map(id => Number(id)));
            relationshipList.value = relationshipList.value.map((rel) => {
                if (!pendingSet.has(Number(rel.id))) return rel;

                const syntheticPendency = {
                    id: `local-${Date.now()}-${rel.id}`,
                    relationship_id: rel.id,
                    trigger_document_id: props.document.id,
                    trigger_paragraph_id: rel.current_paragraph_id,
                    reviewed_at: null,
                };

                const currentItems = Array.isArray(rel.pending_items) ? rel.pending_items : [];
                const alreadyOpen = currentItems.some((item) =>
                    Number(item.trigger_document_id) === Number(props.document.id)
                    && String(item.trigger_paragraph_id) === String(rel.current_paragraph_id)
                    && !item.reviewed_at
                );

                const nextItems = alreadyOpen ? currentItems : [syntheticPendency, ...currentItems];
                const active = nextItems[0] || null;

                return {
                    ...rel,
                    is_pending: nextItems.length > 0,
                    pending_items: nextItems,
                    active_pendency_id: active?.id || null,
                    pending_trigger_document_id: active?.trigger_document_id || null,
                    pending_trigger_paragraph_id: active?.trigger_paragraph_id || null,
                };
            });
        }

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
const rightPanelRef = ref(null);
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

// =============================================
// Document Relationships

const handleEditorClick = (e) => {
    // Traverse DOM upwards until we hit a block-level Tiptap element (para, heading, list item)
    let el = e.target;
    while (el && el.classList && !el.classList.contains('a4-page')) {
        let id = el.id || el.dataset.id;
        if ((el.tagName === 'P' || el.tagName === 'H1' || el.tagName === 'H2' || el.tagName === 'H3' || el.tagName === 'LI') && id) {

            // Dot marker uses ::before with left: -20px and width: 6px.
            // Since it's a pseudo-element, we detect clicks by X-range near the marker.
            const rect = el.getBoundingClientRect();
            const dotLeftOffsetPx = -20;
            const dotSizePx = 6;
            const dotHitSlopPx = 10;
            const dotStartX = rect.left + dotLeftOffsetPx - dotHitSlopPx;
            const dotEndX = rect.left + dotLeftOffsetPx + dotSizePx + dotHitSlopPx;

            if (e.clientX >= dotStartX && e.clientX <= dotEndX) {

                // Does this paragraph have relationships?
                const rel = documentRelationships.value.find(r => r.current_paragraph_id === id);
                if (rel) {
                    showRightPanel.value = true;
                    nextTick(() => {
                        rightPanelRef.value?.focusRelationshipParagraph(id);
                        navigateToRelationship(rel);
                    });
                    return; // Handled
                }
            }
            break;
        }
        el = el.parentElement;
    }
};
// =============================================
const showRelationshipModal = ref(false);
const selectedRelationshipDocument = ref(null);
const relationshipSearchQuery = ref('');
const activeRelationshipView = ref(null);

// Relationship Selection State
const selectedParagraphCurrent = ref(null); // ID of the selected paragraph in the current document
const selectedParagraphRelated = ref([]); // IDs no documento relacionado (seleção múltipla)

const extractParagraphPreview = (html, paragraphId) => {
    if (!html || !paragraphId) return '';

    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;

    let target = null;
    try {
        target = wrapper.querySelector(`[data-id="${paragraphId}"]`);
    } catch {
        target = null;
    }

    if (!target) {
        target = wrapper.querySelector(`[id="${paragraphId}"]`) || wrapper.getElementById?.(paragraphId);
    }

    if (!target) return '';

    const text = (target.textContent || '').replace(/\s+/g, ' ').trim();
    if (!text) return '';

    return text.length > 120 ? `${text.slice(0, 117)}...` : text;
};

const updateParagraphTextInHtml = (html, paragraphId, text) => {
    if (!html || !paragraphId) return html || '';

    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;

    let target = null;
    try {
        target = wrapper.querySelector(`[data-id="${paragraphId}"]`);
    } catch {
        target = null;
    }
    if (!target) {
        target = wrapper.querySelector(`[id="${paragraphId}"]`) || wrapper.getElementById?.(paragraphId);
    }
    if (!target) return html;

    target.textContent = text;
    return wrapper.innerHTML;
};

const normalizeDocumentRelationships = (document) => {
    const list = [];
    if (!document) return list;

    // sourceRelationships (current is source, related is target)
    if (document.source_relationships) {
        document.source_relationships.forEach(rel => {
            const pendingItems = (rel.pendencies || [])
                .filter(item => !item.reviewed_at)
                .sort((a, b) => Number(b.id) - Number(a.id));
            const activePendency = pendingItems[0] || null;

            list.push({
                ...rel,
                is_source: true,
                related_document_id: rel.target_document_id,
                related_document_title: rel.target_document?.title || `Documento #${rel.target_document_id}`,
                related_document_content: rel.target_document?.content || '',
                current_paragraph_id: rel.source_paragraph_id,
                related_paragraph_id: rel.target_paragraph_id,
                is_pending: pendingItems.length > 0,
                pending_items: pendingItems,
                active_pendency_id: activePendency?.id || null,
                pending_trigger_document_id: activePendency?.trigger_document_id || null,
                pending_trigger_paragraph_id: activePendency?.trigger_paragraph_id || null,
                related_paragraph_preview: extractParagraphPreview(
                    rel.target_document?.content,
                    rel.target_paragraph_id
                ),
            });
        });
    }

    // targetRelationships (current is target, related is source)
    if (document.target_relationships) {
        document.target_relationships.forEach(rel => {
            const pendingItems = (rel.pendencies || [])
                .filter(item => !item.reviewed_at)
                .sort((a, b) => Number(b.id) - Number(a.id));
            const activePendency = pendingItems[0] || null;

            list.push({
                ...rel,
                is_source: false,
                related_document_id: rel.source_document_id,
                related_document_title: rel.source_document?.title || `Documento #${rel.source_document_id}`,
                related_document_content: rel.source_document?.content || '',
                current_paragraph_id: rel.target_paragraph_id,
                related_paragraph_id: rel.source_paragraph_id,
                is_pending: pendingItems.length > 0,
                pending_items: pendingItems,
                active_pendency_id: activePendency?.id || null,
                pending_trigger_document_id: activePendency?.trigger_document_id || null,
                pending_trigger_paragraph_id: activePendency?.trigger_paragraph_id || null,
                related_paragraph_preview: extractParagraphPreview(
                    rel.source_document?.content,
                    rel.source_paragraph_id
                ),
            });
        });
    }

    // Sort by id descending (newest first)
    return list.sort((a, b) => b.id - a.id);
};

const relationshipList = ref([]);

watch(
    () => props.document,
    (document) => {
        relationshipList.value = normalizeDocumentRelationships(document);
    },
    { immediate: true }
);

const documentRelationships = computed(() => {
    return relationshipList.value;
});

watch(documentRelationships, (list) => {
    if (!activeRelationshipView.value) return;
    const updated = list.find(rel => rel.id === activeRelationshipView.value.id);
    if (!updated) {
        activeRelationshipView.value = null;
        return;
    }
    activeRelationshipView.value = updated;
});

const relationshipStyles = computed(() => {
    if (!documentRelationships.value || documentRelationships.value.length === 0) return '';

    const paragraphStyleMap = new Map();

    documentRelationships.value.forEach((rel) => {
        const existing = paragraphStyleMap.get(rel.current_paragraph_id);
        if (!existing) {
            paragraphStyleMap.set(rel.current_paragraph_id, {
                isPending: !!rel.is_pending,
            });
            return;
        }
        existing.isPending = existing.isPending || !!rel.is_pending;
    });

    return Array.from(paragraphStyleMap.entries()).map(([paragraphId, style]) => {
        if (style.isPending) {
            return `.a4-page .tiptap [data-id="${paragraphId}"]::before { opacity: 1; background-color: #f59e0b; box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.25); }`;
        }
        return `.a4-page .tiptap [data-id="${paragraphId}"]::before { opacity: 1; background-color: #8b5cf6; box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.2); }`;
    }).join('\n');
});

const removeRelationship = (rel) => {
    if (!rel?.id) {
        console.error('Relationship id inválido para exclusão:', rel);
        return;
    }

    axios.delete(route('library.documents.relationships.destroy', {
        document: props.document.id,
        relationship: rel.id,
    }), {
        headers: {
            Accept: 'application/json',
        },
    })
        .then(() => {
            relationshipList.value = relationshipList.value.filter(item => item.id !== rel.id);
            if (activeRelationshipView.value?.id === rel.id) {
                activeRelationshipView.value = null;
            }
        })
        .catch(err => {
            console.error(err);
            alert('Não foi possível excluir o relacionamento. Tente novamente.');
        });
};

const resolveRelationshipPendency = (rel) => {
    if (!rel?.id || !rel?.active_pendency_id) return;

    const activePendencyId = String(rel.active_pendency_id);
    const isLocalPendency = activePendencyId.startsWith('local-');

    axios.post(route('library.documents.relationships.resolve-pendency', {
        document: props.document.id,
        relationship: rel.id,
    }), {
        ...(isLocalPendency ? {} : { pendency_id: rel.active_pendency_id }),
    }, {
        headers: {
            Accept: 'application/json',
        },
    })
        .then((response) => {
            const reviewedId = response?.data?.reviewed_pendency_id;
            if (isLocalPendency || !reviewedId) {
                router.reload({ only: ['document'] });
                return;
            }

            relationshipList.value = relationshipList.value.map((item) => {
                if (Number(item.id) !== Number(rel.id)) return item;

                const nextPendingItems = (item.pending_items || []).filter(p =>
                    String(p.id) !== String(reviewedId)
                );
                const active = nextPendingItems[0] || null;

                return {
                    ...item,
                    is_pending: nextPendingItems.length > 0,
                    pending_items: nextPendingItems,
                    active_pendency_id: active?.id || null,
                    pending_trigger_document_id: active?.trigger_document_id || null,
                    pending_trigger_paragraph_id: active?.trigger_paragraph_id || null,
                };
            });
        })
        .catch((err) => {
            console.error(err);
            alert('Não foi possível marcar a pendência como revisada.');
        });
};

const saveInlineRelationshipEdit = ({ side, text, relationship }) => {
    if (!relationship?.id || !text) return;

    const isCurrentSide = side === 'current';
    const documentId = isCurrentSide ? props.document.id : relationship.related_document_id;
    const paragraphId = isCurrentSide ? relationship.current_paragraph_id : relationship.related_paragraph_id;

    const baseContent = isCurrentSide
        ? getCurrentContent()
        : (relationship.related_document_content || '');

    const nextContent = updateParagraphTextInHtml(baseContent, paragraphId, text);
    if (!nextContent || nextContent === baseContent) return;

    axios.put(route('library.documents.update', documentId), {
        content: nextContent,
        exclude_relationship_id: relationship.id,
    })
        .then((response) => {
            const pendingIds = response?.data?.pending_relationship_ids;
            if (Array.isArray(pendingIds) && pendingIds.length > 0) {
                const pendingSet = new Set(pendingIds.map(id => Number(id)));
                relationshipList.value = relationshipList.value.map((rel) => {
                    if (!pendingSet.has(Number(rel.id))) return rel;
                    if (Number(rel.id) === Number(relationship.id)) return rel;

                    const syntheticPendency = {
                        id: `local-${Date.now()}-${rel.id}`,
                        relationship_id: rel.id,
                        trigger_document_id: documentId,
                        trigger_paragraph_id: isCurrentSide ? rel.current_paragraph_id : rel.related_paragraph_id,
                        reviewed_at: null,
                    };

                    const currentItems = Array.isArray(rel.pending_items) ? rel.pending_items : [];
                    const nextItems = [syntheticPendency, ...currentItems];
                    const active = nextItems[0] || null;

                    return {
                        ...rel,
                        is_pending: true,
                        pending_items: nextItems,
                        active_pendency_id: active?.id || null,
                        pending_trigger_document_id: active?.trigger_document_id || null,
                        pending_trigger_paragraph_id: active?.trigger_paragraph_id || null,
                    };
                });
            }

            if (isCurrentSide) {
                form.content = nextContent;
                if (editor.value) {
                    editor.value.commands.setContent(nextContent, false);
                }
            } else {
                relationshipList.value = relationshipList.value.map((rel) => {
                    if (Number(rel.id) !== Number(relationship.id)) return rel;
                    return {
                        ...rel,
                        related_document_content: nextContent,
                        related_paragraph_preview: extractParagraphPreview(nextContent, relationship.related_paragraph_id),
                    };
                });
            }
        })
        .catch((err) => {
            console.error(err);
            alert('Não foi possível salvar a edição inline.');
        });
};

const openRelationshipViewer = (rel) => {
    if (!rel) return;
    activeRelationshipView.value = rel;
    navigateToRelationship(rel);
};

const selectRelationshipInViewer = (rel) => {
    openRelationshipViewer(rel);
};

const closeRelationshipViewer = () => {
    activeRelationshipView.value = null;
};

const handleRightPanelTabChange = (tab) => {
    if (tab !== 'relationships') {
        closeRelationshipViewer();
    }
};

watch(showRightPanel, (isOpen) => {
    if (!isOpen) {
        closeRelationshipViewer();
    }
});

const navigateToRelationship = (rel) => {
    // Scroll to the current paragraph block
    scrollToHeading(rel.current_paragraph_id);

    // Highlight it temporarily
    const element = document.querySelector(`[data-id="${rel.current_paragraph_id}"]`) || document.getElementById(rel.current_paragraph_id);
    if (element) {
        const originalBg = element.style.backgroundColor;
        element.style.backgroundColor = 'rgba(139, 92, 246, 0.2)'; // violet-500 light
        element.style.outline = '2px solid rgba(139, 92, 246, 0.8)';

        setTimeout(() => {
            element.style.backgroundColor = originalBg;
            element.style.outline = 'none';
        }, 2000);
    }
};

const availableDocuments = ref([]);
const loadingDocuments = ref(false);

const openRelationshipModal = () => {
    relationshipSearchQuery.value = '';
    showRelationshipModal.value = true;
    closeRelationshipViewer();
    // Carregar documentos se a lista estiver vazia
    if (availableDocuments.value.length === 0) {
        loadingDocuments.value = true;
        axios.get(route('library.navigator.documents', { book_id: props.document.library_book_id }))
            .then(res => {
                availableDocuments.value = res.data;
            })
            .catch(err => {
                console.error("Erro ao carregar documentos:", err);
            })
            .finally(() => {
                loadingDocuments.value = false;
            });
    }
};

const closeRelationshipModal = () => {
    showRelationshipModal.value = false;
};

const filteredRelationshipDocuments = computed(() => {
    if (!relationshipSearchQuery.value) return availableDocuments.value;
    const lowerQuery = relationshipSearchQuery.value.toLowerCase();
    return availableDocuments.value.filter(doc => doc.title.toLowerCase().includes(lowerQuery));
});

// Handling clicks on the read-only views
const handleCurrentDocumentClick = (e) => {
    // Traverse DOM upwards until we hit a block-level Tiptap element (para, heading, list item)
    let el = e.target;
    while (el && el !== e.currentTarget) {
        let id = el.id || el.dataset.id;
        if ((el.tagName === 'P' || el.tagName === 'H1' || el.tagName === 'H2' || el.tagName === 'H3' || el.tagName === 'LI') && id) {
            selectedParagraphCurrent.value = id === selectedParagraphCurrent.value ? null : id;
            break;
        }
        el = el.parentElement;
    }
};

const handleRelatedDocumentClick = (e) => {
    let el = e.target;
    while (el && el !== e.currentTarget) {
        let id = el.id || el.dataset.id;
        if ((el.tagName === 'P' || el.tagName === 'H1' || el.tagName === 'H2' || el.tagName === 'H3' || el.tagName === 'LI') && id) {
            const exists = selectedParagraphRelated.value.includes(id);
            if (exists) {
                selectedParagraphRelated.value = selectedParagraphRelated.value.filter(item => item !== id);
            } else {
                selectedParagraphRelated.value = [...selectedParagraphRelated.value, id];
            }
            break;
        }
        el = el.parentElement;
    }
};

const selectDocumentForRelationship = (doc) => {
    selectedRelationshipDocument.value = doc;
    showRelationshipModal.value = false;
    selectedParagraphCurrent.value = null;
    selectedParagraphRelated.value = [];
};

const closeRelationshipView = () => {
    selectedRelationshipDocument.value = null;
    selectedParagraphCurrent.value = null;
    selectedParagraphRelated.value = [];
};

const getRelationshipCurrentContent = () => {
    let content = getCurrentContent();
    if (selectedParagraphCurrent.value) {
        // Inject the active class into the HTML element with the matching id or data-id
        const regex = new RegExp(`(<[^>]+(?:id|data-id)="${selectedParagraphCurrent.value}"[^>]*class=")([^"]*)(")`, 'i');
        if (regex.test(content)) {
            content = content.replace(regex, `$1$2 is-selected-paragraph$3`);
        } else {
            // Element might not have a class attribute yet
            const regexNoClass = new RegExp(`(<[^>]+(?:id|data-id)="${selectedParagraphCurrent.value}"[^>]*)(>)`, 'i');
            content = content.replace(regexNoClass, `$1 class="is-selected-paragraph"$2`);
        }
    }
    return content;
};

const getRelationshipRelatedContent = () => {
    if (!selectedRelationshipDocument.value) return '';
    let content = selectedRelationshipDocument.value.content;
    if (selectedParagraphRelated.value.length > 0) {
        selectedParagraphRelated.value.forEach((paragraphId) => {
            const regex = new RegExp(`(<[^>]+(?:id|data-id)="${paragraphId}"[^>]*class=")([^"]*)(")`, 'i');
            if (regex.test(content)) {
                content = content.replace(regex, `$1$2 is-selected-paragraph$3`);
            } else {
                const regexNoClass = new RegExp(`(<[^>]+(?:id|data-id)="${paragraphId}"[^>]*)(>)`, 'i');
                content = content.replace(regexNoClass, `$1 class="is-selected-paragraph"$2`);
            }
        });
    }
    return content;
};

const submitRelationship = () => {
    const payload = {
        source_paragraph_id: selectedParagraphCurrent.value,
        target_document_id: selectedRelationshipDocument.value.id,
        target_paragraph_ids: selectedParagraphRelated.value
    };

    axios.post(route('library.documents.relationships.store', props.document.id), payload)
        .then(() => {
            // Force reload to get updated relationships from server
            router.reload({ only: ['document'] });
            closeRelationshipView();
        })
        .catch(error => {
            console.error('Error saving relationship:', error);
            const message = error?.response?.data?.message
                || error?.response?.data?.errors?.target_paragraph_ids?.[0]
                || error?.response?.data?.errors?.target_paragraph_id?.[0]
                || 'Não foi possível criar o relacionamento.';
            alert(message);
        });
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
                    <div class="a4-page" @contextmenu="handleContextMenu" @click="handleEditorClick">
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
            <RelationshipOverlayViewer :open="!!activeRelationshipView" :relationship="activeRelationshipView"
                :relationships="documentRelationships" :current-document-title="form.title"
                :current-document-content="getCurrentContent()" @close="closeRelationshipViewer"
                @select-relationship="selectRelationshipInViewer" @remove-relationship="removeRelationship"
                @resolve-pendency="resolveRelationshipPendency" @inline-edit-save="saveInlineRelationshipEdit" />

            <DocumentRightPanel ref="rightPanelRef" v-model:show="showRightPanel" :toc-items="tocItems"
                :version-history="versionHistory"
                :active-version-id="versionModal.version?.id" :relationships="documentRelationships"
                @scroll-to-heading="scrollToHeading" @open-version-modal="openVersionModal" @save-version="saveVersion"
                @delete-version="deleteVersion" @export-version="v => exportToPdf(v)"
                @open-relationship-modal="openRelationshipModal" @remove-relationship="removeRelationship"
                @open-relationship-viewer="openRelationshipViewer"
                @active-tab-change="handleRightPanelTabChange"
                @navigate-to-relationship="navigateToRelationship" />

        </div>

        <!-- Side-by-Side Reference Overlay (Full Screen) -->
        <Teleport to="body">
            <div v-if="selectedRelationshipDocument"
                class="fixed inset-0 z-[100] bg-black/60 flex flex-col items-center justify-center p-4 sm:p-6 pb-24">

                <!-- Removed floating cancel button -->

                <div class="flex flex-col md:flex-row gap-4 sm:gap-6 w-full h-full relative">

                    <!-- Coluna 1: Documento Atual -->
                    <div
                        class="flex-1 flex flex-col bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden min-h-0">
                        <div
                            :class="[props.document.book?.color || 'bg-violet-700', props.document.book?.text_color || 'text-white', 'px-4 py-3 font-medium flex items-center min-w-0 shrink-0']">
                            <span v-if="selectedParagraphCurrent"
                                class="text-xs font-semibold bg-white/30 px-2 py-0.5 rounded mr-3 shrink-0">1
                                Selecionado</span>
                            <span class="truncate text-sm mr-2 opacity-80 shrink-0">Documento Atual:</span>
                            <span class="truncate font-semibold">{{ form.title }}</span>
                        </div>
                        <div
                            class="flex-1 overflow-y-auto p-8 relative flex justify-center bg-gray-50/50 tiptap-relationship-container">
                            <div class="a4-page !min-h-0 relationship-selection-area"
                                :class="{ 'has-selection': selectedParagraphCurrent }"
                                style="width: 100%; max-width: 210mm; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"
                                @click="handleCurrentDocumentClick">
                                <div class="tiptap version-readonly"
                                    :class="{ 'document-has-selection': selectedParagraphCurrent }"
                                    v-html="getRelationshipCurrentContent()" />
                            </div>
                        </div>
                    </div>

                    <!-- Coluna 2: Documento Relacionado -->
                    <div
                        class="flex-1 flex flex-col bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden min-h-0 relative">
                        <div
                            :class="[selectedRelationshipDocument?.library_book_id === props.document.library_book_id ? (props.document.book?.color || 'bg-violet-700') : 'bg-gray-700', 'text-white px-4 py-3 font-medium flex items-center min-w-0 shrink-0']">
                            <span v-if="selectedParagraphRelated.length > 0"
                                class="text-xs font-semibold bg-white/30 px-2 py-0.5 rounded mr-3 shrink-0">
                                {{ selectedParagraphRelated.length }} Selecionado{{ selectedParagraphRelated.length > 1 ? 's' : '' }}
                            </span>
                            <span class="truncate text-sm mr-2 opacity-80 shrink-0">Relacionando com:</span>
                            <span class="truncate font-semibold">{{ selectedRelationshipDocument.title }}</span>
                            <div class="flex items-center gap-1 ml-auto shrink-0">
                                <button @click="openRelationshipModal"
                                    class="p-1.5 hover:bg-white/20 rounded-md transition text-white"
                                    title="Trocar documento relacionado">
                                    <RefreshCcw class="w-4 h-4" />
                                </button>
                                <button @click="closeRelationshipView"
                                    class="border-l border-white/20 pl-3 ml-2 text-sm font-medium hover:text-white/80 transition text-white"
                                    title="Cancelar relacionamento">
                                    Cancelar
                                </button>
                            </div>
                        </div>
                        <div
                            class="flex-1 overflow-y-auto p-8 relative flex justify-center bg-gray-50/50 tiptap-relationship-container">
                            <div class="a4-page !min-h-0 relationship-selection-area"
                                :class="{ 'has-selection': selectedParagraphRelated.length > 0 }"
                                style="width: 100%; max-width: 210mm; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"
                                @click="handleRelatedDocumentClick">
                                <div class="tiptap version-readonly"
                                    :class="{ 'document-has-selection': selectedParagraphRelated.length > 0 }"
                                    v-html="getRelationshipRelatedContent()" />
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Floating Action Button for submitting relationship -->
                <div v-if="selectedParagraphCurrent && selectedParagraphRelated.length > 0"
                    class="absolute bottom-6 right-6 z-[110] animate-in fade-in slide-in-from-bottom-4 duration-300">
                    <button @click="submitRelationship"
                        class="bg-violet-600 hover:bg-violet-700 text-white shadow-xl shadow-violet-500/30 font-semibold py-3 px-6 rounded-full flex items-center gap-2 transition-all transform hover:scale-105 active:scale-95 border border-violet-500/50 text-base">
                        <Plus class="w-5 h-5" />
                        Confirmar
                    </button>
                </div>

            </div>
        </Teleport>

        <!-- Duplicate Panel Removed -->

        <!-- Floating Toggle Panel Button (visible when Panel is hidden) -->
        <button v-if="!showRightPanel" @click="toggleRightPanel"
            class="fixed right-6 top-24 z-20 p-2 bg-white rounded-full shadow-[0_4px_10px_-1px_rgba(0,0,0,0.1)] border hover:bg-gray-50 hover:text-primary transition text-gray-400"
            title="Mostrar Painel">
            <Menu class="w-5 h-5" />
        </button>

        <!-- Relationship File Explorer Modal -->
        <Modal :show="showRelationshipModal" @close="closeRelationshipModal" maxWidth="2xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-lg font-medium text-gray-900 flex items-center gap-2">
                        <LinkIcon class="w-5 h-5 text-gray-500" />
                        Qual documento você deseja relacionar?
                    </h2>
                    <button @click="closeRelationshipModal" class="text-gray-400 hover:text-gray-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="relative mb-4">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input v-model="relationshipSearchQuery" type="text"
                        placeholder="Buscar documento para relacionar..."
                        class="h-10 w-full rounded-md border border-input bg-white pl-9 pr-3 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors" />
                </div>

                <!-- Explorador de Relacionamentos -->
                <div class="border rounded-md divide-y overflow-y-auto max-h-96 custom-scrollbar">
                    <div v-if="loadingDocuments" class="p-4 text-center text-sm text-gray-500">
                        Carregando documentos...
                    </div>
                    <div v-else-if="filteredRelationshipDocuments.length === 0"
                        class="p-4 text-center text-sm text-gray-500">
                        Nenhum documento encontrado.
                    </div>
                    <button v-else v-for="doc in filteredRelationshipDocuments" :key="doc.id"
                        @click="selectDocumentForRelationship(doc)"
                        class="w-full flex items-center gap-3 p-3 hover:bg-gray-50 transition text-left group">
                        <div
                            class="p-2 bg-gray-100 group-hover:bg-primary/10 rounded-md text-gray-500 group-hover:text-primary transition-colors shrink-0">
                            <FileText class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-medium text-gray-900 truncate">{{ doc.title }}</h4>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ doc.id === props.document.id ? 'Este mesmo Documento' : 'Documento na Biblioteca' }}
                            </p>
                        </div>
                    </button>
                </div>

                <div class="mt-6 flex justify-end">
                    <button @click="closeRelationshipModal"
                        class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md transition font-medium">
                        Cancelar
                    </button>
                </div>
            </div>
        </Modal>

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

        <!-- Dynamic CSS Block for Persistently Coloring Relationship Dots -->
        <component is="style" type="text/css">
            {{ relationshipStyles }}
        </component>
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

.tiptap-relationship-container .a4-page {
    padding: 10mm 15mm;
}

/* Relationship Selection Hover Features */
.relationship-selection-area .tiptap>* {
    transition: background-color 0.2s, outline 0.2s;
    cursor: pointer;
    border-radius: 4px;
    margin: -2px;
    padding: 2px;
}

.relationship-selection-area .tiptap>*:hover {
    background-color: rgba(139, 92, 246, 0.1);
    /* purple-500 with opacity */
    outline: 2px solid rgba(139, 92, 246, 0.3);
}

.document-has-selection>*:not(.is-selected-paragraph) {
    opacity: 0.6;
}

.document-has-selection>*.is-selected-paragraph {
    background-color: rgba(139, 92, 246, 0.2);
    outline: 2px solid rgba(139, 92, 246, 0.8);
    opacity: 1;
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

/* Indicators in Editor (Notion-style Hover Dots) */
.a4-page .tiptap {
    padding-left: 1.5rem;
}

.a4-page .tiptap p,
.a4-page .tiptap h1,
.a4-page .tiptap h2,
.a4-page .tiptap h3 {
    position: relative;
}

.a4-page .tiptap p::before,
.a4-page .tiptap h1::before,
.a4-page .tiptap h2::before,
.a4-page .tiptap h3::before {
    content: '';
    position: absolute;
    left: -20px;
    top: 0.6em;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    opacity: 0;
    transition: opacity 0.2s ease, transform 0.2s ease, background-color 0.2s ease;
    cursor: pointer;
    z-index: 10;
}

.a4-page .tiptap p:hover::before,
.a4-page .tiptap h1:hover::before,
.a4-page .tiptap h2:hover::before,
.a4-page .tiptap h3:hover::before {
    transform: scale(1.5);
}
</style>
