<script setup>
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';

defineProps({
    open: { type: Boolean, default: false },
    entry: { type: Object, default: null },
});

const emit = defineEmits(['update:open']);
</script>

<template>
    <Dialog :open="open" class="max-w-3xl p-0" @update:open="emit('update:open', $event)">
        <template #default="{ close }">
            <div class="border-b px-6 py-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">Visualização</p>
                <h2 class="text-lg font-semibold text-gray-900">
                    Registro de {{ entry?.title || '-' }}
                </h2>
                <p class="text-sm text-muted-foreground">{{ entry?.weekday || '-' }}</p>
            </div>

            <div class="max-h-[65vh] overflow-auto px-6 py-4">
                <div
                    v-if="entry?.content"
                    class="prose prose-sm max-w-none text-gray-700"
                    v-html="entry.content"
                />
                <p v-else class="text-sm text-gray-500">Sem conteúdo para exibir.</p>
            </div>

            <div class="flex justify-end border-t px-6 py-4">
                <Button type="button" variant="outline" @click="close">Fechar</Button>
            </div>
        </template>
    </Dialog>
</template>
