<script setup>
import { computed, ref, nextTick } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Square, CheckSquare, Info, Star } from 'lucide-vue-next';

const props = defineProps({
    name: String,
    href: String,
    color: {
        type: String,
        default: 'yellow'
    },
    selected: { type: Boolean, default: false },
    selectionMode: { type: Boolean, default: false },
    favorited: { type: Boolean, default: false },
});

const emit = defineEmits(['rename', 'select', 'info', 'contextmenu', 'unfavorite']);

const isEditing = ref(false);
const tempName = ref(props.name);
const inputRef = ref(null);

const startEditing = () => {
    tempName.value = props.name;
    isEditing.value = true;
    nextTick(() => {
        inputRef.value?.focus();
        inputRef.value?.select();
    });
};

defineExpose({ startEditing });

const save = () => {
    if (!isEditing.value) return;

    isEditing.value = false;
    if (tempName.value && tempName.value.trim() !== props.name) {
        emit('rename', tempName.value.trim());
    } else {
        tempName.value = props.name;
    }
};

const colorClasses = computed(() => {
    const colors = {
        blue: { back: 'bg-blue-200', front: 'bg-blue-500', tab: 'bg-blue-300' },
        yellow: { back: 'bg-amber-200', front: 'bg-amber-400', tab: 'bg-amber-300' },
        purple: { back: 'bg-purple-200', front: 'bg-purple-500', tab: 'bg-purple-300' },
        gray: { back: 'bg-gray-200', front: 'bg-gray-400', tab: 'bg-gray-300' },
    };
    return colors[props.color] || colors.yellow;
});

const handleContextMenu = (e) => {
    e.preventDefault();
    emit('contextmenu', e);
};
</script>

<template>
    <div class="group flex flex-col items-center gap-2 w-[160px]" :title="name" @contextmenu="handleContextMenu">
        <!-- Folder Icon (Navigates) -->
        <div class="relative w-32 h-24">
            <Link :href="href"
                class="relative w-32 h-24 transition-transform group-hover:-translate-y-1 block outline-none focus:scale-105">
                <!-- Back plate -->
                <div class="absolute inset-0 rounded-lg shadow-sm" :class="colorClasses.back"></div>

                <!-- Tab -->
                <div class="absolute -top-3 left-0 w-12 h-4 rounded-t-md" :class="colorClasses.tab"></div>

                <!-- Paper -->
                <div
                    class="absolute top-1 left-2 right-2 h-[90%] bg-white shadow-sm rounded-sm opacity-60 transform scale-[0.85] origin-bottom">
                </div>

                <!-- Front plate -->
                <div class="absolute bottom-0 left-0 right-0 h-[85%] rounded-b-lg rounded-tr-lg shadow-md flex items-center justify-center transition-colors"
                    :class="[colorClasses.front, selected ? 'ring-2 ring-primary ring-offset-1' : '']">
                    <div class="w-1/3 h-1 bg-white/20 rounded-full mb-4"></div>
                </div>
            </Link>

            <!-- Hover: Checkbox (top-left) -->
            <button @click.stop.prevent="emit('select')"
                class="absolute -top-3 left-0 z-20 p-0.5 rounded transition-all"
                :class="selected || selectionMode ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
                title="Selecionar">
                <CheckSquare v-if="selected" class="w-5 h-5 text-primary" />
                <Square v-else class="w-5 h-5 text-gray-500 hover:text-primary bg-white/80 rounded" />
            </button>

            <!-- Hover: Info (top-right) -->
            <button @click.stop.prevent="emit('info')"
                class="absolute -top-3 right-0 z-20 p-0.5 rounded opacity-0 group-hover:opacity-100 transition-all"
                title="Informações">
                <Info class="w-5 h-5 text-gray-500 hover:text-primary bg-white/80 rounded-full" />
            </button>

            <!-- Favorite Star Badge (bottom-left) -->
            <button v-if="favorited" @click.stop.prevent="emit('unfavorite')"
                class="absolute bottom-1 left-0 z-20 p-0.5 rounded-full transition-all hover:scale-110"
                title="Desfavoritar">
                <Star class="w-4 h-4 text-white fill-white drop-shadow-md" />
            </button>
        </div>

        <!-- Name Label (Double-Click to Edit) -->
        <div v-if="!isEditing" @dblclick.stop.prevent="startEditing"
            class="text-xs font-semibold text-gray-700 text-center line-clamp-2 w-full leading-tight group-hover:text-primary transition-colors cursor-default border border-transparent rounded px-1">
            {{ name }}
        </div>
        <input v-else ref="inputRef" v-model="tempName" @blur="save" @keydown.enter="save"
            @keydown.esc="isEditing = false; tempName = name" @click.stop
            class="w-full text-xs font-semibold text-center leading-tight border border-primary rounded px-1 py-0.5 focus:outline-none focus:ring-1 focus:ring-primary bg-white shadow-sm" />
    </div>
</template>
