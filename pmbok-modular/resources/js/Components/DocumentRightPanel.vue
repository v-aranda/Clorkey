<script setup>
import { ref } from 'vue';
import { ListTree, History, X } from 'lucide-vue-next';
import DocumentVersionHistory from '@/Components/DocumentVersionHistory.vue';

const props = defineProps({
    show: Boolean,
    tocItems: Array,
    versionHistory: Array,
    activeVersionId: [Number, String],
});

const emit = defineEmits([
    'update:show',
    'scrollToHeading',
    'openVersionModal',
    'saveVersion',
    'deleteVersion',
    'exportVersion'
]);

const activeTab = ref('index');
</script>

<template>
    <div :class="[
        'transition-all duration-300 ease-in-out border-l bg-gray-50/50 min-h-[calc(100vh-65px)] sticky top-0 self-start',
        show ? 'w-64 opacity-100' : 'w-0 opacity-0 overflow-hidden border-l-0'
    ]">
        <div class="p-4 sticky top-4 w-64" v-if="show">
            <div class="flex items-center gap-1 mb-4">
                <button @click="activeTab = 'index'"
                    :class="['p-2 rounded-md transition', activeTab === 'index' ? 'bg-primary/10 text-primary' : 'text-gray-400 hover:bg-gray-100 hover:text-gray-700']"
                    title="Índice">
                    <ListTree class="w-4 h-4" />
                </button>
                <button @click="activeTab = 'history'"
                    :class="['p-2 rounded-md transition', activeTab === 'history' ? 'bg-primary/10 text-primary' : 'text-gray-400 hover:bg-gray-100 hover:text-gray-700']"
                    title="Histórico de Versões">
                    <History class="w-4 h-4" />
                </button>
                <button @click="emit('update:show', false)"
                    class="ml-auto p-2 rounded-md text-gray-400 hover:bg-gray-200 hover:text-gray-700 transition"
                    title="Fechar">
                    <X class="w-4 h-4" />
                </button>
            </div>

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
            <div v-else-if="activeTab === 'history'">
                <DocumentVersionHistory :versions="versionHistory" :active-version-id="activeVersionId"
                    @open-version-modal="v => emit('openVersionModal', v)" @save-version="l => emit('saveVersion', l)"
                    @delete-version="v => emit('deleteVersion', v)" @export-version="v => emit('exportVersion', v)" />
            </div>
        </div>
    </div>
</template>
