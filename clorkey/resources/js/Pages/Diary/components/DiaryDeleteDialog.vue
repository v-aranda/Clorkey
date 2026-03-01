<script setup>
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';

defineProps({
    open: { type: Boolean, default: false },
    entry: { type: Object, default: null },
});

const emit = defineEmits(['update:open', 'confirm']);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <template #default="{ close }">
            <h2 class="text-lg font-semibold mb-2">Confirmar Exclusão</h2>
            <p class="text-sm text-muted-foreground mb-6">
                Tem certeza que deseja apagar o registro do dia
                <strong>{{ entry?.title }}</strong>?
            </p>
            <div class="flex justify-end gap-3">
                <Button type="button" variant="outline" @click="close">Cancelar</Button>
                <Button variant="destructive" @click="emit('confirm')">Apagar</Button>
            </div>
        </template>
    </Dialog>
</template>
