<script setup>
import { cn } from '@/lib/utils'

defineProps({
    class: { type: String, default: '' },
})
</script>

<template>
    <table :class="cn('w-full caption-bottom text-sm', props.class)">
        <slot />
    </table>
</template>
