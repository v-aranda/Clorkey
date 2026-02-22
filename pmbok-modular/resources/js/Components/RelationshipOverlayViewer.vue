<script setup>
import { computed } from 'vue';
import { X, Link as LinkIcon, Trash2 } from 'lucide-vue-next';

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

const emit = defineEmits(['close', 'selectRelationship', 'removeRelationship']);

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
    return injectRelationshipHighlight(props.currentDocumentContent, props.relationship?.current_paragraph_id);
});

const highlightedRelatedContent = computed(() => {
    return injectRelationshipHighlight(relatedDocumentContent.value, props.relationship?.related_paragraph_id);
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
                                <div class="bg-violet-700 text-white px-4 py-3 flex items-center justify-between rounded-t-xl">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <LinkIcon class="w-4 h-4 shrink-0 text-violet-200" />
                                        <span class="text-xs uppercase tracking-wide text-violet-200">Documento atual</span>
                                    </div>
                                    <span class="text-sm font-semibold truncate ml-3">{{ currentDocumentTitle }}</span>
                                </div>
                                <div class="h-[calc(100%-52px)] overflow-y-auto p-6 bg-gray-50/40">
                                    <div class="a4-page !min-h-0 !w-full !max-w-none shadow-sm">
                                        <div class="tiptap version-readonly" v-html="highlightedCurrentContent" />
                                    </div>
                                </div>
                            </div>

                            <div class="flex-1 min-h-0 bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden">
                                <div class="bg-violet-700 text-white px-4 py-3 flex items-center justify-between rounded-t-xl">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <LinkIcon class="w-4 h-4 shrink-0 text-violet-200" />
                                        <span class="text-xs uppercase tracking-wide text-violet-200">Relacionado</span>
                                    </div>
                                    <div class="flex items-center gap-2 ml-3 min-w-0">
                                        <span class="text-sm font-semibold truncate">{{ relatedDocumentTitle }}</span>
                                    </div>
                                </div>
                                <div class="h-[calc(100%-52px)] overflow-y-auto p-6 bg-gray-50/40">
                                    <div class="a4-page !min-h-0 !w-full !max-w-none shadow-sm">
                                        <div class="tiptap version-readonly" v-html="highlightedRelatedContent" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside
                        class="absolute top-0 right-0 h-full w-full max-w-[320px] bg-white border-l border-gray-200 shadow-2xl overflow-hidden relationship-overlay__sidebar">
                        <div class="bg-violet-700 text-white px-4 py-3 flex items-center justify-between">
                            <div class="min-w-0">
                                <span class="text-xs uppercase tracking-wide text-violet-200">Relacionamentos</span>
                                <p class="text-sm font-semibold mt-0.5">{{ currentDocumentTitle }}</p>
                            </div>
                            <button @click="emit('close')" class="text-violet-200 hover:text-white transition-colors"
                                title="Fechar visualização">
                                <X class="w-5 h-5" />
                            </button>
                        </div>
                        <div class="h-[calc(100%-52px)] overflow-y-auto p-3 space-y-2 bg-gray-50/40">
                            <div v-for="rel in relationships" :key="rel.id" @click="emit('selectRelationship', rel)"
                                role="button" tabindex="0" @keydown.enter="emit('selectRelationship', rel)"
                                :class="[
                                    'w-full text-left rounded-lg border p-2.5 transition-colors relative cursor-pointer',
                                    relationship?.id === rel.id
                                        ? 'border-violet-400 bg-violet-50'
                                        : 'border-gray-200 bg-white hover:border-violet-300'
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
    background-color: rgba(139, 92, 246, 0.16);
    outline: 2px solid rgba(139, 92, 246, 0.7);
    border-radius: 4px;
}
</style>
