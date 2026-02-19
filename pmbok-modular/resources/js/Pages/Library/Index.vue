<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import BookCard from '@/Components/ui/BookCard.vue';
import Input from '@/Components/ui/Input.vue';
import { Search, LibraryBig } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import HeaderTitle from '@/Components/ui/HeaderTitle.vue';

const props = defineProps({
    books: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');

const debounce = (fn, delay) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
};

watch(
    search,
    debounce((value) => {
        router.get(
            route('library.index'),
            { search: value },
            { preserveState: true, replacement: true }
        );
    }, 300)
);
</script>

<template>

    <Head title="Biblioteca" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex w-full items-center justify-between">
                <!-- Title & Icon -->
                <HeaderTitle :icon="LibraryBig">
                    Biblioteca
                </HeaderTitle>

                <!-- Search Bar -->
                <div class="relative w-96">
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input v-model="search" placeholder="Pesquisar livros..." class="pl-9 bg-white" />
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

                <div v-if="books.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-500">
                    <p>Nenhum livro encontrado.</p>
                </div>

                <div v-else
                    class="grid grid-cols-1 gap-8 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 justify-items-center">
                    <BookCard v-for="book in books" :key="book.id" :color="book.color" :text-color="book.text_color"
                        :icon="book.icon" :image="book.image">
                        {{ book.title }}
                    </BookCard>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
