<script setup>
import { ref, watch, computed } from 'vue'
import { Upload, CheckCircle, XCircle, X, Loader2 } from 'lucide-vue-next'

const props = defineProps({
    show: { type: Boolean, default: false },
    status: { type: String, default: 'idle', validator: (v) => ['idle', 'uploading', 'success', 'error'].includes(v) },
    progress: { type: Number, default: 0 },
    message: { type: String, default: '' },
    fileName: { type: String, default: '' },
    duration: { type: Number, default: 4000 },
})

const emit = defineEmits(['update:show', 'close'])

const visible = ref(false)
let timer = null

watch(() => props.show, (val) => {
    if (val) {
        visible.value = true
        clearTimeout(timer)
        // Auto-dismiss only on success
        if (props.status === 'success') {
            timer = setTimeout(close, props.duration)
        }
    } else {
        visible.value = false
    }
})

watch(() => props.status, (val) => {
    if (val === 'success') {
        clearTimeout(timer)
        timer = setTimeout(close, props.duration)
    }
})

function close() {
    visible.value = false
    emit('update:show', false)
    emit('close')
    clearTimeout(timer)
}

const statusConfig = computed(() => {
    switch (props.status) {
        case 'uploading':
            return {
                icon: Loader2,
                bgClass: 'border-blue-500/30 bg-blue-50',
                iconClass: 'text-blue-500 animate-spin',
                textClass: 'text-blue-800',
                barClass: 'bg-blue-500',
            }
        case 'success':
            return {
                icon: CheckCircle,
                bgClass: 'border-emerald-500/30 bg-emerald-50',
                iconClass: 'text-emerald-500',
                textClass: 'text-emerald-800',
                barClass: 'bg-emerald-500',
            }
        case 'error':
            return {
                icon: XCircle,
                bgClass: 'border-red-500/30 bg-red-50',
                iconClass: 'text-red-500',
                textClass: 'text-red-800',
                barClass: 'bg-red-500',
            }
        default:
            return {
                icon: Upload,
                bgClass: 'border-gray-300 bg-white',
                iconClass: 'text-gray-400',
                textClass: 'text-gray-700',
                barClass: 'bg-gray-400',
            }
    }
})
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-4 opacity-0">
            <div v-if="visible"
                class="fixed bottom-6 right-6 z-[100] w-[360px] rounded-xl border shadow-xl overflow-hidden"
                :class="statusConfig.bgClass">
                <!-- Content -->
                <div class="flex items-start gap-3 px-4 pt-3 pb-2">
                    <component :is="statusConfig.icon" class="h-5 w-5 shrink-0 mt-0.5"
                        :class="statusConfig.iconClass" />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold" :class="statusConfig.textClass">{{ message }}</p>
                        <p v-if="fileName" class="text-xs text-gray-500 truncate mt-0.5">{{ fileName }}</p>
                    </div>
                    <button v-if="status !== 'uploading'" @click="close"
                        class="rounded-md p-0.5 opacity-60 hover:opacity-100 transition-opacity shrink-0">
                        <X class="h-4 w-4" :class="statusConfig.textClass" />
                    </button>
                </div>

                <!-- Progress Bar -->
                <div v-if="status === 'uploading'" class="px-4 pb-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[11px] text-gray-500">Progresso</span>
                        <span class="text-[11px] font-semibold text-blue-600">{{ Math.round(progress) }}%</span>
                    </div>
                    <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-300 ease-out"
                            :class="statusConfig.barClass" :style="{ width: progress + '%' }"></div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
