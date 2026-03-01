<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BookHeart } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HeaderTitle from '@/Components/ui/HeaderTitle.vue';
import DiaryFilters from './components/DiaryFilters.vue';
import DiaryEntriesTable from './components/DiaryEntriesTable.vue';
import DiaryDeleteDialog from './components/DiaryDeleteDialog.vue';
import DiaryViewDialog from './components/DiaryViewDialog.vue';

const props = defineProps({
    entries: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'active');
const showDeleteDialog = ref(false);
const showViewDialog = ref(false);
const selectedEntry = ref(null);
const selectedViewEntry = ref(null);
let searchTimeout = null;

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(updateParams, 300);
});

watch(status, () => {
    updateParams();
});

function updateParams() {
    router.get(route('diary.index'), {
        search: search.value || undefined,
        status: status.value,
    }, {
        preserveState: true,
        replace: true,
    });
}

function openDelete(entry) {
    selectedEntry.value = entry;
    showDeleteDialog.value = true;
}

function submitDelete() {
    if (!selectedEntry.value) return;

    router.delete(route('diary.entries.destroy', selectedEntry.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteDialog.value = false;
            selectedEntry.value = null;
        },
    });
}

function restoreEntry(entry) {
    router.post(route('diary.entries.restore', entry.id), {}, {
        preserveScroll: true,
    });
}

function openReadonly(entry) {
    selectedViewEntry.value = entry;
    showViewDialog.value = true;
}

function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Meu Diário" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-2">
                    <Link :href="route('library.index')" class="group relative flex items-center justify-center h-8 w-8 rounded-full hover:bg-gray-100 transition-colors" title="Voltar">
                        <ArrowLeft class="h-5 w-5 text-gray-500 group-hover:text-gray-700 transition-colors" />
                        <span class="absolute left-full ml-1 whitespace-nowrap text-xs text-gray-600 bg-white border rounded px-2 py-1 shadow-sm opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity">Voltar</span>
                    </Link>
                    <HeaderTitle :icon="BookHeart">
                        Meu Diário
                    </HeaderTitle>
                </div>

                <DiaryFilters v-model:search="search" v-model:status="status" />
            </div>
        </template>

        <DiaryEntriesTable
            :entries="entries"
            :status="status"
            @delete-entry="openDelete"
            @view-entry="openReadonly"
            @restore-entry="restoreEntry"
            @go-page="goToPage"
        />

        <DiaryDeleteDialog
            v-model:open="showDeleteDialog"
            :entry="selectedEntry"
            @confirm="submitDelete"
        />

        <DiaryViewDialog
            v-model:open="showViewDialog"
            :entry="selectedViewEntry"
        />
    </AuthenticatedLayout>
</template>
