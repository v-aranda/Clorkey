<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link, usePage } from '@inertiajs/vue3';
import BookCard from '@/Components/ui/BookCard.vue';
import Input from '@/Components/ui/Input.vue';
import { Search, LibraryBig, Star, FileText, FolderOpen, Image, Film, File, StarOff, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import HeaderTitle from '@/Components/ui/HeaderTitle.vue';
import axios from 'axios';
import { isImageFile, isPdfFile, isVideoFile } from '@/lib/fileType';

const props = defineProps({
    books: Array,
    favorites: { type: Array, default: () => [] },
    filters: Object,
});
const page = usePage();

const search = ref(props.filters.search || '');
const localFavorites = ref([...props.favorites]);

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

const getTypeIcon = (fav) => {
    if (fav.type === 'folder') return FolderOpen;
    if (isPdfFile(fav)) return FileText;
    if (isImageFile(fav)) return Image;
    if (isVideoFile(fav)) return Film;
    return File;
};

const getTypeColor = (fav) => {
    if (fav.type === 'folder') return 'text-amber-500';
    if (isPdfFile(fav)) return 'text-red-500';
    if (isImageFile(fav)) return 'text-blue-500';
    if (isVideoFile(fav)) return 'text-purple-500';
    return 'text-gray-400';
};

const getFavoriteLink = (fav) => {
    if (fav.folder_id) {
        return route('library.folder.show', { book: fav.book_id, folderId: fav.folder_id });
    }
    return route('library.show', fav.book_id);
};

const removeFavorite = async (fav) => {
    try {
        await axios.post(route('library.favorites.toggle'), {
            type: fav.type,
            id: fav.item_id,
        });
        localFavorites.value = localFavorites.value.filter(f => f.id !== fav.id);
        nextTick(() => updateScrollState());
    } catch (error) {
        console.error('Erro ao desfavoritar:', error);
    }
};

const currentUserAvatar = computed(() => page.props.auth?.user?.avatar_url || null);

const getBookImage = (book) => {
    if (book.title === 'Base de Conhecimento') return '/images/brain.png';
    if (isDiaryBook(book)) return currentUserAvatar.value || '/images/heart.png';
    return book.image_url || book.image || null;
};

const getBookIcon = (book) => {
    if (isDiaryBook(book) && !currentUserAvatar.value) return null;
    return book.icon;
};

const isDiaryBook = (book) => {
    const normalized = (book?.title || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toUpperCase();

    return normalized === 'MEU DIARIO' || book?.icon === 'Heart';
};

const getBookLink = (book) => {
    if (!isDiaryBook(book)) {
        return route('library.show', book.id);
    }

    try {
        return route('diary.index');
    } catch (error) {
        return '/diary';
    }
};

// =============================================
// Scroll Navigation
// =============================================
const scrollContainerRef = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

const updateScrollState = () => {
    const el = scrollContainerRef.value;
    if (!el) return;
    canScrollLeft.value = el.scrollLeft > 4;
    canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 4;
};

const scrollFavorites = (amount) => {
    const el = scrollContainerRef.value;
    if (!el) return;
    el.scrollBy({ left: amount, behavior: 'smooth' });
};

onMounted(() => {
    nextTick(() => updateScrollState());
});
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

                <!-- ⭐ Favorites Section -->
                <div v-if="localFavorites.length > 0" class="mb-10">
                    <div class="flex items-center gap-2 mb-4">
                        <Star class="w-5 h-5 text-amber-500 fill-amber-500" />
                        <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Favoritos</h2>
                    </div>

                    <!-- Scroll container with arrows -->
                    <div class="relative group/scroll">
                        <!-- Left Arrow -->
                        <button v-show="canScrollLeft" @click="scrollFavorites(-300)"
                            class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-3 z-10 w-8 h-8 rounded-full bg-white shadow-md border flex items-center justify-center hover:bg-gray-50 transition-all opacity-0 group-hover/scroll:opacity-100">
                            <ChevronLeft class="w-4 h-4 text-gray-600" />
                        </button>

                        <!-- Scrollable area -->
                        <div ref="scrollContainerRef" class="overflow-x-auto pb-2 favorites-scroll"
                            @scroll="updateScrollState">
                            <div class="grid grid-rows-2 grid-flow-col gap-3" :style="{ minWidth: 'max-content' }">
                                <Link v-for="fav in localFavorites" :key="fav.id" :href="getFavoriteLink(fav)"
                                    class="group relative flex items-center gap-3 px-3 py-2.5 bg-white border rounded-xl shadow-sm hover:shadow-md hover:border-amber-200 transition-all duration-200 w-[240px]">
                                    <!-- Preview Thumbnail -->
                                    <div class="w-10 h-10 rounded-lg overflow-hidden shrink-0 flex items-center justify-center"
                                        :class="fav.type === 'folder' ? 'bg-amber-50' : 'bg-gray-100'">
                                        <!-- Image preview -->
                                        <img v-if="fav.type === 'file' && isImageFile(fav) && fav.file_url"
                                            :src="fav.file_url" class="w-full h-full object-cover" alt="" />
                                        <!-- PDF preview -->
                                        <img v-else-if="fav.type === 'file' && isPdfFile(fav) && fav.preview_url"
                                            :src="fav.preview_url" class="w-full h-full object-cover" alt="" />
                                        <!-- Video preview -->
                                        <video
                                            v-else-if="fav.type === 'file' && isVideoFile(fav) && fav.file_url"
                                            :src="fav.file_url + '#t=1'" preload="metadata" muted
                                            class="w-full h-full object-cover" />
                                        <!-- Fallback icon -->
                                        <component v-else :is="getTypeIcon(fav)" class="w-5 h-5"
                                            :class="getTypeColor(fav)" />
                                    </div>

                                    <!-- Name & Path -->
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-800 truncate">{{ fav.name }}</p>
                                        <p class="text-xs text-gray-400 truncate">{{ fav.book_title }}</p>
                                    </div>

                                    <!-- Unfavorite button -->
                                    <button @click.prevent.stop="removeFavorite(fav)"
                                        class="absolute top-1.5 right-1.5 p-1 rounded-full opacity-0 group-hover:opacity-100 hover:bg-amber-100 transition-all"
                                        title="Desfavoritar">
                                        <StarOff class="w-3.5 h-3.5 text-amber-500" />
                                    </button>
                                </Link>
                            </div>
                        </div>

                        <!-- Right Arrow -->
                        <button v-show="canScrollRight" @click="scrollFavorites(300)"
                            class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-3 z-10 w-8 h-8 rounded-full bg-white shadow-md border flex items-center justify-center hover:bg-gray-50 transition-all opacity-0 group-hover/scroll:opacity-100">
                            <ChevronRight class="w-4 h-4 text-gray-600" />
                        </button>
                    </div>
                </div>

                <div v-if="books.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-500">
                    <p>Nenhum livro encontrado.</p>
                </div>

                <div v-else
                    class="grid grid-cols-1 gap-8 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 justify-items-center">
                    <Link v-for="book in books" :key="book.id" :href="getBookLink(book)">
                        <BookCard
                            :color="book.color"
                            :text-color="book.text_color"
                            :icon="getBookIcon(book)"
                            :image="getBookImage(book)"
                            :avatar="isDiaryBook(book) && !!currentUserAvatar"
                        >
                            {{ book.title }}
                        </BookCard>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.favorites-scroll {
    scrollbar-width: thin;
    scrollbar-color: transparent transparent;
}

.favorites-scroll:hover {
    scrollbar-color: rgba(0, 0, 0, 0.15) transparent;
}

.favorites-scroll::-webkit-scrollbar {
    height: 4px;
}

.favorites-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.favorites-scroll::-webkit-scrollbar-thumb {
    background: transparent;
    border-radius: 9999px;
}

.favorites-scroll:hover::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.15);
}
</style>
