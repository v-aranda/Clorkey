<script setup>
import { Search } from 'lucide-vue-next';

const props = defineProps({
    search: { type: String, default: '' },
    status: { type: String, default: 'active' },
});

const emit = defineEmits(['update:search', 'update:status']);
</script>

<template>
    <div class="flex items-center gap-3">
        <div class="flex bg-gray-100 p-1 rounded-lg">
            <button
                @click="emit('update:status', 'active')"
                class="px-3 py-1 text-sm font-medium rounded-md transition-all"
                :class="status === 'active' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'"
            >
                Ativos
            </button>
            <button
                @click="emit('update:status', 'inactive')"
                class="px-3 py-1 text-sm font-medium rounded-md transition-all"
                :class="status === 'inactive' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'"
            >
                Inativos
            </button>
        </div>

        <div class="relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
                :value="search"
                @input="emit('update:search', $event.target.value)"
                type="text"
                placeholder="Buscar por data..."
                class="h-9 w-56 rounded-md border border-input bg-white pl-9 pr-3 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-1 transition-colors"
            />
        </div>
    </div>
</template>
