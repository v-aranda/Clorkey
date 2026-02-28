<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { X } from 'lucide-vue-next';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    modelValue: { type: Array, default: () => [] }, // array of user IDs (numbers)
});

const emit = defineEmits(['update:modelValue']);

const search = ref('');
const showDropdown = ref(false);
const containerRef = ref(null);

function onClickOutside(event) {
    if (containerRef.value && !containerRef.value.contains(event.target)) {
        showDropdown.value = false;
    }
}

// Use capture phase so the check runs BEFORE Vue re-renders (removing the clicked
// button from the DOM), which would otherwise cause contains() to return false.
onMounted(() => document.addEventListener('mousedown', onClickOutside, true));
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside, true));

function normalizeId(value) {
    const n = Number(value);
    return Number.isNaN(n) ? null : n;
}

function getUserById(id) {
    const nid = normalizeId(id);
    return props.users.find(u => normalizeId(u.id) === nid);
}

function userOrPlaceholder(id) {
    return getUserById(id) ?? { id: normalizeId(id), name: 'Usuário', avatar_url: null };
}

const filteredUsers = computed(() => {
    const q = search.value.toLowerCase();
    return props.users.filter(u => {
        const nid = normalizeId(u.id);
        return !props.modelValue.includes(nid) &&
            (u.name.toLowerCase().includes(q) || u.email?.toLowerCase().includes(q));
    });
});

function add(userId) {
    const nid = normalizeId(userId);
    if (nid === null || props.modelValue.includes(nid)) return;
    emit('update:modelValue', [...props.modelValue, nid]);
}

function remove(userId) {
    const nid = normalizeId(userId);
    emit('update:modelValue', props.modelValue.filter(id => id !== nid));
}
</script>

<template>
    <div>
        <!-- Selected chips -->
        <div v-if="modelValue.length" class="mt-2 flex flex-wrap gap-1.5">
            <span
                v-for="pid in modelValue"
                :key="pid"
                class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 pl-1.5 pr-2 py-0.5 text-xs font-medium text-gray-700"
            >
                <UserAvatar :user="userOrPlaceholder(pid)" size="xs" />
                <span>{{ userOrPlaceholder(pid).name }}</span>
                <button type="button" @click="remove(pid)" class="ml-0.5 text-gray-400 hover:text-red-500">
                    <X class="h-3 w-3" />
                </button>
            </span>
        </div>

        <!-- Search input -->
        <div ref="containerRef" class="relative mt-2">
            <input
                v-model="search"
                @focus="showDropdown = true"
                type="text"
                placeholder="Buscar participante..."
                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            />
            <div
                v-if="showDropdown && filteredUsers.length"
                class="absolute left-0 right-0 top-full z-20 mt-1 max-h-40 overflow-y-auto rounded-md border bg-white shadow-lg"
            >
                <button
                    v-for="user in filteredUsers"
                    :key="user.id"
                    type="button"
                    @mousedown.prevent="add(user.id); search = ''"
                    class="flex w-full items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    <UserAvatar :user="user" size="xs" />
                    <span>{{ user.name }}</span>
                    <span class="ml-auto text-xs text-gray-400">{{ user.email }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
