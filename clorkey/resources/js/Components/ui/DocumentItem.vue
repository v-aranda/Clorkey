<script setup>
import { computed, ref, nextTick } from 'vue';
import { FileText, Image, File, FileSpreadsheet, FileArchive, FileCode, Film, Square, CheckSquare, Info, Star } from 'lucide-vue-next';

const props = defineProps({
    document: Object,
    selected: { type: Boolean, default: false },
    selectionMode: { type: Boolean, default: false },
    favorited: { type: Boolean, default: false },
});

const emit = defineEmits(['rename', 'select', 'info', 'contextmenu', 'preview', 'unfavorite']);

const isEditing = ref(false);
const editName = ref('');
const inputRef = ref(null);

const startEditing = () => {
    editName.value = props.document.title;
    isEditing.value = true;
    nextTick(() => {
        if (inputRef.value) {
            inputRef.value.focus();
            inputRef.value.select();
        }
    });
};

const saveEdit = () => {
    const trimmed = editName.value.trim();
    if (trimmed && trimmed !== props.document.title) {
        emit('rename', trimmed);
    }
    isEditing.value = false;
};

const cancelEdit = () => {
    isEditing.value = false;
};

defineExpose({ startEditing });

const updatedAt = computed(() => {
    if (!props.document.updated_at) return '—';
    return new Date(props.document.updated_at).toLocaleDateString();
});

const handleContextMenu = (e) => {
    e.preventDefault();
    emit('contextmenu', e);
};

const handleClick = () => {
    if (props.selectionMode) {
        emit('select');
    } else {
        emit('preview'); // Opens the editor
    }
};
</script>

<template>
    <div class="group flex flex-col items-center gap-2 w-[160px] cursor-pointer" :title="document.title"
        @contextmenu="handleContextMenu" @click="handleClick">
        <!-- Preview / Icon Area -->
        <div class="w-full aspect-[3/4] flex items-center justify-center rounded-lg overflow-hidden relative bg-gray-50"
            :class="[
                selected ? 'ring-2 ring-primary ring-offset-1' : ''
            ]">

            <!-- Default Icon -->
            <div class="flex flex-col items-center gap-1">
                <FileText class="w-12 h-12 text-indigo-500" />
            </div>

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
                {{ document.title }}
            </div>
            <div class="text-[11px] text-gray-400 font-medium mt-0.5">Modificado em {{ updatedAt }}</div>
        </div>
    </div>
</template>
