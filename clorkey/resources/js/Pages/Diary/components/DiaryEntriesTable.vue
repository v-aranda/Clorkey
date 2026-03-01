<script setup>
import Button from '@/Components/ui/Button.vue';
import { Eye, FileText, Trash2 } from 'lucide-vue-next';

defineProps({
    entries: { type: Object, required: true },
    status: { type: String, default: 'active' },
});

const emit = defineEmits(['delete-entry', 'go-page', 'view-entry', 'restore-entry']);
</script>

<template>
    <div class="rounded-lg border bg-white shadow-sm">
        <table class="w-full caption-bottom text-sm">
            <thead class="[&_tr]:border-b">
                <tr class="border-b transition-colors">
                    <th class="h-10 px-4 align-middle font-medium text-muted-foreground text-left">Registro</th>
                    <th class="h-10 px-4 align-middle font-medium text-muted-foreground text-left">Dia</th>
                    <th v-if="status === 'inactive'" class="h-10 px-4 align-middle font-medium text-muted-foreground text-left">Excluído em</th>
                    <th class="h-10 px-4 align-middle font-medium text-muted-foreground text-right">Ações</th>
                </tr>
            </thead>

            <tbody class="[&_tr:last-child]:border-0">
                <tr
                    v-for="entry in entries.data"
                    :key="entry.id"
                    class="border-b transition-colors hover:bg-muted/50"
                >
                    <td class="p-4 align-middle font-medium">
                        <div class="flex items-center gap-2">
                            <FileText class="h-4 w-4 text-gray-500" />
                            <span>{{ entry.title }}</span>
                        </div>
                    </td>
                    <td class="p-4 align-middle text-muted-foreground">{{ entry.weekday }}</td>
                    <td v-if="status === 'inactive'" class="p-4 align-middle text-muted-foreground">{{ entry.deleted_at || '-' }}</td>
                    <td class="p-4 align-middle text-right">
                        <div class="flex items-center justify-end gap-1">
                            <Button variant="ghost" size="icon" @click="emit('view-entry', entry)" title="Visualizar registro">
                                <Eye class="h-4 w-4 text-gray-600" />
                            </Button>
                            <Button
                                v-if="status === 'active'"
                                variant="ghost"
                                size="icon"
                                @click="emit('delete-entry', entry)"
                                title="Apagar registro"
                            >
                                <Trash2 class="h-4 w-4 text-red-500" />
                            </Button>
                            <Button
                                v-else
                                variant="ghost"
                                size="sm"
                                class="text-emerald-700"
                                @click="emit('restore-entry', entry)"
                                title="Restaurar registro"
                            >
                                Restaurar
                            </Button>
                        </div>
                    </td>
                </tr>

                <tr v-if="entries.data.length === 0">
                    <td :colspan="status === 'inactive' ? 4 : 3" class="p-8 text-center text-muted-foreground">
                        {{ status === 'active' ? 'Nenhum registro ativo encontrado.' : 'Nenhum registro inativo encontrado.' }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="flex items-center justify-between border-t px-4 py-3">
            <p class="text-sm text-muted-foreground">
                Mostrando {{ entries.from || 0 }} a {{ entries.to || 0 }} de {{ entries.total || 0 }} resultados
            </p>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="!entries.prev_page_url" @click="emit('go-page', entries.prev_page_url)">Anterior</Button>
                <Button variant="outline" size="sm" :disabled="!entries.next_page_url" @click="emit('go-page', entries.next_page_url)">Próximo</Button>
            </div>
        </div>
    </div>
</template>
