<script setup>
import { Link, router } from '@inertiajs/vue3';
import { Folder, Upload, ChevronRight, Trash2, Download, Plus, FolderPlus, Search, X, Pencil, Info as InfoIcon, FolderOpen, FileText, File, Move, Star, StarOff, ArrowLeft, Brain, Heart, Database, Code, Server, Globe } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/ui/Button.vue';
import FolderItem from '@/Components/ui/FolderItem.vue';
import FileItem from '@/Components/ui/FileItem.vue';
import DocumentItem from '@/Components/ui/DocumentItem.vue';
import UploadToast from '@/Components/ui/UploadToast.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import LibraryNavigator from '@/Components/ui/LibraryNavigator.vue';
import FileViewer from '@/Components/ui/FileViewer.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, nextTick, computed } from 'vue';
import axios from 'axios';
import { isImageFile, isPdfFile, isVideoFile } from '@/lib/fileType';

const props = defineProps({
    book: Object,
    currentFolder: Object,
    folders: Array,
    files: Array,
    documents: Array,
    breadcrumbs: Array,
    favoriteIds: { type: Array, default: () => [] },
});

const bookIconComponent = computed(() => {
    const iconMap = { Brain, FileText, Database, Code, Server, Globe, Heart };
    return (props.book?.icon && iconMap[props.book.icon]) || FileText;
});

// =============================================
// Favorites
// =============================================
const favorites = ref(new Set(props.favoriteIds));

const isFavorite = (type, id) => favorites.value.has(`${type}-${id}`);

const toggleFavorite = async (type, id) => {
    try {
        const { data } = await axios.post(route('library.favorites.toggle'), { type, id });
        const key = `${type}-${id}`;
        const updated = new Set(favorites.value);
        if (data.favorited) {
            updated.add(key);
        } else {
            updated.delete(key);
        }
        favorites.value = updated;
    } catch (error) {
        console.error('Erro ao favoritar:', error);
    }
};

const toggleFavoriteFromContext = () => {
    const { item, type } = contextMenu.value;
    toggleFavorite(type, item.id);
    closeContextMenu();
};

const toggleFavoriteFromInfo = () => {
    const type = infoIsFile.value ? 'file' : 'folder';
    toggleFavorite(type, infoItem.value.id);
};

// =============================================
// Search & Filter
// =============================================
const searchQuery = ref('');

const filteredFolders = computed(() => {
    if (!searchQuery.value) return props.folders;
    const q = searchQuery.value.toLowerCase();
    return props.folders.filter(f => f.name.toLowerCase().includes(q));
});

const filteredFiles = computed(() => {
    if (!searchQuery.value) return props.files;
    const q = searchQuery.value.toLowerCase();
    return props.files.filter(f => f.name.toLowerCase().includes(q));
});

const filteredDocuments = computed(() => {
    if (!searchQuery.value) return props.documents;
    const q = searchQuery.value.toLowerCase();
    return props.documents.filter(d => d.title.toLowerCase().includes(q));
});

// =============================================
// Selection Mode
// =============================================
const selectedItems = ref(new Set());
const selectionMode = computed(() => selectedItems.value.size > 0);

const toggleSelectFolder = (folder) => {
    const key = `folder-${folder.id}`;
    const next = new Set(selectedItems.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    selectedItems.value = next;
};

const toggleSelectFile = (file) => {
    const key = `file-${file.id}`;
    const next = new Set(selectedItems.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    selectedItems.value = next;
};

const toggleSelectDocument = (doc) => {
    const key = `document-${doc.id}`;
    const next = new Set(selectedItems.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    selectedItems.value = next;
};

const isFolderSelected = (folder) => selectedItems.value.has(`folder-${folder.id}`);
const isFileSelected = (file) => selectedItems.value.has(`file-${file.id}`);
const isDocumentSelected = (doc) => selectedItems.value.has(`document-${doc.id}`);

const cancelSelection = () => {
    selectedItems.value = new Set();
};

const selectedCount = computed(() => selectedItems.value.size);

// =============================================
// Delete Confirmation Dialog
// =============================================
const showDeleteDialog = ref(false);
const deleteTarget = ref(null); // { type: 'file'|'folder'|'bulk', item?, message }

const requestDelete = (type, item = null) => {
    let message = '';
    if (type === 'bulk') {
        message = `Tem certeza que deseja excluir ${selectedCount.value} item(ns) selecionado(s)?`;
    } else if (type === 'folder') {
        message = `Tem certeza que deseja excluir o ficheiro "${item.name}" e todo seu conteúdo?`;
    } else if (type === 'file') {
        message = `Tem certeza que deseja excluir o arquivo "${item.name}"?`;
    } else if (type === 'document') {
        message = `Tem certeza que deseja excluir o documento "${item.title}"?`;
    }
    deleteTarget.value = { type, item, message };
    showDeleteDialog.value = true;
};

const confirmDelete = () => {
    if (!deleteTarget.value) return;

    const { type, item } = deleteTarget.value;

    if (type === 'bulk') {
        const folderIds = [];
        const fileIds = [];
        const docIds = [];
        selectedItems.value.forEach(key => {
            const [t, id] = key.split('-');
            if (t === 'folder') folderIds.push(id);
            if (t === 'file') fileIds.push(id);
            if (t === 'document') docIds.push(id);
        });

        folderIds.forEach(id => {
            useForm({}).delete(route('library.folders.destroy', id), { preserveScroll: true });
        });
        fileIds.forEach(id => {
            useForm({}).delete(route('library.files.destroy', id), { preserveScroll: true });
        });
        docIds.forEach(id => {
            useForm({}).delete(route('library.documents.destroy', id), { preserveScroll: true });
        });
        cancelSelection();
    } else if (type === 'folder') {
        useForm({}).delete(route('library.folders.destroy', item.id));
    } else if (type === 'file') {
        useForm({}).delete(route('library.files.destroy', item.id));
    } else if (type === 'document') {
        useForm({}).delete(route('library.documents.destroy', item.id));
    }

    showDeleteDialog.value = false;
    deleteTarget.value = null;
};

const bulkDelete = () => {
    requestDelete('bulk');
};

// =============================================
// Move Modal
// =============================================
const showMoveModal = ref(false);
const moveDestination = ref({ bookId: null, folderId: null, level: 'root' });
const moveItemsSource = ref({ fileIds: [], folderIds: [] }); // what we're moving

const excludeIdsForNavigator = computed(() => {
    return {
        folders: moveItemsSource.value.folderIds,
        files: moveItemsSource.value.fileIds,
    };
});

const openMoveModal = (fileIds = [], folderIds = []) => {
    moveItemsSource.value = { fileIds, folderIds };
    moveDestination.value = { bookId: null, folderId: null, level: 'root' };
    showMoveModal.value = true;
};

const openMoveFromSelection = () => {
    const fileIds = [];
    const folderIds = [];
    selectedItems.value.forEach(key => {
        const [type, id] = key.split('-');
        if (type === 'file') fileIds.push(Number(id));
        if (type === 'folder') folderIds.push(Number(id));
    });
    openMoveModal(fileIds, folderIds);
};

const openMoveFromContext = () => {
    const { item, type } = contextMenu.value;
    if (type === 'file') {
        openMoveModal([item.id], []);
    } else {
        openMoveModal([], [item.id]);
    }
    closeContextMenu();
};

const handleNavigatorSelect = (selection) => {
    moveDestination.value = selection;
};

const canMove = computed(() => {
    return moveDestination.value.bookId !== null;
});

const confirmMove = async () => {
    if (!canMove.value) return;

    try {
        await axios.post(route('library.files.move'), {
            file_ids: moveItemsSource.value.fileIds.length > 0 ? moveItemsSource.value.fileIds : undefined,
            folder_ids: moveItemsSource.value.folderIds.length > 0 ? moveItemsSource.value.folderIds : undefined,
            destination_book_id: moveDestination.value.bookId,
            destination_folder_id: moveDestination.value.folderId,
        });

        showMoveModal.value = false;
        cancelSelection();
        router.reload({ preserveScroll: true });
    } catch (error) {
        alert('Erro ao mover itens. Tente novamente.');
    }
};

// =============================================
// Drag and Drop (Move)
// =============================================
const dragItem = ref(null);
const dropTargetId = ref(null);
const isDraggingOverBreadcrumb = ref(null);

const onDragStart = (e, type, id) => {
    e.dataTransfer.effectAllowed = 'move';
    dragItem.value = { type, id };
    e.dataTransfer.setData('text/plain', JSON.stringify({ type, id }));
};

const onDragOverFolder = (e, folderId) => {
    if (!dragItem.value) return;
    if (dragItem.value.type === 'folder' && dragItem.value.id === folderId) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    dropTargetId.value = folderId;
};

const onDragLeaveFolder = (e, folderId) => {
    if (dropTargetId.value === folderId) {
        dropTargetId.value = null;
    }
};

const onDragOverBreadcrumb = (e, targetId) => {
    if (!dragItem.value) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    isDraggingOverBreadcrumb.value = targetId;
};

const onDragLeaveBreadcrumb = (e, targetId) => {
    if (isDraggingOverBreadcrumb.value === targetId) {
        isDraggingOverBreadcrumb.value = null;
    }
};

const onDropMove = async (e, destinationFolderId, destinationBookId = null) => {
    e.preventDefault();

    // Reset visual state immediately
    const targetFolder = destinationFolderId;
    dropTargetId.value = null;
    isDraggingOverBreadcrumb.value = null;

    if (!dragItem.value) return;

    if (dragItem.value.type === 'folder' && dragItem.value.id === targetFolder) {
        dragItem.value = null;
        return;
    }

    const currentFolderId = props.currentFolder?.id || null;
    const destFolderId = targetFolder || null;

    // Disallow if moving to current directory
    if (destFolderId === currentFolderId) {
        dragItem.value = null;
        return;
    }

    try {
        const payload = {
            destination_book_id: destinationBookId || props.book.id,
            destination_folder_id: destFolderId,
        };

        if (dragItem.value.type === 'file') {
            payload.file_ids = [dragItem.value.id];
        } else {
            payload.folder_ids = [dragItem.value.id];
        }

        await axios.post(route('library.files.move'), payload);
        router.reload({ preserveScroll: true });
    } catch (error) {
        alert('Erro ao mover item. Tente novamente.');
    } finally {
        dragItem.value = null;
    }
};

const onDragEnd = () => {
    dragItem.value = null;
    dropTargetId.value = null;
    isDraggingOverBreadcrumb.value = null;
};

// =============================================
// File Viewer
// =============================================
const viewerFile = ref(null);

const viewableFiles = computed(() => {
    return props.files.filter((f) => isImageFile(f) || isPdfFile(f) || isVideoFile(f));
});

const openViewer = (file) => {
    viewerFile.value = file;
};

const closeViewer = () => {
    viewerFile.value = null;
};

const navigateViewer = (file) => {
    viewerFile.value = file;
};

const bulkDownload = async () => {
    const fileIds = [];
    const folderIds = [];
    selectedItems.value.forEach(key => {
        const [type, id] = key.split('-');
        if (type === 'file') fileIds.push(Number(id));
        if (type === 'folder') folderIds.push(Number(id));
    });

    if (fileIds.length === 0 && folderIds.length === 0) {
        alert('Nenhum item selecionado para download.');
        return;
    }

    try {
        const response = await axios.post(route('library.files.download-zip'), {
            file_ids: fileIds.length > 0 ? fileIds : undefined,
            folder_ids: folderIds.length > 0 ? folderIds : undefined,
        }, {
            responseType: 'blob',
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `download_${new Date().toISOString().slice(0, 10)}.zip`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        alert('Erro ao baixar arquivos. Tente novamente.');
    }
};

// =============================================
// Info Off-Canvas Panel
// =============================================
const showInfoPanel = ref(false);
const infoItem = ref(null);
const infoItemType = ref(null); // 'file' | 'folder'

const openInfoPanel = (item, type) => {
    infoItem.value = item;
    infoItemType.value = type;
    showInfoPanel.value = true;
};

const closeInfoPanel = () => {
    showInfoPanel.value = false;
    infoItem.value = null;
    infoItemType.value = null;
};

const infoIsFile = computed(() => infoItemType.value === 'file');
const infoIsFolder = computed(() => infoItemType.value === 'folder');
const infoIsDocument = computed(() => infoItemType.value === 'document');

const infoItemName = computed(() => {
    if (!infoItem.value) return '';
    return infoIsDocument.value ? infoItem.value.title : infoItem.value.name;
});

const infoPreviewSrc = computed(() => {
    if (!infoItem.value || !infoIsFile.value) return null;
    const file = infoItem.value;
    if (isPdfFile(file) && file.preview_url) return file.preview_url;
    if (isImageFile(file) && file.file_url) return file.file_url;
    return null;
});

const infoFileSize = computed(() => {
    if (!infoItem.value?.size) return '—';
    const size = infoItem.value.size;
    if (size < 1024) return size + ' B';
    if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
    return (size / (1024 * 1024)).toFixed(1) + ' MB';
});

const infoCreatedAt = computed(() => {
    if (!infoItem.value?.created_at) return '—';
    return new Date(infoItem.value.created_at).toLocaleDateString('pt-BR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
});

// Inline rename from info panel
const infoEditing = ref(false);
const infoEditName = ref('');
const infoInputRef = ref(null);

const startInfoRename = () => {
    infoEditName.value = infoItemName.value;
    infoEditing.value = true;
    nextTick(() => {
        if (infoInputRef.value) {
            infoInputRef.value.focus();
            if (infoIsFile.value) {
                const dot = infoEditName.value.lastIndexOf('.');
                if (dot > 0) {
                    infoInputRef.value.setSelectionRange(0, dot);
                } else {
                    infoInputRef.value.select();
                }
            } else {
                infoInputRef.value.select();
            }
        }
    });
};

const saveInfoRename = () => {
    const trimmed = infoEditName.value.trim();
    if (!trimmed || trimmed === infoItemName.value) {
        infoEditing.value = false;
        return;
    }

    if (infoIsFile.value) {
        renameFile(infoItem.value, trimmed);
    } else if (infoIsDocument.value) {
        renameDocument(infoItem.value, trimmed);
    } else {
        renameFolder(infoItem.value, trimmed);
    }
    infoEditing.value = false;
};

const deleteFromInfo = () => {
    if (infoIsFile.value) {
        deleteFile(infoItem.value);
    } else if (infoIsDocument.value) {
        deleteDocument(infoItem.value);
    } else {
        deleteFolder(infoItem.value);
    }
    closeInfoPanel();
};

const downloadFolder = async (folder) => {
    try {
        const response = await axios.post(route('library.files.download-zip'), {
            folder_ids: [folder.id],
        }, { responseType: 'blob' });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `${folder.name}.zip`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        alert('Erro ao baixar ficheiro. Tente novamente.');
    }
};

const downloadFromInfo = () => {
    if (infoIsFile.value) {
        window.open(route('library.files.download', infoItem.value.id), '_blank');
    } else if (infoIsDocument.value) {
        window.open(route('library.documents.download', infoItem.value.id), '_blank');
    } else {
        downloadFolder(infoItem.value);
    }
};

// =============================================
// Context Menu
// =============================================
const contextMenu = ref({ show: false, x: 0, y: 0, item: null, type: null });

const openContextMenu = (e, item, type) => {
    contextMenu.value = {
        show: true,
        x: e.clientX,
        y: e.clientY,
        item,
        type,
    };
};

const closeContextMenu = () => {
    contextMenu.value.show = false;
};

const contextDownload = () => {
    if (contextMenu.value.type === 'document') {
        window.open(route('library.documents.download', contextMenu.value.item.id), '_blank');
        closeContextMenu();
        return;
    }

    if (contextMenu.value.type === 'file') {
        window.open(route('library.files.download', contextMenu.value.item.id), '_blank');
    } else {
        downloadFolder(contextMenu.value.item);
    }
    closeContextMenu();
};

const contextRename = () => {
    openInfoPanel(contextMenu.value.item, contextMenu.value.type);
    nextTick(() => startInfoRename());
    closeContextMenu();
};

const contextDelete = () => {
    if (contextMenu.value.type === 'file') {
        deleteFile(contextMenu.value.item);
    } else if (contextMenu.value.type === 'document') {
        deleteDocument(contextMenu.value.item);
    } else {
        deleteFolder(contextMenu.value.item);
    }
    closeContextMenu();
};

const contextOpen = () => {
    if (contextMenu.value.type === 'folder') {
        router.get(route('library.folder.show', [props.book.id, contextMenu.value.item.id]));
    }
    closeContextMenu();
};

// =============================================
// FAB
// =============================================
const showFab = ref(false);

// =============================================
// Folder CRUD
// =============================================
const folderRefs = ref([]);

const getNextDefaultName = (baseName = 'Novo Ficheiro') => {
    const names = props.folders.map(f => f.name);
    if (!names.includes(baseName)) return baseName;

    let i = 1;
    while (names.includes(`${baseName} (${i})`)) {
        i++;
    }
    return `${baseName} (${i})`;
};

const folderRefMap = ref({});
const setFolderRef = (el, id) => {
    if (el) {
        folderRefMap.value[id] = el;
    }
};

const createFolderWithAutoEdit = () => {
    const name = getNextDefaultName();

    useForm({
        name: name,
        library_book_id: props.book.id,
        parent_id: props.currentFolder?.id || null,
    }).post(route('library.folders.store'), {
        preserveScroll: true,
        onSuccess: () => {
            nextTick(() => {
                const newFolder = props.folders.find(f => f.name === name);
                if (newFolder && folderRefMap.value[newFolder.id]) {
                    folderRefMap.value[newFolder.id].startEditing();
                }
            });
        },
    });
};

const createDocument = () => {
    useForm({
        title: 'Novo Documento',
        library_book_id: props.book.id,
        library_folder_id: props.currentFolder?.id || null,
    }).post(route('library.documents.store'), {
        preserveScroll: true,
    });
};

const deleteFolder = (folder) => {
    requestDelete('folder', folder);
};

const renameFolder = (folder, newName) => {
    if (folder.name === newName) return;

    useForm({ name: newName }).put(route('library.folders.update', folder.id), {
        preserveScroll: true,
    });
};

// =============================================
// Upload Logic with Progress Toast
// =============================================
const fileInput = ref(null);
const activeDrag = ref(false);

const uploadToast = ref({
    show: false,
    status: 'idle',
    progress: 0,
    message: '',
    fileName: '',
});

const MAX_FILE_SIZE_MB = 150;
const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;
const ALLOWED_EXTENSIONS = null;

const showToast = (status, message, fileName = '') => {
    uploadToast.value = { show: true, status, progress: status === 'success' ? 100 : 0, message, fileName };
};

const validateFiles = (files) => {
    for (const file of files) {
        if (file.size > MAX_FILE_SIZE_BYTES) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
            showToast('error', `Arquivo "${file.name}" (${sizeMB} MB) excede o limite de ${MAX_FILE_SIZE_MB} MB.`, file.name);
            return false;
        }

        if (ALLOWED_EXTENSIONS) {
            const ext = file.name.split('.').pop()?.toLowerCase();
            if (!ALLOWED_EXTENSIONS.includes(ext)) {
                showToast('error', `Formato ".${ext}" não aceito. Formatos permitidos: ${ALLOWED_EXTENSIONS.join(', ')}.`, file.name);
                return false;
            }
        }
    }
    return true;
};

const handleDrop = (e) => {
    activeDrag.value = false;
    const files = e.dataTransfer.files;
    if (files.length) {
        uploadFiles(files);
    }
};

const handleFileSelect = (e) => {
    const files = e.target.files;
    if (files.length) {
        uploadFiles(files);
    }
    e.target.value = '';
};

const uploadFiles = async (fileList) => {
    const files = Array.from(fileList);

    if (!validateFiles(files)) return;

    const fileNames = files.map(f => f.name).join(', ');

    showToast('uploading', files.length === 1 ? `Enviando "${files[0].name}"...` : `Enviando ${files.length} arquivos...`, fileNames);

    const formData = new FormData();
    formData.append('library_book_id', props.book.id);
    formData.append('library_folder_id', props.currentFolder?.id || '');

    files.forEach(file => {
        formData.append('files[]', file);
    });

    try {
        await axios.post(route('library.files.store'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'X-Requested-With': 'XMLHttpRequest',
            },
            onUploadProgress: (progressEvent) => {
                const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                uploadToast.value.progress = percent;
            },
        });

        showToast('success', files.length === 1 ? `"${files[0].name}" enviado com sucesso!` : `${files.length} arquivos enviados com sucesso!`, fileNames);

        router.reload({ preserveScroll: true });

    } catch (error) {
        let errorMessage = 'Ocorreu um erro desconhecido ao enviar o arquivo.';

        if (error.response?.status === 413) {
            errorMessage = `O arquivo é muito grande. O limite máximo de envio é de ${MAX_FILE_SIZE_MB} MB.`;
        } else if (error.response?.status === 422) {
            const errors = error.response.data?.errors;
            if (errors) {
                const firstKey = Object.keys(errors)[0];
                const firstError = errors[firstKey]?.[0] || '';

                if (firstError.includes('max') || firstError.includes('size')) {
                    errorMessage = `O arquivo excede o tamanho máximo permitido de ${MAX_FILE_SIZE_MB} MB.`;
                } else if (firstError.includes('mimes') || firstError.includes('mimetypes') || firstError.includes('file')) {
                    errorMessage = `Formato de arquivo não permitido. Verifique os formatos aceitos.`;
                } else {
                    errorMessage = firstError;
                }
            }
        } else if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.message === 'Network Error') {
            errorMessage = 'Falha na conexão com o servidor. Verifique sua internet e tente novamente.';
        }

        showToast('error', errorMessage, fileNames);
    }
};

const deleteFile = (file) => {
    requestDelete('file', file);
};

const renameFile = (file, newName) => {
    if (file.name === newName) return;

    useForm({ name: newName }).put(route('library.files.update', file.id), {
        preserveScroll: true,
    });
};

const deleteDocument = (doc) => {
    requestDelete('document', doc);
};

const renameDocument = (doc, newName) => {
    if (doc.title === newName) return;

    useForm({ title: newName }).put(route('library.documents.update', doc.id), {
        preserveScroll: true,
    });
};

</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <!-- Back + Title + Breadcrumb -->
                <div class="flex items-center gap-2 min-w-0">
                    <Link :href="route('library.index')" class="group relative flex items-center justify-center h-8 w-8 rounded-full hover:bg-gray-100 transition-colors shrink-0" title="Voltar">
                        <ArrowLeft class="h-5 w-5 text-gray-500 group-hover:text-gray-700 transition-colors" />
                        <span class="absolute left-full ml-1 whitespace-nowrap text-xs text-gray-600 bg-white border rounded px-2 py-1 shadow-sm opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50">Voltar</span>
                    </Link>

                    <nav class="flex items-center gap-1.5 text-sm text-gray-500 min-w-0">
                        <Link :href="route('library.show', book.id)" @dragover="onDragOverBreadcrumb($event, 'root')"
                            @dragleave="onDragLeaveBreadcrumb($event, 'root')" @drop="onDropMove($event, null, book.id)"
                            class="flex items-center gap-2 px-1.5 py-0.5 rounded hover:text-gray-900 transition-colors whitespace-nowrap" :class="[
                                !currentFolder && !breadcrumbs?.length ? 'font-semibold text-gray-800' : '',
                                isDraggingOverBreadcrumb === 'root' ? 'bg-primary/10 text-primary ring-1 ring-primary border-primary border-dashed' : ''
                            ]">
                            <component :is="bookIconComponent" class="h-5 w-5 text-gray-600 shrink-0" />
                            <span class="text-lg font-semibold text-gray-900">{{ book.title }}</span>
                        </Link>
                        <template v-if="breadcrumbs?.length" v-for="crumb in breadcrumbs.filter(c => c.id !== currentFolder?.id)" :key="crumb.id">
                            <ChevronRight class="w-4 h-4 shrink-0 text-gray-400" />
                            <Link :href="route('library.folder.show', [book.id, crumb.id])"
                                @dragover="onDragOverBreadcrumb($event, crumb.id)"
                                @dragleave="onDragLeaveBreadcrumb($event, crumb.id)"
                                @drop="onDropMove($event, crumb.id, book.id)"
                                class="px-1.5 py-0.5 rounded hover:text-gray-900 transition-colors whitespace-nowrap truncate max-w-[120px]"
                                :class="isDraggingOverBreadcrumb === crumb.id ? 'bg-primary/10 text-primary ring-1 ring-primary border-primary border-dashed' : ''">
                                {{ crumb.name }}
                            </Link>
                        </template>
                        <template v-if="currentFolder">
                            <ChevronRight class="w-4 h-4 shrink-0 text-gray-400" />
                            <span class="font-semibold text-gray-800 whitespace-nowrap truncate max-w-[180px]">{{
                                currentFolder.name }}</span>
                        </template>
                    </nav>
                </div>

                <!-- Right side: Search + Selection Actions -->
                <div class="flex items-center gap-3">
                    <!-- Selection Mode Actions -->
                    <template v-if="selectionMode">
                        <span class="text-sm font-medium text-gray-600">{{ selectedCount }} selecionado(s)</span>
                        <Button variant="outline" size="sm" @click="openMoveFromSelection" class="gap-1.5">
                            <Move class="w-4 h-4" />
                            Mover
                        </Button>
                        <Button variant="outline" size="sm" @click="bulkDownload" class="gap-1.5">
                            <Download class="w-4 h-4" />
                            Baixar
                        </Button>
                        <Button variant="outline" size="sm" @click="bulkDelete"
                            class="gap-1.5 text-red-600 border-red-200 hover:bg-red-50 hover:text-red-700">
                            <Trash2 class="w-4 h-4" />
                            Excluir
                        </Button>
                        <Button variant="ghost" size="sm" @click="cancelSelection" class="gap-1.5">
                            <X class="w-4 h-4" />
                            Cancelar
                        </Button>
                    </template>

                    <!-- Search Bar -->
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input v-model="searchQuery" type="text" placeholder="Buscar arquivos e ficheiros..."
                            class="h-9 w-64 rounded-md border border-input bg-white pl-9 pr-3 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-1 transition-colors" />
                    </div>
                </div>
            </div>
        </template>

        <!-- Hidden file input -->
        <input type="file" ref="fileInput" multiple class="hidden" @change="handleFileSelect">

        <div class="py-6" @click="closeContextMenu">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Content Area (Drop Zone) -->
                <div class="overflow-hidden sm:rounded-lg min-h-[500px] border-2 border-transparent transition-all"
                    :class="{ 'border-primary border-dashed bg-primary/5': activeDrag }"
                    @dragover.prevent="activeDrag = true" @dragleave.prevent="activeDrag = false"
                    @drop.prevent="handleDrop">
                    <div class="p-6">

                        <!-- Folders Grid -->
                        <div v-if="filteredFolders.length > 0" class="mb-8">
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Ficheiros</h3>
                            <div class="flex flex-wrap gap-4">
                                <div v-for="folder in filteredFolders" :key="folder.id"
                                    class="relative transition-all rounded-lg"
                                    :class="dropTargetId === folder.id ? 'ring-2 ring-primary ring-offset-2 scale-[1.02] bg-primary/5' : ''"
                                    draggable="true" @dragstart="onDragStart($event, 'folder', folder.id)"
                                    @dragend="onDragEnd" @dragover="onDragOverFolder($event, folder.id)"
                                    @dragleave="onDragLeaveFolder($event, folder.id)"
                                    @drop="onDropMove($event, folder.id, book.id)">
                                    <FolderItem :ref="(el) => setFolderRef(el, folder.id)" :name="folder.name"
                                        :href="route('library.folder.show', [book.id, folder.id])"
                                        :selected="isFolderSelected(folder)" :selection-mode="selectionMode"
                                        :favorited="isFavorite('folder', folder.id)"
                                        @rename="(newName) => renameFolder(folder, newName)"
                                        @select="toggleSelectFolder(folder)" @info="openInfoPanel(folder, 'folder')"
                                        @unfavorite="toggleFavorite('folder', folder.id)"
                                        @contextmenu="(e) => openContextMenu(e, folder, 'folder')" />
                                </div>
                            </div>
                        </div>

                        <!-- Files Grid -->
                        <div v-if="filteredFiles.length > 0">
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Arquivos</h3>
                            <div class="flex flex-wrap gap-4">
                                <div v-for="file in filteredFiles" :key="file.id"
                                    class="relative transition-all rounded-lg" draggable="true"
                                    @dragstart="onDragStart($event, 'file', file.id)" @dragend="onDragEnd">
                                    <FileItem :file="file" :selected="isFileSelected(file)"
                                        :selection-mode="selectionMode" :favorited="isFavorite('file', file.id)"
                                        @rename="(newName) => renameFile(file, newName)"
                                        @select="toggleSelectFile(file)" @info="openInfoPanel(file, 'file')"
                                        @preview="openViewer(file)" @unfavorite="toggleFavorite('file', file.id)"
                                        @contextmenu="(e) => openContextMenu(e, file, 'file')" />
                                </div>
                            </div>
                        </div>

                        <!-- Documents Grid -->
                        <div v-if="filteredDocuments.length > 0" class="mt-8">
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Documentos
                            </h3>
                            <div class="flex flex-wrap gap-4">
                                <div v-for="doc in filteredDocuments" :key="doc.id"
                                    class="relative transition-all rounded-lg" draggable="true"
                                    @dragstart="onDragStart($event, 'document', doc.id)" @dragend="onDragEnd">
                                    <DocumentItem :document="doc" :selected="isDocumentSelected(doc)"
                                        :selection-mode="selectionMode" :favorited="isFavorite('document', doc.id)"
                                        @rename="(newName) => renameDocument(doc, newName)"
                                        @select="toggleSelectDocument(doc)" @info="openInfoPanel(doc, 'document')"
                                        @preview="router.get(route('library.documents.show', doc.id))"
                                        @unfavorite="toggleFavorite('document', doc.id)"
                                        @contextmenu="(e) => openContextMenu(e, doc, 'document')" />
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-if="filteredFolders.length === 0 && filteredFiles.length === 0 && filteredDocuments.length === 0"
                            class="flex flex-col items-center justify-center py-20 text-gray-400">
                            <Upload class="w-12 h-12 mb-4 text-gray-300" />
                            <template v-if="searchQuery">
                                <p class="text-lg font-medium text-gray-500">Nenhum resultado encontrado</p>
                                <p class="text-sm">Tente buscar com outros termos</p>
                            </template>
                            <template v-else>
                                <p class="text-lg font-medium text-gray-500">Este ficheiro está vazio</p>
                                <p class="text-sm">Arraste arquivos aqui ou use o botão <strong>+</strong></p>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== CONTEXT MENU ========== -->
        <Teleport to="body">
            <div v-if="contextMenu.show" class="fixed inset-0 z-[60]" @click="closeContextMenu"
                @contextmenu.prevent="closeContextMenu">
                <div class="fixed bg-white rounded-lg shadow-xl border py-1 min-w-[180px] z-[61] animate-in fade-in zoom-in-95 duration-100"
                    :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }">
                    <!-- Open (folders only) -->
                    <button v-if="contextMenu.type === 'folder'" @click="contextOpen"
                        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                        <FolderOpen class="w-4 h-4 text-gray-400" />
                        Abrir
                    </button>
                    <!-- Download -->
                    <button @click="contextDownload"
                        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                        <Download class="w-4 h-4 text-gray-400" />
                        Baixar
                    </button>
                    <!-- Rename -->
                    <button @click="contextRename"
                        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                        <Pencil class="w-4 h-4 text-gray-400" />
                        Renomear
                    </button>
                    <!-- Move -->
                    <button @click="openMoveFromContext"
                        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                        <Move class="w-4 h-4 text-gray-400" />
                        Mover
                    </button>
                    <!-- Favorite -->
                    <button @click="toggleFavoriteFromContext"
                        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                        <Star v-if="!isFavorite(contextMenu.type, contextMenu.item?.id)"
                            class="w-4 h-4 text-gray-400" />
                        <StarOff v-else class="w-4 h-4 text-amber-500" />
                        {{ isFavorite(contextMenu.type, contextMenu.item?.id) ? 'Desfavoritar' : 'Favoritar' }}
                    </button>
                    <!-- Divider -->
                    <div class="border-t my-1"></div>
                    <!-- Delete -->
                    <button @click="contextDelete"
                        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                        <Trash2 class="w-4 h-4" />
                        Excluir
                    </button>
                </div>
            </div>
        </Teleport>

        <!-- ========== INFO OFF-CANVAS PANEL ========== -->
        <Teleport to="body">
            <!-- Overlay -->
            <Transition enter-active-class="transition-opacity duration-200"
                leave-active-class="transition-opacity duration-150" enter-from-class="opacity-0"
                leave-to-class="opacity-0">
                <div v-if="showInfoPanel" class="fixed inset-0 bg-black/40 z-[70]" @click="closeInfoPanel"></div>
            </Transition>

            <!-- Panel -->
            <Transition enter-active-class="transition-transform duration-300 ease-out"
                leave-active-class="transition-transform duration-200 ease-in" enter-from-class="translate-x-full"
                leave-to-class="translate-x-full">
                <div v-if="showInfoPanel && infoItem"
                    class="fixed inset-y-0 right-0 z-[71] w-full max-w-md flex flex-col bg-white shadow-2xl border-l">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b px-6 py-4 shrink-0">
                        <h2 class="text-lg font-semibold text-gray-900">Informações</h2>
                        <button @click="closeInfoPanel"
                            class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Body (scrollable) -->
                    <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6">
                        <!-- Preview area -->
                        <div
                            class="w-full aspect-[4/3] rounded-xl overflow-hidden bg-gray-50 flex items-center justify-center border">
                            <template v-if="infoIsFile && infoPreviewSrc">
                                <img :src="infoPreviewSrc" class="w-full h-full object-contain" alt="Preview" />
                            </template>
                            <template v-else-if="infoIsFolder">
                                <div class="flex flex-col items-center gap-2">
                                    <Folder class="w-20 h-20 text-blue-400" />
                                </div>
                            </template>
                            <template v-else-if="infoIsDocument">
                                <div class="flex flex-col items-center gap-2">
                                    <FileText class="w-20 h-20 text-indigo-500" />
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex flex-col items-center gap-2">
                                    <FileText v-if="isPdfFile(infoItem)"
                                        class="w-16 h-16 text-gray-300" />
                                    <File v-else class="w-16 h-16 text-gray-300" />
                                </div>
                            </template>
                        </div>

                        <!-- Name with rename -->
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</label>
                            <div class="mt-1 flex items-center gap-2">
                                <template v-if="infoEditing">
                                    <input ref="infoInputRef" v-model="infoEditName"
                                        class="flex-1 h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        @keydown.enter="saveInfoRename" @keydown.escape="infoEditing = false"
                                        @blur="saveInfoRename" />
                                </template>
                                <template v-else>
                                    <span class="flex-1 text-sm font-medium text-gray-800 break-all">{{ infoItemName
                                    }}</span>
                                    <button @click="startInfoRename"
                                        class="p-1.5 rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                                        title="Renomear">
                                        <Pencil class="w-4 h-4" />
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="space-y-3">
                            <div v-if="infoIsFile">
                                <label
                                    class="text-xs font-medium text-gray-500 uppercase tracking-wider">Tamanho</label>
                                <p class="mt-0.5 text-sm text-gray-700">{{ infoFileSize }}</p>
                            </div>
                            <div v-if="infoIsFile && infoItem.mime_type">
                                <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</label>
                                <p class="mt-0.5 text-sm text-gray-700">{{ infoItem.mime_type }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Data de
                                    Upload</label>
                                <p class="mt-0.5 text-sm text-gray-700">{{ infoCreatedAt }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer with actions -->
                    <div class="border-t px-6 py-4 shrink-0 flex gap-3">
                        <Button variant="outline" class="gap-2"
                            :class="isFavorite(infoItemType, infoItem?.id) ? 'text-amber-600 border-amber-200 hover:bg-amber-50' : ''"
                            @click="toggleFavoriteFromInfo">
                            <Star v-if="!isFavorite(infoItemType, infoItem?.id)" class="w-4 h-4" />
                            <StarOff v-else class="w-4 h-4" />
                        </Button>
                        <Button v-if="infoItemType !== 'document'" variant="outline" class="flex-1 gap-2"
                            @click="downloadFromInfo">
                            <Download class="w-4 h-4" />
                            Baixar
                        </Button>
                        <Button variant="outline"
                            class="gap-2 text-red-600 border-red-200 hover:bg-red-50 hover:text-red-700"
                            :class="infoIsFile ? '' : 'flex-1'" @click="deleteFromInfo">
                            <Trash2 class="w-4 h-4" />
                            Excluir
                        </Button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ========== FAB (Floating Action Button) ========== -->

        <!-- Overlay -->
        <Transition enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-150" enter-from-class="opacity-0"
            leave-to-class="opacity-0">
            <div v-if="showFab" class="fixed inset-0 bg-black/30 z-40" @click="showFab = false"></div>
        </Transition>

        <!-- FAB Menu Options -->
        <Transition enter-active-class="transition-all duration-200 ease-out"
            leave-active-class="transition-all duration-150 ease-in" enter-from-class="opacity-0 translate-y-4 scale-95"
            leave-to-class="opacity-0 translate-y-4 scale-95">
            <div v-if="showFab" class="fixed bottom-28 right-8 z-50 flex flex-col gap-3 items-end">
                <!-- Upload option -->
                <button @click="showFab = false; $refs.fileInput.click()"
                    class="flex items-center gap-3 bg-white rounded-full pl-4 pr-5 py-3 shadow-lg hover:shadow-xl transition-all hover:scale-105 group">
                    <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center">
                        <Upload class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Upload</span>
                </button>

                <!-- Nova Pasta option -->
                <button @click="showFab = false; createFolderWithAutoEdit()"
                    class="flex items-center gap-3 bg-white rounded-full pl-4 pr-5 py-3 shadow-lg hover:shadow-xl transition-all hover:scale-105 group">
                    <div class="w-10 h-10 rounded-full bg-amber-500 flex items-center justify-center">
                        <FolderPlus class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Ficheiro</span>
                </button>

                <!-- Nova Documento option -->
                <button @click="showFab = false; createDocument()"
                    class="flex items-center gap-3 bg-white rounded-full pl-4 pr-5 py-3 shadow-lg hover:shadow-xl transition-all hover:scale-105 group">
                    <div class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center">
                        <FileText class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Documento</span>
                </button>
            </div>
        </Transition>

        <!-- FAB Button -->
        <button @click="showFab = !showFab"
            class="fixed bottom-8 right-8 z-50 w-14 h-14 rounded-full bg-primary text-primary-foreground shadow-lg hover:shadow-xl flex items-center justify-center transition-all duration-200 hover:scale-110"
            :class="showFab ? 'rotate-45' : ''">
            <Plus class="w-7 h-7" />
        </button>

    </AuthenticatedLayout>

    <!-- ========== DELETE CONFIRMATION DIALOG ========== -->
    <Dialog v-model:open="showDeleteDialog">
        <template #default="{ close }">
            <h2 class="text-lg font-semibold mb-2">Confirmar Exclusão</h2>
            <p class="text-sm text-muted-foreground mb-6">
                {{ deleteTarget?.message }}
            </p>
            <div class="flex justify-end gap-3">
                <Button type="button" variant="outline" @click="close">Cancelar</Button>
                <Button variant="destructive" @click="confirmDelete">Excluir</Button>
            </div>
        </template>
    </Dialog>

    <!-- ========== MOVE DIALOG ========== -->
    <Dialog v-model:open="showMoveModal" class="max-w-md">
        <template #default="{ close }">
            <h2 class="text-lg font-semibold mb-4">Mover para...</h2>
            <LibraryNavigator max-depth="folder" :exclude-ids="excludeIdsForNavigator"
                @select="handleNavigatorSelect" />
            <div class="flex justify-end gap-3 mt-4 pt-3 border-t">
                <Button type="button" variant="outline" @click="close">Cancelar</Button>
                <Button :disabled="!canMove" @click="confirmMove">Mover para cá</Button>
            </div>
        </template>
    </Dialog>

    <!-- Upload Toast -->
    <UploadToast v-model:show="uploadToast.show" :status="uploadToast.status" :progress="uploadToast.progress"
        :message="uploadToast.message" :file-name="uploadToast.fileName" />

    <!-- File Viewer Overlay -->
    <FileViewer :file="viewerFile" :files="viewableFiles" @close="closeViewer" @navigate="navigateViewer" />
</template>
