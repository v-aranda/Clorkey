<script setup>
import { ref, computed, watch } from 'vue';
import { ListTree, History, Trash2, Link as LinkIcon, Link2Off, Plus, ChevronLeft, ChevronRight, ExternalLink, X } from 'lucide-vue-next';
import DocumentVersionHistory from '@/Components/DocumentVersionHistory.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    show: Boolean,
    tocItems: Array,
    versionHistory: Array,
    activeVersionId: [Number, String],
    relationships: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits([
    'update:show',
    'scrollToHeading',
    'openVersionModal',
    'saveVersion',
    'deleteVersion',
    'exportVersion',
    'openRelationshipModal',
    'removeRelationship',
    'openRelationshipViewer',
    'activeTabChange',
    'navigateToRelationship'
]);

const activeTab = ref('index');
const showDeleteDialog = ref(false);
const relationshipToDelete = ref(null);

const groupedRelationships = computed(() => {
    const groups = {};
    props.relationships.forEach(rel => {
        if (!groups[rel.current_paragraph_id]) {
            groups[rel.current_paragraph_id] = {
                paragraphId: rel.current_paragraph_id,
                relationships: []
            };
        }
        groups[rel.current_paragraph_id].relationships.push(rel);
    });
    return Object.values(groups);
});

const currentParagraphIndex = ref(0);

watch(groupedRelationships, (newVal) => {
    if (currentParagraphIndex.value >= newVal.length) {
        currentParagraphIndex.value = Math.max(0, newVal.length - 1);
    }
});

watch(activeTab, (tab) => {
    emit('activeTabChange', tab);
});

const currentGroup = computed(() => {
    if (groupedRelationships.value.length === 0) return null;
    return groupedRelationships.value[currentParagraphIndex.value];
});

const prevParagraph = () => {
    if (currentParagraphIndex.value > 0) {
        currentParagraphIndex.value--;
        if (currentGroup.value) {
            emit('navigateToRelationship', currentGroup.value.relationships[0]);
        }
    }
};

const nextParagraph = () => {
    if (currentParagraphIndex.value < groupedRelationships.value.length - 1) {
        currentParagraphIndex.value++;
        if (currentGroup.value) {
            emit('navigateToRelationship', currentGroup.value.relationships[0]);
        }
    }
};

const jumpToCurrentParagraph = () => {
    if (currentGroup.value) {
        emit('navigateToRelationship', currentGroup.value.relationships[0]);
    }
};

const openDeleteRelationship = (rel) => {
    relationshipToDelete.value = rel;
    showDeleteDialog.value = true;
};

const submitDeleteRelationship = () => {
    if (!relationshipToDelete.value) return;
    emit('removeRelationship', relationshipToDelete.value);
    showDeleteDialog.value = false;
    relationshipToDelete.value = null;
};

const focusRelationshipParagraph = (paragraphId) => {
    activeTab.value = 'relationships';
    const idx = groupedRelationships.value.findIndex(group => group.paragraphId === paragraphId);
    if (idx !== -1) {
        currentParagraphIndex.value = idx;
    }
};

const openTab = (tab) => {
    activeTab.value = tab;
    emit('update:show', true);
};

defineExpose({
    focusRelationshipParagraph,
});
</script>

<template>
    <div class="relative shrink-0 flex sticky top-0 self-start min-h-[calc(100vh-65px)]">

        <!-- Botões externos de alternância (pill-shaped, posicionados à esquerda do painel) -->
        <div class="absolute -left-11 top-14 z-30 flex flex-col items-center gap-2">
            <template v-if="!show">
                <button
                    @click="openTab('index')"
                    class="flex h-11 w-11 items-center justify-center rounded-l-2xl border border-r-0 shadow transition-all duration-300 bg-purple-700 border-transparent text-white"
                    title="Índice"
                    aria-label="Abrir índice"
                >
                    <ListTree class="h-5 w-5" />
                </button>

                <button
                    @click="openTab('history')"
                    class="flex h-11 w-11 items-center justify-center rounded-l-2xl border border-r-0 shadow transition-all duration-300 bg-purple-700 border-transparent text-white"
                    title="Histórico de Versões"
                    aria-label="Abrir histórico de versões"
                >
                    <History class="h-5 w-5" />
                </button>

                <button
                    @click="openTab('relationships')"
                    class="flex h-11 w-11 items-center justify-center rounded-l-2xl border border-r-0 shadow transition-all duration-300 bg-purple-700 border-transparent text-white"
                    title="Relacionamentos"
                    aria-label="Abrir relacionamentos"
                >
                    <LinkIcon class="h-5 w-5" />
                </button>
            </template>
            <template v-else>
                <button
                    @click="emit('update:show', false)"
                    class="flex h-11 w-11 items-center justify-center rounded-l-2xl border border-r-0 shadow transition-all duration-300 bg-white text-gray-400"
                    title="Fechar painel"
                    aria-label="Fechar painel"
                >
                    <X class="h-5 w-5" />
                </button>
            </template>
        </div>

        <!-- Painel colapsável (oculto por width, não por opacity/display) -->
        <div
            :class="[
                'flex flex-col bg-white transition-all duration-200 overflow-hidden h-full border-l',
                show ? 'w-64 border-gray-200' : 'w-0 border-transparent'
            ]"
        >
            <div class="flex w-64 shrink-0 flex-col h-full">

                <!-- Cabeçalho: barra de ícones de abas (sem botão X) -->
                <div class="flex shrink-0 items-center gap-1 border-b border-gray-100 px-3 py-3">
                    <button
                        @click="activeTab = 'index'"
                        :class="['rounded-md p-2 transition-colors duration-500', activeTab === 'index' ? 'bg-purple-100 text-purple-700' : 'bg-white text-gray-400']"
                        title="Índice"
                    >
                        <ListTree class="w-4 h-4" />
                    </button>
                    <button
                        @click="activeTab = 'history'"
                        :class="['rounded-md p-2 transition-colors duration-500', activeTab === 'history' ? 'bg-purple-100 text-purple-700' : 'bg-white text-gray-400']"
                        title="Histórico de Versões"
                    >
                        <History class="w-4 h-4" />
                    </button>
                    <button
                        @click="activeTab = 'relationships'"
                        :class="['rounded-md p-2 transition-colors duration-500', activeTab === 'relationships' ? 'bg-purple-100 text-purple-700' : 'bg-white text-gray-400']"
                        title="Relacionamentos"
                    >
                        <LinkIcon class="w-4 h-4" />
                    </button>
                </div>

                <!-- Corpo do painel: conteúdo das abas -->
                <div class="flex flex-1 flex-col gap-6 overflow-y-auto p-4">

                    <!-- Aba Índice (TOC) -->
                    <div v-if="activeTab === 'index'">
                        <div v-if="tocItems.length === 0" class="text-sm text-gray-400 italic">
                            Nenhum título no documento. Adicione títulos (H1, H2, H3) para vê-los aqui.
                        </div>
                        <ul v-else class="space-y-1.5 max-h-[80vh] overflow-y-auto pr-2 custom-scrollbar">
                            <li v-for="item in tocItems" :key="item.id">
                                <button @click="emit('scrollToHeading', item.id)" :class="[
                                    'text-left w-full text-sm hover:text-primary hover:bg-primary/5 transition-colors px-2 py-1 rounded line-clamp-2',
                                    item.level === 1 ? 'font-semibold text-gray-800' : '',
                                    item.level === 2 ? 'font-medium text-gray-600 pl-4' : '',
                                    item.level === 3 ? 'text-gray-500 pl-8 text-xs' : ''
                                ]" :title="item.text">
                                    {{ item.text }}
                                </button>
                            </li>
                        </ul>
                    </div>

                    <!-- Aba Histórico de Versões -->
                    <div v-else-if="activeTab === 'history'">
                        <DocumentVersionHistory :versions="versionHistory" :active-version-id="activeVersionId"
                            @open-version-modal="v => emit('openVersionModal', v)" @save-version="l => emit('saveVersion', l)"
                            @delete-version="v => emit('deleteVersion', v)" @export-version="v => emit('exportVersion', v)" />
                    </div>

                    <!-- Aba Relacionamentos -->
                    <div v-else-if="activeTab === 'relationships'" class="flex flex-col h-full">
                        <div v-if="relationships.length === 0"
                            class="flex flex-col items-center justify-center text-center p-6 bg-white rounded-lg border border-dashed border-gray-300">
                            <div class="w-10 h-10 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                                <Link2Off class="w-5 h-5 text-gray-400" />
                            </div>
                            <h3 class="text-sm font-medium text-gray-900 mb-1">Sem relacionamentos</h3>
                            <p class="text-xs text-gray-500 mb-4">
                                Este documento ainda não possui vínculos com outros itens.
                            </p>
                            <button @click="emit('openRelationshipModal')"
                                class="flex items-center justify-center w-full gap-2 px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors shadow-sm">
                                <Plus class="w-4 h-4" />
                                Adicionar relacionamento
                            </button>
                        </div>

                        <div v-else class="flex flex-col h-full">
                            <!-- Navigator -->
                            <div v-if="groupedRelationships.length > 0"
                                class="flex items-center justify-between bg-white border rounded-lg p-2 mb-3 shadow-sm">
                                <button @click="prevParagraph" :disabled="currentParagraphIndex === 0"
                                    class="p-1 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                    <ChevronLeft class="w-4 h-4 text-gray-600" />
                                </button>
                                <button @click="jumpToCurrentParagraph"
                                    class="text-xs font-medium text-gray-700 hover:text-primary transition-colors hover:underline"
                                    title="Localizar parágrafo no editor">
                                    Parágrafo {{ currentParagraphIndex + 1 }} de {{ groupedRelationships.length }}
                                </button>
                                <button @click="nextParagraph"
                                    :disabled="currentParagraphIndex === groupedRelationships.length - 1"
                                    class="p-1 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                    <ChevronRight class="w-4 h-4 text-gray-600" />
                                </button>
                            </div>

                            <div class="flex-1 overflow-y-auto space-y-3 pr-2 custom-scrollbar">
                                <template v-if="currentGroup">
                                    <div v-for="rel in currentGroup.relationships" :key="rel.id"
                                        @click="emit('openRelationshipViewer', rel)"
                                        :class="[
                                            'rounded-lg p-3 shadow-sm relative group transition-colors cursor-pointer border',
                                            rel.is_pending
                                                ? 'bg-amber-50 border-amber-300 hover:border-amber-400'
                                                : 'bg-white border-gray-200 hover:border-purple-300'
                                        ]">

                                        <div
                                            class="absolute top-2 right-2 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <a :href="route('library.documents.show', rel.related_document_id)" target="_blank"
                                                rel="noopener noreferrer" @click.stop
                                                class="text-gray-400 hover:text-purple-600 transition-colors"
                                                title="Abrir documento relacionado em nova aba">
                                                <ExternalLink class="w-4 h-4" />
                                            </a>
                                            <button @click.stop="openDeleteRelationship(rel)"
                                                class="text-gray-400 hover:text-red-500 transition-colors"
                                                title="Remover Vínculo">
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>

                                        <div class="flex items-start gap-2 mb-2">
                                            <div class="mt-0.5 bg-purple-100 text-purple-700 p-1 rounded">
                                                <LinkIcon class="w-3.5 h-3.5" />
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-[11px] text-gray-500 font-medium">{{ rel.related_document_title }}</p>
                                                <p class="text-sm font-semibold text-gray-900 leading-tight truncate">
                                                    {{ rel.related_paragraph_preview }}
                                                </p>
                                                <p v-if="!rel.related_paragraph_preview"
                                                    class="mt-1 text-xs text-gray-600 leading-snug line-clamp-2">
                                                     Sem prévia do parágrafo relacionado.
                                                </p>

                                            </div>
                                        </div>
                                        <span v-if="rel.is_pending"
                                            class="absolute bottom-2 right-2 w-2.5 h-2.5 rounded-full bg-amber-400 ring-2 ring-amber-200"
                                            title="Relacionamento pendente de revisão" />
                                    </div>
                                </template>
                            </div>

                            <button @click="emit('openRelationshipModal')"
                                class="mt-4 flex items-center justify-center w-full gap-2 px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors shadow-sm shrink-0">
                                <Plus class="w-4 h-4" />
                                Novo relacionamento
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Dialog de confirmação de exclusão (fora do painel colapsável) -->
        <Dialog v-model:open="showDeleteDialog">
            <template #default="{ close }">
                <h2 class="text-lg font-semibold mb-2">Confirmar Exclusão</h2>
                <p class="text-sm text-muted-foreground mb-6">
                    Tem certeza que deseja excluir este relacionamento?
                </p>
                <div class="flex justify-end gap-3">
                    <Button type="button" variant="outline" @click.stop="close">Cancelar</Button>
                    <Button type="button" variant="destructive" @click.stop="submitDeleteRelationship">Excluir</Button>
                </div>
            </template>
        </Dialog>

    </div>
</template>
