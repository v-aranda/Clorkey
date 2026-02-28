<script setup>
import { computed } from 'vue'

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['xs', 'sm', 'md', 'lg', 'xl'].includes(v),
    },
})

const initials = computed(() => {
    if (!props.user?.name) return '?'
    const parts = props.user.name.trim().split(/\s+/)
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase()
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase()
})

const colors = [
    'bg-indigo-500',
    'bg-emerald-500',
    'bg-amber-500',
    'bg-rose-500',
    'bg-cyan-500',
    'bg-violet-500',
    'bg-orange-500',
    'bg-teal-500',
]

const bgColor = computed(() => {
    if (!props.user?.name) return colors[0]
    let hash = 0
    for (let i = 0; i < props.user.name.length; i++) {
        hash = props.user.name.charCodeAt(i) + ((hash << 5) - hash)
    }
    return colors[Math.abs(hash) % colors.length]
})

const sizeClasses = computed(() => {
    const map = {
        xs: 'h-6 w-6 text-[10px]',
        sm: 'h-8 w-8 text-xs',
        md: 'h-10 w-10 text-sm',
        lg: 'h-16 w-16 text-xl',
        xl: 'h-24 w-24 text-3xl',
    }
    return map[props.size]
})
</script>

<template>
    <div class="relative shrink-0">
        <img
            v-if="user?.avatar_url"
            :src="user.avatar_url"
            :alt="user.name"
            class="rounded-full object-cover"
            :class="sizeClasses"
        />
        <div
            v-else
            class="flex items-center justify-center rounded-full font-bold text-white uppercase"
            :class="[sizeClasses, bgColor]"
        >
            {{ initials }}
        </div>
    </div>
</template>
