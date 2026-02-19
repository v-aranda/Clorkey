<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Book, Folder, ChevronRight, Search, ArrowLeft, FileText, Loader2 } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    /**
     * Maximum navigation depth: 'book' | 'folder' | 'file'
     * 'book'   = only show books (no drilling into folders)
     * 'folder' = show books + folders (default for move)
     * 'file'   = show books + folders + files
     */
    maxDepth: {
        type: String,
        default: 'folder',
        validator: (v) => ['book', 'folder', 'file'].includes(v),
    },
    /**
     * IDs to exclude from display (prevent moving into self).
     * { folders: [1,2], files: [3,4] }
     */
    excludeIds: {
        type: Object,
        default: () => ({ folders: [], files: [] }),
    },
});

const emit = defineEmits(['select']);

// Navigation state
const loading = ref(false);
const searchQuery = ref('');

// Current level: 'root' | 'book' | 'folder'
const currentLevel = ref('root');
const currentBook = ref(null);
const currentFolder = ref(null);
const breadcrumbTrail = ref([]); // [{ id, name, type: 'book'|'folder' }]

// Data
const books = ref([]);
const folders = ref([]);

// Filtered items
const filteredItems = computed(() => {
    const q = searchQuery.value.toLowerCase();
    if (currentLevel.value === 'root') {
        return books.value.filter(b => !q || b.title.toLowerCase().includes(q));
    }
    return folders.value.filter(f => !q || f.name.toLowerCase().includes(q));
});

// Load books (root level)
const loadBooks = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('library.navigator.books'));
        books.value = data;
    } catch (e) {
        books.value = [];
    }
    loading.value = false;
};

// Load folders for a book/parent
const loadFolders = async (bookId, parentId = null) => {
    loading.value = true;
    try {
        const params = {};
        if (parentId) params.parent_id = parentId;
        const { data } = await axios.get(route('library.navigator.folders', bookId), { params });
        // Filter out excluded folders
        folders.value = data.filter(f => !props.excludeIds.folders?.includes(f.id));
    } catch (e) {
        folders.value = [];
    }
    loading.value = false;
};

// Navigate into a book
const enterBook = async (book) => {
    if (props.maxDepth === 'book') {
        // Book is the deepest — just select it
        return;
    }
    currentBook.value = book;
    currentLevel.value = 'book';
    breadcrumbTrail.value = [{ id: book.id, name: book.title, type: 'book' }];
    currentFolder.value = null;
    searchQuery.value = '';
    await loadFolders(book.id);
    emitCurrentSelection();
};

// Navigate into a folder
const enterFolder = async (folder) => {
    currentFolder.value = folder;
    currentLevel.value = 'folder';
    breadcrumbTrail.value.push({ id: folder.id, name: folder.name, type: 'folder' });
    searchQuery.value = '';
    await loadFolders(currentBook.value.id, folder.id);
    emitCurrentSelection();
};

// Go back one level
const goBack = async () => {
    searchQuery.value = '';
    if (breadcrumbTrail.value.length <= 1) {
        // Go back to root
        currentLevel.value = 'root';
        currentBook.value = null;
        currentFolder.value = null;
        breadcrumbTrail.value = [];
        emitCurrentSelection();
        return;
    }

    // Remove last crumb
    breadcrumbTrail.value.pop();
    const last = breadcrumbTrail.value[breadcrumbTrail.value.length - 1];

    if (last.type === 'book') {
        currentFolder.value = null;
        currentLevel.value = 'book';
        await loadFolders(currentBook.value.id);
    } else {
        currentFolder.value = { id: last.id, name: last.name };
        currentLevel.value = 'folder';
        await loadFolders(currentBook.value.id, last.id);
    }
    emitCurrentSelection();
};

// Navigate to a specific breadcrumb
const navigateTo = async (index) => {
    const crumb = breadcrumbTrail.value[index];
    searchQuery.value = '';

    // Trim breadcrumbs
    breadcrumbTrail.value = breadcrumbTrail.value.slice(0, index + 1);

    if (crumb.type === 'book') {
        currentFolder.value = null;
        currentLevel.value = 'book';
        await loadFolders(currentBook.value.id);
    } else {
        currentFolder.value = { id: crumb.id, name: crumb.name };
        currentLevel.value = 'folder';
        await loadFolders(currentBook.value.id, crumb.id);
    }
    emitCurrentSelection();
};

// Go to root
const goToRoot = async () => {
    searchQuery.value = '';
    currentLevel.value = 'root';
    currentBook.value = null;
    currentFolder.value = null;
    breadcrumbTrail.value = [];
    emitCurrentSelection();
};

// Emit current selection
const emitCurrentSelection = () => {
    emit('select', {
        bookId: currentBook.value?.id || null,
        folderId: currentFolder.value?.id || null,
        level: currentLevel.value,
    });
};

// Computed: current location string
const currentLocationLabel = computed(() => {
    if (currentLevel.value === 'root') return 'Biblioteca';
    const parts = breadcrumbTrail.value.map(c => c.name);
    return parts.join(' / ');
});

onMounted(loadBooks);
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Breadcrumb Navigation -->
        <div class="flex items-center gap-1 px-1 pb-3 text-sm text-gray-500 flex-wrap border-b mb-3">
            <button @click="goToRoot" class="hover:text-gray-900 transition-colors font-medium"
                :class="currentLevel === 'root' ? 'text-gray-900' : ''">
                Biblioteca
            </button>
            <template v-for="(crumb, i) in breadcrumbTrail" :key="i">
                <ChevronRight class="w-3.5 h-3.5 shrink-0 text-gray-400" />
                <button @click="navigateTo(i)" class="hover:text-gray-900 transition-colors truncate max-w-[120px]"
                    :class="i === breadcrumbTrail.length - 1 ? 'text-gray-900 font-medium' : ''">
                    {{ crumb.name }}
                </button>
            </template>
        </div>

        <!-- Search -->
        <div class="relative mb-3">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input v-model="searchQuery" type="text"
                :placeholder="currentLevel === 'root' ? 'Buscar livros...' : 'Buscar ficheiros...'"
                class="h-9 w-full rounded-md border border-input bg-white pl-9 pr-3 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-1 transition-colors" />
        </div>

        <!-- Items List -->
        <div class="flex-1 overflow-y-auto min-h-[200px] max-h-[320px]">
            <!-- Loading state -->
            <div v-if="loading" class="flex items-center justify-center py-10 text-gray-400">
                <Loader2 class="w-5 h-5 animate-spin" />
            </div>

            <!-- Back button (when inside a book/folder) -->
            <template v-else>
                <button v-if="currentLevel !== 'root'" @click="goBack"
                    class="w-full flex items-center gap-3 px-3 py-2.5 text-sm text-gray-600 hover:bg-gray-50 rounded-md transition-colors mb-1">
                    <ArrowLeft class="w-4 h-4" />
                    <span>Voltar</span>
                </button>

                <!-- Books list (root level) -->
                <template v-if="currentLevel === 'root'">
                    <button v-for="book in filteredItems" :key="book.id" @click="enterBook(book)"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-md transition-colors">
                        <Book class="w-4 h-4 text-blue-500 shrink-0" />
                        <span class="truncate">{{ book.title }}</span>
                        <ChevronRight v-if="maxDepth !== 'book'" class="w-4 h-4 text-gray-300 ml-auto shrink-0" />
                    </button>
                </template>

                <!-- Folders list (inside a book/folder) -->
                <template v-else>
                    <button v-for="folder in filteredItems" :key="folder.id" @click="enterFolder(folder)"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-amber-50 hover:text-amber-700 rounded-md transition-colors">
                        <Folder class="w-4 h-4 text-amber-500 shrink-0" />
                        <span class="truncate">{{ folder.name }}</span>
                        <ChevronRight v-if="folder.has_children" class="w-4 h-4 text-gray-300 ml-auto shrink-0" />
                    </button>
                </template>

                <!-- Empty state -->
                <div v-if="!loading && filteredItems.length === 0" class="text-center py-8 text-gray-400 text-sm">
                    <template v-if="searchQuery">Nenhum resultado encontrado</template>
                    <template v-else-if="currentLevel === 'root'">Nenhum livro encontrado</template>
                    <template v-else>Nenhum ficheiro aqui</template>
                </div>
            </template>
        </div>

        <!-- Current Location (footer) -->
        <div class="border-t pt-3 mt-3">
            <p class="text-xs text-gray-500">Destino selecionado:</p>
            <p class="text-sm font-medium text-gray-800 truncate mt-0.5">{{ currentLocationLabel }}</p>
        </div>
    </div>
</template>
