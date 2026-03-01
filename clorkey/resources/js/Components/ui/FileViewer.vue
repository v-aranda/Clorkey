<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { X, Download, ZoomIn, ZoomOut, RotateCw, Maximize2, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { isImageFile, isPdfFile, isVideoFile } from '@/lib/fileType';

const props = defineProps({
    file: { type: Object, default: null },
    files: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'navigate']);

const isImage = computed(() => isImageFile(props.file));
const isPdf = computed(() => isPdfFile(props.file));
const isVideo = computed(() => isVideoFile(props.file));

// Image zoom & transform
const scale = ref(1);
const rotation = ref(0);
const pdfLoading = ref(true);

const viewerSrc = computed(() => {
    if (!props.file) return null;
    if (isImage.value) return props.file.file_url;
    if (isPdf.value) return props.file.file_url;
    if (isVideo.value) return props.file.file_url;
    return null;
});

// File navigation
const currentIndex = computed(() => {
    if (!props.file || props.files.length === 0) return -1;
    return props.files.findIndex(f => f.id === props.file.id);
});

const hasPrev = computed(() => currentIndex.value > 0);
const hasNext = computed(() => currentIndex.value < props.files.length - 1 && currentIndex.value >= 0);

const navigatePrev = () => {
    if (hasPrev.value) {
        resetTransform();
        emit('navigate', props.files[currentIndex.value - 1]);
    }
};

const navigateNext = () => {
    if (hasNext.value) {
        resetTransform();
        emit('navigate', props.files[currentIndex.value + 1]);
    }
};

// Transform controls
const zoomIn = () => { scale.value = Math.min(scale.value + 0.25, 5); };
const zoomOut = () => { scale.value = Math.max(scale.value - 0.25, 0.25); };
const rotate = () => { rotation.value = (rotation.value + 90) % 360; };
const resetTransform = () => { scale.value = 1; rotation.value = 0; };

const imageStyle = computed(() => ({
    transform: `scale(${scale.value}) rotate(${rotation.value}deg)`,
    transition: 'transform 0.2s ease',
}));

// Download
const downloadFile = () => {
    if (!props.file) return;
    window.open(route('library.files.download', props.file.id), '_blank');
};

// Keyboard shortcuts
const handleKeydown = (e) => {
    switch (e.key) {
        case 'Escape':
            emit('close');
            break;
        case 'ArrowLeft':
            navigatePrev();
            break;
        case 'ArrowRight':
            navigateNext();
            break;
        case '+':
        case '=':
            zoomIn();
            break;
        case '-':
            zoomOut();
            break;
    }
};

// Mouse wheel zoom
const handleWheel = (e) => {
    e.preventDefault();
    if (e.deltaY < 0) {
        zoomIn();
    } else {
        zoomOut();
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
});

watch(() => props.file, () => {
    pdfLoading.value = true;
});

// Counter label
const counterLabel = computed(() => {
    if (currentIndex.value < 0 || props.files.length === 0) return '';
    return `${currentIndex.value + 1} / ${props.files.length}`;
});
</script>

<template>
    <Teleport to="body">
        <div v-if="file" class="fixed inset-0 z-[100] flex flex-col bg-black/95">
            <!-- Top Bar -->
            <div
                class="flex items-center justify-between px-4 py-3 bg-black/60 backdrop-blur-sm text-white shrink-0 z-10">
                <div class="flex items-center gap-3 min-w-0">
                    <h3 class="text-sm font-medium truncate max-w-[400px]">{{ file.name }}</h3>
                    <span v-if="counterLabel" class="text-xs text-gray-400">{{ counterLabel }}</span>
                </div>

                <div class="flex items-center gap-1">
                    <!-- Image-only controls -->
                    <template v-if="isImage">
                        <button @click="zoomOut" class="p-2 hover:bg-white/10 rounded-lg transition-colors"
                            title="Diminuir zoom (-)">
                            <ZoomOut class="w-5 h-5" />
                        </button>
                        <span class="text-xs text-gray-400 w-12 text-center">{{ Math.round(scale * 100) }}%</span>
                        <button @click="zoomIn" class="p-2 hover:bg-white/10 rounded-lg transition-colors"
                            title="Aumentar zoom (+)">
                            <ZoomIn class="w-5 h-5" />
                        </button>
                        <button @click="rotate" class="p-2 hover:bg-white/10 rounded-lg transition-colors"
                            title="Rotacionar">
                            <RotateCw class="w-5 h-5" />
                        </button>
                        <button @click="resetTransform" class="p-2 hover:bg-white/10 rounded-lg transition-colors"
                            title="Resetar">
                            <Maximize2 class="w-5 h-5" />
                        </button>
                        <div class="w-px h-6 bg-white/20 mx-1"></div>
                    </template>

                    <button @click="downloadFile" class="p-2 hover:bg-white/10 rounded-lg transition-colors"
                        title="Baixar">
                        <Download class="w-5 h-5" />
                    </button>
                    <div class="w-px h-6 bg-white/20 mx-1"></div>
                    <button @click="emit('close')" class="p-2 hover:bg-white/10 rounded-lg transition-colors"
                        title="Fechar (Esc)">
                        <X class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-1 relative overflow-hidden flex items-center justify-center">
                <!-- Navigation Arrows -->
                <button v-if="hasPrev" @click="navigatePrev"
                    class="absolute left-4 z-10 p-3 rounded-full bg-black/40 hover:bg-black/60 text-white transition-colors backdrop-blur-sm">
                    <ChevronLeft class="w-6 h-6" />
                </button>
                <button v-if="hasNext" @click="navigateNext"
                    class="absolute right-4 z-10 p-3 rounded-full bg-black/40 hover:bg-black/60 text-white transition-colors backdrop-blur-sm">
                    <ChevronRight class="w-6 h-6" />
                </button>

                <!-- Image Viewer -->
                <div v-if="isImage" class="w-full h-full flex items-center justify-center overflow-auto"
                    @wheel="handleWheel">
                    <img :src="viewerSrc" :style="imageStyle" class="max-w-full max-h-full object-contain select-none"
                        draggable="false" alt="Visualização" />
                </div>

                <!-- PDF Viewer -->
                <div v-else-if="isPdf" class="w-full h-full flex items-center justify-center">
                    <div v-if="pdfLoading" class="text-white text-sm">Carregando PDF...</div>
                    <iframe :src="viewerSrc + '#toolbar=1&navpanes=0'" class="w-full h-full border-0 bg-white"
                        @load="pdfLoading = false" allowfullscreen />
                </div>

                <!-- Video Player -->
                <div v-else-if="isVideo" class="w-full h-full flex items-center justify-center p-8">
                    <video :key="viewerSrc" :src="viewerSrc" controls autoplay
                        class="max-w-full max-h-full rounded-lg shadow-2xl" :type="file.mime_type">
                        Seu navegador não suporta a reprodução deste vídeo.
                    </video>
                </div>

                <!-- Unsupported type -->
                <div v-else class="text-center text-gray-400">
                    <p class="text-lg">Pré-visualização não disponível</p>
                    <p class="text-sm mt-2">Faça o download para visualizar este arquivo</p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
