<script setup>
import { DropdownMenuTrigger } from 'radix-vue'
import { cn } from '@/lib/utils'

const props = defineProps({
    disabled: { type: Boolean, required: false },
    asChild: { type: Boolean, required: false },
    as: { type: null, required: false },
    class: { type: null, required: false },
})
</script>

<template>
    <DropdownMenuTrigger v-bind="props" :class="cn('outline-none', props.class)">
        <slot />
    </DropdownMenuTrigger>
</template>
