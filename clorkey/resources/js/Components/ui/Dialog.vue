<script setup>
import { ref, watch } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps({
    open: { type: Boolean, default: false },
    class: { type: String, default: '' },
})

const emit = defineEmits(['update:open'])

const isOpen = ref(props.open)

watch(() => props.open, (val) => { isOpen.value = val })
watch(isOpen, (val) => { emit('update:open', val) })

function close() {
    isOpen.value = false
}
</script>

<template>
    <Teleport to="body">
        <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center">
            <!-- Overlay -->
            <div
                class="fixed inset-0 bg-black/80 transition-opacity"
                @click="close"
            />
            <!-- Content -->
            <div
                :class="cn(
                    'relative z-50 w-full max-w-lg rounded-lg border bg-background p-6 shadow-lg',
                    props.class
                )"
            >
                <!-- Close button -->
                <button
                    @click="close"
                    class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18" /><path d="m6 6 12 12" />
                    </svg>
                </button>
                <slot :close="close" />
            </div>
        </div>
    </Teleport>
</template>
