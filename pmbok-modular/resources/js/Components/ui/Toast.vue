<script setup>
import { ref, watch, onMounted } from 'vue'
import { CheckCircle, XCircle, X, Info } from 'lucide-vue-next'

const props = defineProps({
    message: { type: String, default: '' },
    type: { type: String, default: 'success', validator: (v) => ['success', 'error', 'info'].includes(v) },
    duration: { type: Number, default: 4000 },
    show: { type: Boolean, default: false },
})

const emit = defineEmits(['update:show'])

const visible = ref(false)
let timer = null

watch(() => props.show, (val) => {
    if (val) {
        visible.value = true
        clearTimeout(timer)
        timer = setTimeout(() => {
            visible.value = false
            emit('update:show', false)
        }, props.duration)
    } else {
        visible.value = false
    }
})

function close() {
    visible.value = false
    emit('update:show', false)
    clearTimeout(timer)
}

const icons = { success: CheckCircle, error: XCircle, info: Info }
const colors = {
    success: 'border-emerald-500/30 bg-emerald-50 text-emerald-800',
    error: 'border-red-500/30 bg-red-50 text-red-800',
    info: 'border-blue-500/30 bg-blue-50 text-blue-800',
}
const iconColors = {
    success: 'text-emerald-500',
    error: 'text-red-500',
    info: 'text-blue-500',
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-4 opacity-0"
        >
            <div
                v-if="visible"
                class="fixed bottom-6 right-6 z-[100] flex items-center gap-3 rounded-lg border px-4 py-3 shadow-lg"
                :class="colors[type]"
            >
                <component :is="icons[type]" class="h-5 w-5 shrink-0" :class="iconColors[type]" />
                <p class="text-sm font-medium">{{ message }}</p>
                <button @click="close" class="ml-2 rounded-md p-0.5 opacity-60 hover:opacity-100 transition-opacity">
                    <X class="h-4 w-4" />
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
