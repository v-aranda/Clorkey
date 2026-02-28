<template>
    <div class="flex flex-col gap-3">

        <!-- Save version -->
        <div v-if="!showSaveInput">
            <button @click="showSaveInput = true"
                class="flex items-center gap-1.5 w-full px-3 py-1.5 text-xs font-medium text-primary bg-primary/5 hover:bg-primary/10 rounded-lg transition-colors border border-primary/20">
                <Plus class="w-3.5 h-3.5" />
                Salvar versão atual
            </button>
        </div>
        <div v-else class="flex flex-col gap-1.5">
            <input v-model="newVersionLabel" placeholder="Nome da versão (opcional)"
                class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 w-full focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                @keydown.enter="confirmSave" @keydown.escape="showSaveInput = false; newVersionLabel = ''" />
            <div class="flex gap-1.5">
                <button @click="confirmSave"
                    class="flex-1 px-2 py-1 text-xs font-medium text-white bg-primary hover:bg-primary/90 rounded-md transition-colors">
                    Salvar
                </button>
                <button @click="showSaveInput = false; newVersionLabel = ''"
                    class="px-2 py-1 text-xs text-gray-500 hover:bg-gray-100 rounded-md transition-colors">
                    Cancelar
                </button>
            </div>
        </div>

        <!-- Version list -->
        <div v-if="versions.length === 0" class="text-xs text-gray-400 italic text-center py-4">
            Nenhuma versão salva ainda.
        </div>

        <ul v-else class="flex flex-col gap-1 max-h-[60vh] overflow-y-auto pr-1 custom-scrollbar">
            <li v-for="version in versions" :key="version.id" @click="$emit('open-version-modal', version)" :class="activeVersionId === version.id
                ? 'border-primary/40 bg-primary/5'
                : 'border-gray-100 bg-white hover:border-gray-200 hover:bg-gray-50'"
                class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors">
                <div class="flex flex-col min-w-0 flex-1">
                    <span class="text-xs font-medium text-gray-700 truncate">
                        {{ version.label || 'Sem nome' }}
                    </span>
                    <span class="text-[10px] text-gray-400">
                        {{ version.user?.name || '' }}
                        <template v-if="version.created_at"> · {{ formatDate(version.created_at) }}</template>
                    </span>
                </div>
                <button @click.stop="$emit('export-version', version)"
                    class="flex-shrink-0 p-1 rounded-md text-gray-400 hover:text-blue-500 hover:bg-blue-50 transition-colors"
                    title="Exportar PDF">
                    <Download class="w-3.5 h-3.5" />
                </button>
                <button @click.stop="openDelete(version)"
                    class="flex-shrink-0 p-1 rounded-md text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                    title="Excluir versão">
                    <Trash2 class="w-3.5 h-3.5" />
                </button>
            </li>
        </ul>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <template #default="{ close }">
                <h2 class="text-lg font-semibold mb-2">Confirmar Exclusão</h2>
                <p class="text-sm text-muted-foreground mb-6">
                    Tem certeza que deseja excluir a versão
                    <strong>{{ versionToDelete?.label || 'Sem nome' }}</strong>?
                </p>
                <div class="flex justify-end gap-3">
                    <Button type="button" variant="outline" @click="close">Cancelar</Button>
                    <Button variant="destructive" @click="submitDelete">Excluir</Button>
                </div>
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Plus, Trash2, Download } from 'lucide-vue-next';
import Dialog from '@/Components/ui/Dialog.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    versions: { type: Array, default: () => [] },
    activeVersionId: { type: Number, default: null },
});

const emit = defineEmits(['open-version-modal', 'save-version', 'delete-version', 'export-version']);

const showSaveInput = ref(false);
const newVersionLabel = ref('');
const showDeleteDialog = ref(false);
const versionToDelete = ref(null);

function confirmSave() {
    emit('save-version', newVersionLabel.value.trim() || null);
    showSaveInput.value = false;
    newVersionLabel.value = '';
}

function openDelete(version) {
    versionToDelete.value = version;
    showDeleteDialog.value = true;
}

function submitDelete() {
    emit('delete-version', versionToDelete.value);
    showDeleteDialog.value = false;
    versionToDelete.value = null;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', year: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}
</script>
