<script setup>
import { computed, ref, nextTick } from 'vue';
import { FileText, Image, File, FileSpreadsheet, FileArchive, FileCode, Film, Square, CheckSquare, Info, Star } from 'lucide-vue-next';

const props = defineProps({
    file: Object,
    selected: { type: Boolean, default: false },
    selectionMode: { type: Boolean, default: false },
    favorited: { type: Boolean, default: false },
});

const emit = defineEmits(['rename', 'select', 'info', 'contextmenu', 'preview', 'unfavorite']);

// Inline editing
const isEditing = ref(false);
const editName = ref('');
const inputRef = ref(null);

const startEditing = () => {
    editName.value = props.file.name;
    isEditing.value = true;
    nextTick(() => {
        if (inputRef.value) {
            inputRef.value.focus();
            const dotIndex = editName.value.lastIndexOf('.');
            if (dotIndex > 0) {
                inputRef.value.setSelectionRange(0, dotIndex);
            } else {
                inputRef.value.select();
            }
        }
    });
};

const saveEdit = () => {
    const trimmed = editName.value.trim();
    if (trimmed && trimmed !== props.file.name) {
        emit('rename', trimmed);
    }
    isEditing.value = false;
};

const cancelEdit = () => {
    isEditing.value = false;
};

defineExpose({ startEditing });

const isImage = computed(() => props.file.mime_type?.startsWith('image/'));
const isPdf = computed(() => props.file.mime_type === 'application/pdf');
const isVideo = computed(() => props.file.mime_type?.startsWith('video/'));
const isViewable = computed(() => isImage.value || isPdf.value || isVideo.value);

const hasVisualPreview = computed(() => {
    return (isPdf.value && props.file.preview_url) || (isImage.value && props.file.file_url) || (isVideo.value && props.file.file_url);
});

const previewSrc = computed(() => {
    if (isPdf.value && props.file.preview_url) return props.file.preview_url;
    if (isImage.value && props.file.file_url) return props.file.file_url;
    return null;
});

const fileSize = computed(() => {
    const size = props.file.size;
    if (size < 1024) return size + ' B';
    if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
    return (size / (1024 * 1024)).toFixed(1) + ' MB';
});

const fileIcon = computed(() => {
    const mime = props.file.mime_type || '';
    if (mime === 'application/pdf') return { icon: FileText, color: 'text-red-500' };
    if (mime.startsWith('image/')) return { icon: Image, color: 'text-blue-500' };
    if (mime.startsWith('video/')) return { icon: Film, color: 'text-purple-500' };
    if (mime.includes('spreadsheet') || mime.includes('excel') || mime.includes('csv'))
        return { icon: FileSpreadsheet, color: 'text-green-600' };
    if (mime.includes('zip') || mime.includes('rar') || mime.includes('tar') || mime.includes('compressed'))
        return { icon: FileArchive, color: 'text-amber-600' };
    if (mime.includes('javascript') || mime.includes('json') || mime.includes('html') || mime.includes('xml') || mime.includes('css'))
        return { icon: FileCode, color: 'text-cyan-600' };
    return { icon: File, color: 'text-gray-400' };
});

const handleContextMenu = (e) => {
    e.preventDefault();
    emit('contextmenu', e);
};

const handleClick = () => {
    if (props.selectionMode) {
        emit('select');
    } else if (isViewable.value) {
        emit('preview');
    }
};
</script>

<template>
    <div class="group flex flex-col items-center gap-2 w-[160px] cursor-pointer" :title="file.name"
        @contextmenu="handleContextMenu" @click="handleClick">
        <!-- Preview / Icon Area -->
        <div class="w-full aspect-[3/4] flex items-center justify-center rounded-lg overflow-hidden relative" :class="[
            hasVisualPreview ? 'bg-gray-100' : 'bg-gray-50',
            selected ? 'ring-2 ring-primary ring-offset-1' : ''
        ]">

            <!-- Video Thumbnail Preview -->
            <template v-if="isVideo && file.file_url">
                <video :src="file.file_url + '#t=1'" preload="metadata" muted
                    class="w-full h-full object-cover transition-transform group-hover:scale-105" />
                <!-- Play icon overlay -->
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="w-10 h-10 rounded-full bg-black/50 flex items-center justify-center backdrop-blur-sm">
                        <svg class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z" />
                        </svg>
                    </div>
                </div>
            </template>

            <!-- Visual Preview (PDF or Image) -->
            <img v-else-if="hasVisualPreview" :src="previewSrc"
                class="w-full h-full transition-transform group-hover:scale-105"
                :class="isPdf ? 'object-cover' : 'object-contain'" alt="Preview" />

            <!-- Default Icon (no preview available) -->
            <div v-else class="flex flex-col items-center gap-1">
                <component :is="fileIcon.icon" class="w-12 h-12" :class="fileIcon.color" />
            </div>

            <!-- PDF Badge -->
            <span v-if="isPdf && !selectionMode"
                class="absolute top-2 left-2 bg-red-600 text-white text-[10px] font-bold uppercase px-1.5 py-0.5 rounded shadow-sm leading-none z-10">
                PDF
            </span>

            <!-- Hover: Checkbox (top-left) -->
            <button @click.stop.prevent="emit('select')"
                class="absolute top-1.5 left-1.5 z-20 p-0.5 rounded transition-all"
                :class="selected || selectionMode ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
                title="Selecionar">
                <CheckSquare v-if="selected" class="w-5 h-5 text-primary" />
                <Square v-else class="w-5 h-5 text-gray-500 hover:text-primary bg-white/80 rounded" />
            </button>

            <!-- Hover: Info (top-right) -->
            <button @click.stop.prevent="emit('info')"
                class="absolute top-1.5 right-1.5 z-20 p-0.5 rounded opacity-0 group-hover:opacity-100 transition-all"
                title="Informações">
                <Info class="w-5 h-5 text-gray-500 hover:text-primary bg-white/80 rounded-full" />
            </button>

            <!-- Hover overlay -->
            <div
                class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors rounded-lg pointer-events-none">
            </div>

            <!-- Favorite Star Badge (bottom-left) -->
            <button v-if="favorited" @click.stop.prevent="emit('unfavorite')"
                class="absolute bottom-1.5 left-1.5 z-20 p-0.5 rounded-full transition-all hover:scale-110"
                title="Desfavoritar">
                <Star class="w-4 h-4 text-amber-400 fill-amber-400 drop-shadow-sm" />
            </button>
        </div>

        <!-- Name & Size -->
        <div class="w-full text-center">
            <input v-if="isEditing" ref="inputRef" v-model="editName"
                class="w-full text-sm font-semibold text-gray-700 text-center bg-white border border-blue-400 rounded px-1 py-0.5 outline-none focus:ring-2 focus:ring-blue-300"
                @keydown.enter="saveEdit" @keydown.escape="cancelEdit" @blur="saveEdit" />
            <div v-else class="text-sm font-semibold text-gray-700 truncate w-full leading-tight"
                @dblclick="startEditing">
                {{ file.name }}
            </div>
            <div class="text-[11px] text-gray-400 font-medium mt-0.5">{{ fileSize }}</div>
        </div>
    </div>
</template>
