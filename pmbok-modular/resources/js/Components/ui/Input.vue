<script setup>
import { cn } from '@/lib/utils'

defineProps({
    class: { type: String, default: '' },
    modelValue: { type: String, default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    id: { type: String, default: '' },
    disabled: Boolean,
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
    <input
        :type="type"
        :value="modelValue"
        @input="emit('update:modelValue', $event.target.value)"
        :placeholder="placeholder"
        :id="id"
        :disabled="disabled"
        :class="cn(
            'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
            props.class
        )"
    />
</template>
