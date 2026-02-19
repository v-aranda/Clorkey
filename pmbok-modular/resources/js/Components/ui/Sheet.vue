<script setup>
import { ref, watch } from 'vue'
import { X } from 'lucide-vue-next'

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    side: { type: String, default: 'right', validator: (v) => ['left', 'right'].includes(v) },
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
        <!-- Overlay -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 bg-black/50"
                @click="close"
            />
        </Transition>

        <!-- Panel -->
        <div
            v-if="isOpen"
            class="fixed inset-y-0 z-50 flex w-full max-w-md flex-col border-l bg-white shadow-xl transition-transform duration-300 ease-out"
            :class="[
                side === 'right' ? 'right-0' : 'left-0 border-l-0 border-r',
            ]"
        >
            <!-- Header -->
            <div class="flex items-center justify-between border-b px-6 py-4 shrink-0">
                <h2 class="text-lg font-semibold text-gray-900">{{ title }}</h2>
                <button
                    @click="close"
                    class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto px-6 py-6">
                <slot :close="close" />
            </div>
        </div>
    </Teleport>
</template>
