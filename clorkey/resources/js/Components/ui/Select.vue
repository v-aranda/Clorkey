<script setup>
import { cn } from '@/lib/utils'

defineProps({
    class: { type: String, default: '' },
    modelValue: { type: String, default: '' },
    id: { type: String, default: '' },
    disabled: Boolean,
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
    <select
        :value="modelValue"
        @change="emit('update:modelValue', $event.target.value)"
        :id="id"
        :disabled="disabled"
        :class="cn(
            'flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
            props.class
        )"
    >
        <slot />
    </select>
</template>
