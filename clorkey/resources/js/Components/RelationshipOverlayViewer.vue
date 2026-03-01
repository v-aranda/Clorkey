<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { X, Link as LinkIcon, Trash2, CheckCircle2, Pencil, Check, X as XIcon } from 'lucide-vue-next';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    relationship: {
        type: Object,
        default: null,
    },
    currentDocumentTitle: {
        type: String,
        default: '',
    },
    currentDocumentContent: {
        type: String,
        default: '',
    },
    relationships: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'selectRelationship', 'removeRelationship', 'resolvePendency', 'inlineEditSave']);

const relatedDocumentTitle = computed(() => props.relationship?.related_document_title || 'Documento relacionado');
const relatedDocumentContent = computed(() => props.relationship?.related_document_content || '');

const escapeRegExp = (value) => String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const injectRelationshipHighlight = (html, paragraphId) => {
    if (!html || !paragraphId) return html || '';
    const escapedId = escapeRegExp(paragraphId);

    const regexWithClass = new RegExp(`(<[^>]+(?:id|data-id)="${escapedId}"[^>]*class=")([^"]*)(")`, 'i');
    if (regexWithClass.test(html)) {
        return html.replace(regexWithClass, `$1$2 relationship-overlay-highlight$3`);
    }

    const regexNoClass = new RegExp(`(<[^>]+(?:id|data-id)="${escapedId}"[^>]*)(>)`, 'i');
    return html.replace(regexNoClass, `$1 class="relationship-overlay-highlight"$2`);
};

const highlightedCurrentContent = computed(() => {
    const highlightClass = props.relationship?.is_pending
        ? 'relationship-overlay-highlight-pending'
        : 'relationship-overlay-highlight';
    return injectRelationshipHighlight(props.currentDocumentContent, props.relationship?.current_paragraph_id)
        .replace(/relationship-overlay-highlight/g, highlightClass);
});

const highlightedRelatedContent = computed(() => {
    const highlightClass = props.relationship?.is_pending
        ? 'relationship-overlay-highlight-pending'
        : 'relationship-overlay-highlight';
    return injectRelationshipHighlight(relatedDocumentContent.value, props.relationship?.related_paragraph_id)
        .replace(/relationship-overlay-highlight/g, highlightClass);
});

const triggerParagraphId = computed(() => props.relationship?.pending_trigger_paragraph_id || null);

const showResolveOnCurrent = computed(() => {
    if (!props.relationship?.is_pending || !triggerParagraphId.value || !props.relationship?.current_paragraph_id) return false;
    return String(triggerParagraphId.value) !== String(props.relationship.current_paragraph_id);
});

const showResolveOnRelated = computed(() => {
    if (!props.relationship?.is_pending || !triggerParagraphId.value || !props.relationship?.related_paragraph_id) return false;
    return String(triggerParagraphId.value) !== String(props.relationship.related_paragraph_id);
});

const currentScrollRef = ref(null);
const relatedScrollRef = ref(null);
const currentResolveStyle = ref(null);
const relatedResolveStyle = ref(null);
const currentEditStyle = ref(null);
const relatedEditStyle = ref(null);
const editingSide = ref(null); // 'current' | 'related' | null
const inlineOriginalText = ref('');

const getOffsetWithin = (container, element) => {
    let top = 0;
    let left = 0;
    let current = element;

    while (current && current !== container) {
        top += current.offsetTop || 0;
        left += current.offsetLeft || 0;
        current = current.offsetParent;
    }

    return { top, left };
};

const updateResolveButtonPosition = (containerRef, styleRef, shouldShow) => {
    if (!shouldShow || !containerRef.value) {
        styleRef.value = null;
        return;
    }

    const target = containerRef.value.querySelector('.relationship-overlay-highlight, .relationship-overlay-highlight-pending');
    if (!target) {
        styleRef.value = null;
        return;
    }

    const { top, left } = getOffsetWithin(containerRef.value, target);
    const buttonTop = top + target.offsetHeight + 8;
    const buttonLeft = left + target.offsetWidth;

    styleRef.value = {
        top: `${buttonTop}px`,
        left: `${buttonLeft}px`,
    };
};

const getHighlightedElement = (containerRef) => {
    if (!containerRef.value) return null;
    return containerRef.value.querySelector('.relationship-overlay-highlight, .relationship-overlay-highlight-pending');
};

const updateEditButtonPosition = (containerRef, styleRef, shouldShow) => {
    if (!shouldShow || !containerRef.value) {
        styleRef.value = null;
        return;
    }
    const target = getHighlightedElement(containerRef);
    if (!target) {
        styleRef.value = null;
        return;
    }
    const { top, left } = getOffsetWithin(containerRef.value, target);
    styleRef.value = {
        top: `${top + 6}px`,
        left: `${left + target.offsetWidth - 6}px`,
    };
};

const getContainerRefBySide = (side) => (side === 'current' ? currentScrollRef : relatedScrollRef);

const getEditableElementBySide = (side) => {
    if (!side) return null;
    return getHighlightedElement(getContainerRefBySide(side));
};

const toggleEditableHighlight = (side, editable) => {
    const target = getEditableElementBySide(side);
    if (!target) return null;
    target.setAttribute('contenteditable', editable ? 'true' : 'false');
    target.classList.toggle('relationship-overlay-highlight-editing', editable);
    return target;
};

const updateResolveButtons = () => {
    updateResolveButtonPosition(currentScrollRef, currentResolveStyle, showResolveOnCurrent.value);
    updateResolveButtonPosition(relatedScrollRef, relatedResolveStyle, showResolveOnRelated.value);
    updateEditButtonPosition(currentScrollRef, currentEditStyle, showResolveOnCurrent.value);
    updateEditButtonPosition(relatedScrollRef, relatedEditStyle, showResolveOnRelated.value);
};

const openInlineEditor = (side) => {
    if (editingSide.value && editingSide.value !== side) {
        toggleEditableHighlight(editingSide.value, false);
    }

    const target = toggleEditableHighlight(side, true);
    if (!target) return;

    editingSide.value = side;
    inlineOriginalText.value = target.innerText || '';

    nextTick(() => {
        target.focus();
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(target);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
    });
};

const finishInlineEdit = (restoreOriginalText = false) => {
    const side = editingSide.value;
    if (!side) return;

    const target = getEditableElementBySide(side);
    if (restoreOriginalText && target) {
        target.innerText = inlineOriginalText.value;
    }

    toggleEditableHighlight(side, false);
    editingSide.value = null;
    inlineOriginalText.value = '';
};

const cancelInlineEdit = () => {
    finishInlineEdit(true);
};

const submitInlineEdit = () => {
    const side = editingSide.value;
    if (!side) return;

    const target = getEditableElementBySide(editingSide.value);
    if (!target) return;

    const text = (target.innerText || '').trim();
    if (!text) return;

    emit('inlineEditSave', {
        side,
        text,
        relationship: props.relationship,
    });

    finishInlineEdit(false);
};

watch(
    () => [props.open, props.relationship?.id, highlightedCurrentContent.value, highlightedRelatedContent.value],
    async () => {
        if (editingSide.value) {
            toggleEditableHighlight(editingSide.value, false);
            editingSide.value = null;
            inlineOriginalText.value = '';
        }
        await nextTick();
        updateResolveButtons();
    }
);

const handleResize = () => {
    updateResolveButtons();
};

onMounted(() => {
    window.addEventListener('resize', handleResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="relationship-overlay">
            <div v-if="open" class="relationship-overlay-viewer fixed inset-0 z-[120]">
                <div class="absolute inset-0 bg-black/75 relationship-overlay__backdrop" @click="emit('close')" />

                <div class="relative h-full p-4 sm:p-6 relationship-overlay__content">
                    <div class="h-full pr-0 lg:pr-[340px]">
                        <div class="h-full flex flex-col lg:flex-row gap-4 sm:gap-6 relationship-overlay__main">
                            <div class="flex-1 min-h-0 bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden">
                                <div class="bg-purple-700 text-white px-4 py-3 flex items-center justify-between rounded-t-xl">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <LinkIcon class="w-4 h-4 shrink-0 text-purple-200" />
                                        <span class="text-xs uppercase tracking-wide text-purple-200">Documento atual</span>
                                    </div>
                                    <div class="flex items-center gap-3 ml-3 min-w-0">
                                        <span class="text-sm font-semibold truncate">{{ currentDocumentTitle }}</span>
                                    </div>
                                </div>
                                <div ref="currentScrollRef" @scroll="updateResolveButtons"
                                    class="h-[calc(100%-52px)] overflow-y-auto p-6 bg-gray-50/40 relative">
                                    <div class="a4-page !min-h-0 !w-full !max-w-none shadow-sm">
                                        <div class="tiptap version-readonly" v-html="highlightedCurrentContent" />
                                    </div>
                                    <div v-if="showResolveOnCurrent && currentEditStyle && (!editingSide || editingSide === 'current')"
                                        class="relationship-edit-actions absolute" :style="currentEditStyle">
                                        <button v-if="editingSide !== 'current'" @click.stop="openInlineEditor('current')"
                                            class="relationship-edit-btn inline-flex items-center justify-center rounded-md bg-white/95 hover:bg-white text-purple-700 border border-purple-200 shadow-sm transition-colors"
                                            title="Editar parágrafo">
                                            <Pencil class="w-3.5 h-3.5" />
                                        </button>
                                        <template v-else>
                                            <button @click.stop="cancelInlineEdit"
                                                class="relationship-edit-btn inline-flex items-center justify-center rounded-md bg-white/95 hover:bg-white text-gray-600 border border-gray-200 shadow-sm transition-colors"
                                                title="Cancelar edição">
                                                <XIcon class="w-3.5 h-3.5" />
                                            </button>
                                            <button @click.stop="submitInlineEdit"
                                                class="relationship-edit-btn inline-flex items-center justify-center rounded-md bg-emerald-500 hover:bg-emerald-600 text-white border border-emerald-600 shadow-sm transition-colors"
                                                title="Salvar edição">
                                                <Check class="w-3.5 h-3.5" />
                                            </button>
                                        </template>
                                    </div>
                                    <button v-if="showResolveOnCurrent && currentResolveStyle" @click.stop="emit('resolvePendency', relationship)"
                                        class="relationship-resolve-btn absolute inline-flex items-center gap-1 rounded-md bg-amber-400 hover:bg-emerald-500 hover:text-white text-amber-950 text-xs font-semibold px-2 py-1 transition-colors shadow-md"
                                        :style="currentResolveStyle" title="Marcar pendência como revisada">
                                        <CheckCircle2 class="w-3.5 h-3.5" />
                                        Solucionar
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 min-h-0 bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden">
                                <div class="bg-purple-700 text-white px-4 py-3 flex items-center justify-between rounded-t-xl">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <LinkIcon class="w-4 h-4 shrink-0 text-purple-200" />
                                        <span class="text-xs uppercase tracking-wide text-purple-200">Relacionado</span>
                                    </div>
                                    <div class="flex items-center gap-2 ml-3 min-w-0">
                                        <span class="text-sm font-semibold truncate">{{ relatedDocumentTitle }}</span>
                                    </div>
                                </div>
                                <div ref="relatedScrollRef" @scroll="updateResolveButtons"
                                    class="h-[calc(100%-52px)] overflow-y-auto p-6 bg-gray-50/40 relative">
                                    <div class="a4-page !min-h-0 !w-full !max-w-none shadow-sm">
                                        <div class="tiptap version-readonly" v-html="highlightedRelatedContent" />
                                    </div>
                                    <div v-if="showResolveOnRelated && relatedEditStyle && (!editingSide || editingSide === 'related')"
                                        class="relationship-edit-actions absolute" :style="relatedEditStyle">
                                        <button v-if="editingSide !== 'related'" @click.stop="openInlineEditor('related')"
                                            class="relationship-edit-btn inline-flex items-center justify-center rounded-md bg-white/95 hover:bg-white text-purple-700 border border-purple-200 shadow-sm transition-colors"
                                            title="Editar parágrafo">
                                            <Pencil class="w-3.5 h-3.5" />
                                        </button>
                                        <template v-else>
                                            <button @click.stop="cancelInlineEdit"
                                                class="relationship-edit-btn inline-flex items-center justify-center rounded-md bg-white/95 hover:bg-white text-gray-600 border border-gray-200 shadow-sm transition-colors"
                                                title="Cancelar edição">
                                                <XIcon class="w-3.5 h-3.5" />
                                            </button>
                                            <button @click.stop="submitInlineEdit"
                                                class="relationship-edit-btn inline-flex items-center justify-center rounded-md bg-emerald-500 hover:bg-emerald-600 text-white border border-emerald-600 shadow-sm transition-colors"
                                                title="Salvar edição">
                                                <Check class="w-3.5 h-3.5" />
                                            </button>
                                        </template>
                                    </div>
                                    <button v-if="showResolveOnRelated && relatedResolveStyle" @click.stop="emit('resolvePendency', relationship)"
                                        class="relationship-resolve-btn absolute inline-flex items-center gap-1 rounded-md bg-amber-400 hover:bg-emerald-500 hover:text-white text-amber-950 text-xs font-semibold px-2 py-1 transition-colors shadow-md"
                                        :style="relatedResolveStyle" title="Marcar pendência como revisada">
                                        <CheckCircle2 class="w-3.5 h-3.5" />
                                        Solucionar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside
                        class="absolute top-0 right-0 h-full w-full max-w-[320px] bg-white border-l border-gray-200 shadow-2xl overflow-hidden relationship-overlay__sidebar">
                        <div class="bg-purple-700 text-white px-4 py-3 flex items-center justify-between">
                            <div class="min-w-0">
                                <span class="text-xs uppercase tracking-wide text-purple-200">Relacionamentos</span>
                                <p class="text-sm font-semibold mt-0.5">{{ currentDocumentTitle }}</p>
                            </div>
                            <button @click="emit('close')" class="text-purple-200 hover:text-white transition-colors"
                                title="Fechar visualização">
                                <X class="w-5 h-5" />
                            </button>
                        </div>
                        <div class="h-[calc(100%-52px)] overflow-y-auto p-3 space-y-2 bg-gray-50/40">
                            <div v-for="rel in relationships" :key="rel.id" @click="emit('selectRelationship', rel)"
                                role="button" tabindex="0" @keydown.enter="emit('selectRelationship', rel)"
                                :class="[
                                    'w-full text-left rounded-lg border p-2.5 transition-colors relative cursor-pointer',
                                    rel.is_pending
                                        ? (relationship?.id === rel.id
                                            ? 'border-amber-400 bg-amber-100'
                                            : 'border-amber-300 bg-amber-50 hover:border-amber-400')
                                        : (relationship?.id === rel.id
                                            ? 'border-purple-400 bg-purple-50'
                                            : 'border-gray-200 bg-white hover:border-purple-300')
                                ]">
                                <button @click.stop="emit('removeRelationship', rel)"
                                    class="absolute top-2 right-2 text-gray-400 hover:text-red-500 transition-colors"
                                    title="Excluir relacionamento">
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                                <p class="text-xs text-gray-500 truncate">{{ rel.related_document_title }}</p>
                                <p class="text-sm font-semibold text-gray-900 truncate pr-6">
                                    {{ rel.related_paragraph_preview || 'Sem prévia do parágrafo relacionado.' }}
                                </p>
                                <span v-if="rel.is_pending"
                                    class="absolute bottom-2 right-2 w-2.5 h-2.5 rounded-full bg-amber-400 ring-2 ring-amber-200"
                                    title="Relacionamento pendente de revisão" />
                            </div>
                            <div v-if="relationships.length === 0" class="text-xs text-gray-500 italic p-1">
                                Sem relacionamentos disponíveis.
                            </div>
                        </div>
                    </aside>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style>
.relationship-overlay-enter-active,
.relationship-overlay-leave-active {
    transition: opacity 0.28s ease;
}

.relationship-overlay-enter-from,
.relationship-overlay-leave-to {
    opacity: 0;
}

.relationship-overlay-enter-active .relationship-overlay__content {
    animation: relationshipOverlayContentIn 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

.relationship-overlay-leave-active .relationship-overlay__content {
    animation: relationshipOverlayContentOut 0.2s ease forwards;
}

.relationship-overlay-enter-active .relationship-overlay__main {
    animation: relationshipOverlayMainIn 0.34s cubic-bezier(0.22, 1, 0.36, 1);
}

.relationship-overlay-enter-active .relationship-overlay__sidebar {
    animation: relationshipOverlaySidebarIn 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes relationshipOverlayContentIn {
    from {
        transform: translateY(10px);
        opacity: 0.94;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes relationshipOverlayContentOut {
    from {
        transform: translateY(0);
        opacity: 1;
    }
    to {
        transform: translateY(8px);
        opacity: 0.98;
    }
}

@keyframes relationshipOverlayMainIn {
    from {
        transform: translateX(-8px) scale(0.995);
        opacity: 0;
    }
    to {
        transform: translateX(0) scale(1);
        opacity: 1;
    }
}

@keyframes relationshipOverlaySidebarIn {
    from {
        transform: translateX(24px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.relationship-overlay-viewer .a4-page .tiptap {
    padding-left: 0 !important;
}

.relationship-overlay-viewer .a4-page .tiptap p::before,
.relationship-overlay-viewer .a4-page .tiptap h1::before,
.relationship-overlay-viewer .a4-page .tiptap h2::before,
.relationship-overlay-viewer .a4-page .tiptap h3::before,
.relationship-overlay-viewer .a4-page .tiptap [data-id]::before,
.relationship-overlay-viewer .a4-page .tiptap [id]::before {
    content: none !important;
    display: none !important;
    opacity: 0 !important;
    box-shadow: none !important;
}

.relationship-overlay-viewer .tiptap .relationship-overlay-highlight {
    background-color: rgba(147, 51, 234, 0.16);
    outline: 2px solid rgba(147, 51, 234, 0.7);
    border-radius: 4px;
}

.relationship-overlay-viewer .tiptap .relationship-overlay-highlight-pending {
    background-color: rgba(245, 158, 11, 0.22);
    outline: 2px solid rgba(245, 158, 11, 0.85);
    border-radius: 4px;
}

.relationship-overlay-viewer .tiptap .relationship-overlay-highlight-editing {
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.55) inset;
    cursor: text;
}

.relationship-overlay-viewer .relationship-resolve-btn {
    transform: translateX(calc(-100% + 8px));
    z-index: 5;
}

.relationship-overlay-viewer .relationship-edit-actions {
    transform: translateX(calc(-100% + 6px));
    display: inline-flex;
    align-items: center;
    gap: 4px;
    z-index: 7;
}

.relationship-overlay-viewer .relationship-edit-btn {
    width: 26px;
    height: 26px;
    z-index: 6;
}
</style>
