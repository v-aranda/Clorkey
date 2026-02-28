<script setup>
import { ref, watch, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import {
    useVueTable,
    getCoreRowModel,
    getSortedRowModel,
    createColumnHelper,
} from '@tanstack/vue-table'
import Badge from '@/Components/ui/Badge.vue'
import Button from '@/Components/ui/Button.vue'
import Dialog from '@/Components/ui/Dialog.vue'
import { Pencil, Trash2, Plus, Users, ArrowUpDown, ArrowUp, ArrowDown, Search, X, RefreshCcw, Camera } from 'lucide-vue-next'

const props = defineProps({
    users: Object,
    filters: Object,
})

// Search & Filter
const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || 'active')
let searchTimeout = null

watch(search, (value) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        updateParams()
    }, 300)
})

watch(status, () => {
    updateParams()
})

function updateParams() {
    router.get(route('admin.users.index'), {
        search: search.value || undefined,
        status: status.value,
    }, {
        preserveState: true,
        replace: true,
    })
}

// Panel state
const showCreatePanel = ref(false)
const showEditPanel = ref(false)
const showDeleteDialog = ref(false)
const showRestoreDialog = ref(false)
const selectedUser = ref(null)

// Avatar preview state
const createAvatarPreview = ref(null)
const editAvatarPreview = ref(null)
const createFileInput = ref(null)
const editFileInput = ref(null)
const editRemoveAvatar = ref(false)

// Toast state
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')
let toastTimer = null

function triggerToast(message, type = 'success') {
    toastMessage.value = message
    toastType.value = type
    showToast.value = true
    clearTimeout(toastTimer)
    toastTimer = setTimeout(() => { showToast.value = false }, 4000)
}

// Sorting state
const sorting = ref([])

// Forms
const createForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'user',
    avatar: null,
})

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    role: '',
    avatar: null,
    remove_avatar: false,
})

// Create avatar preview user
const createPreviewUser = computed(() => ({
    name: createForm.name || '?',
    avatar_url: createAvatarPreview.value,
}))

const editPreviewUser = computed(() => {
    if (editAvatarPreview.value) {
        return { name: editForm.name || '?', avatar_url: editAvatarPreview.value }
    }
    if (editRemoveAvatar.value) {
        return { name: editForm.name || '?', avatar_url: null }
    }
    return { name: editForm.name || '?', avatar_url: selectedUser.value?.avatar_url }
})

// Column definitions (TanStack headless)
const columnHelper = createColumnHelper()

const columns = [
    columnHelper.accessor('name', {
        header: 'Nome',
        enableSorting: true,
    }),
    columnHelper.accessor('email', {
        header: 'Email',
        enableSorting: true,
    }),
    columnHelper.accessor('role', {
        header: 'Função',
        enableSorting: true,
    }),
    columnHelper.accessor('created_at', {
        header: 'Data de Criação',
        enableSorting: true,
    }),
    columnHelper.display({
        id: 'actions',
        header: 'Ações',
        enableSorting: false,
    }),
]

// TanStack Table instance
const table = useVueTable({
    get data() {
        return props.users.data
    },
    columns,
    state: {
        get sorting() {
            return sorting.value
        },
    },
    onSortingChange: (updaterOrValue) => {
        sorting.value = typeof updaterOrValue === 'function'
            ? updaterOrValue(sorting.value)
            : updaterOrValue
    },
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
})

// Actions
function openCreate() {
    createForm.reset()
    createForm.clearErrors()
    createAvatarPreview.value = null
    showEditPanel.value = false
    showCreatePanel.value = true
}

function openEdit(user) {
    selectedUser.value = user
    editForm.name = user.name
    editForm.email = user.email
    editForm.password = ''
    editForm.role = user.role
    editForm.avatar = null
    editForm.remove_avatar = false
    editForm.clearErrors()
    editAvatarPreview.value = null
    editRemoveAvatar.value = false
    showCreatePanel.value = false
    showEditPanel.value = true
}

function openDelete(user) {
    selectedUser.value = user
    showDeleteDialog.value = true
}

function openRestore(user) {
    selectedUser.value = user
    showRestoreDialog.value = true
}

function closePanel() {
    showCreatePanel.value = false
    showEditPanel.value = false
}

// Avatar handlers for create
function selectCreateAvatar() {
    createFileInput.value.click()
}

function onCreateAvatarSelected(e) {
    const file = e.target.files[0]
    if (!file) return
    createForm.avatar = file
    const reader = new FileReader()
    reader.onload = (ev) => { createAvatarPreview.value = ev.target.result }
    reader.readAsDataURL(file)
}

function removeCreateAvatar() {
    createForm.avatar = null
    createAvatarPreview.value = null
    if (createFileInput.value) createFileInput.value.value = ''
}

// Avatar handlers for edit
function selectEditAvatar() {
    editFileInput.value.click()
}

function onEditAvatarSelected(e) {
    const file = e.target.files[0]
    if (!file) return
    editForm.avatar = file
    editForm.remove_avatar = false
    editRemoveAvatar.value = false
    const reader = new FileReader()
    reader.onload = (ev) => { editAvatarPreview.value = ev.target.result }
    reader.readAsDataURL(file)
}

function removeEditAvatar() {
    editForm.avatar = null
    editForm.remove_avatar = true
    editRemoveAvatar.value = true
    editAvatarPreview.value = null
    if (editFileInput.value) editFileInput.value.value = ''
}

function submitCreate() {
    createForm.post(route('admin.users.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            showCreatePanel.value = false
            createForm.reset()
            createAvatarPreview.value = null
            triggerToast('Usuário criado com sucesso!')
        },
    })
}

function submitEdit() {
    editForm.put(route('admin.users.update', selectedUser.value.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            showEditPanel.value = false
            editForm.reset()
            editAvatarPreview.value = null
            editRemoveAvatar.value = false
            triggerToast('Usuário atualizado com sucesso!')
        },
    })
}

function submitDelete() {
    router.delete(route('admin.users.destroy', selectedUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteDialog.value = false
            selectedUser.value = null
            triggerToast('Usuário desativado com sucesso!')
        },
        onError: () => {
            showDeleteDialog.value = false
            triggerToast('Erro ao remover usuário.', 'error')
        },
    })
}

function submitRestore() {
    router.post(route('admin.users.restore', selectedUser.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showRestoreDialog.value = false
            selectedUser.value = null
            triggerToast('Usuário restaurado com sucesso!')
        },
        onError: () => {
            showRestoreDialog.value = false
            triggerToast('Erro ao restaurar usuário.', 'error')
        },
    })
}

// Pagination
function goToPage(url) {
    if (url) {
        router.get(url)
    }
}
</script>

<template>
    <Head title="Gestão de Usuários" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between w-full">
                <div class="flex items-center gap-3">
                    <Users class="h-6 w-6 text-gray-600" />
                    <h1 class="text-lg font-semibold text-gray-900">Gestão de Usuários</h1>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Status Toggle -->
                    <div class="flex bg-gray-100 p-1 rounded-lg">
                        <button
                            @click="status = 'active'"
                            class="px-3 py-1 text-sm font-medium rounded-md transition-all"
                            :class="status === 'active' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'"
                        >
                            Ativos
                        </button>
                        <button
                            @click="status = 'inactive'"
                            class="px-3 py-1 text-sm font-medium rounded-md transition-all"
                            :class="status === 'inactive' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'"
                        >
                            Inativos
                        </button>
                    </div>

                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Buscar por nome ou e-mail..."
                            class="h-9 w-64 rounded-md border border-input bg-white pl-9 pr-3 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-1 transition-colors"
                        />
                    </div>
                    <Button @click="openCreate" class="gap-2">
                        <Plus class="h-4 w-4" />
                        Novo Usuário
                    </Button>
                </div>
            </div>
        </template>

        <!-- Users Table -->
        <div class="rounded-lg border bg-white shadow-sm">
            <table class="w-full caption-bottom text-sm">
                <thead class="[&_tr]:border-b">
                    <tr
                        v-for="headerGroup in table.getHeaderGroups()"
                        :key="headerGroup.id"
                        class="border-b transition-colors"
                    >
                        <th
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                            class="h-10 px-4 align-middle font-medium text-muted-foreground"
                            :class="[
                                header.column.id === 'actions' ? 'text-right' : 'text-left',
                                header.column.getCanSort() ? 'cursor-pointer select-none hover:text-foreground' : '',
                            ]"
                            @click="header.column.getToggleSortingHandler()?.($event)"
                        >
                            <div
                                class="flex items-center gap-1"
                                :class="header.column.id === 'actions' ? 'justify-end' : ''"
                            >
                                {{ header.column.columnDef.header }}
                                <template v-if="header.column.getCanSort()">
                                    <ArrowUp v-if="header.column.getIsSorted() === 'asc'" class="h-4 w-4 text-foreground" />
                                    <ArrowDown v-else-if="header.column.getIsSorted() === 'desc'" class="h-4 w-4 text-foreground" />
                                    <ArrowUpDown v-else class="h-3.5 w-3.5 opacity-40" />
                                </template>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="[&_tr:last-child]:border-0">
                    <tr
                        v-for="row in table.getRowModel().rows"
                        :key="row.id"
                        class="border-b transition-colors hover:bg-muted/50"
                    >
                        <td class="p-4 align-middle font-medium">
                            <div class="flex items-center gap-3">
                                <UserAvatar :user="row.original" size="sm" />
                                <div class="flex flex-col">
                                    <span>{{ row.getValue('name') }}</span>
                                    <span v-if="status === 'inactive'" class="text-xs text-red-500">Deletado em: {{ row.original.deleted_at }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="p-4 align-middle text-muted-foreground">{{ row.getValue('email') }}</td>
                        <td class="p-4 align-middle">
                            <Badge :variant="row.getValue('role') === 'admin' ? 'default' : 'secondary'">
                                {{ row.getValue('role') === 'admin' ? 'Admin' : 'Usuário' }}
                            </Badge>
                        </td>
                        <td class="p-4 align-middle text-muted-foreground">{{ row.getValue('created_at') }}</td>
                        <td class="p-4 align-middle text-right">
                            <div v-if="status === 'active'" class="flex items-center justify-end gap-1">
                                <Button variant="ghost" size="icon" @click="openEdit(row.original)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    v-if="!['aurora@pmbok.sys', 'prisma@pmbok.sys'].includes(row.original.email)"
                                    variant="ghost"
                                    size="icon"
                                    @click="openDelete(row.original)"
                                >
                                    <Trash2 class="h-4 w-4 text-red-500" />
                                </Button>
                            </div>
                            <div v-else class="flex items-center justify-end gap-1">
                                <Button variant="ghost" size="sm" @click="openRestore(row.original)" class="gap-2 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50">
                                    <RefreshCcw class="h-4 w-4" />
                                    Restaurar
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="table.getRowModel().rows.length === 0">
                        <td colspan="5" class="p-8 text-center text-muted-foreground">
                            {{ status === 'active' ? 'Nenhum usuário ativo encontrado.' : 'Nenhum usuário inativo encontrado.' }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="flex items-center justify-between border-t px-4 py-3">
                <p class="text-sm text-muted-foreground">
                    Mostrando {{ users.from || 0 }} a {{ users.to || 0 }} de {{ users.total }} resultados
                </p>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" :disabled="!users.prev_page_url" @click="goToPage(users.prev_page_url)">Anterior</Button>
                    <Button variant="outline" size="sm" :disabled="!users.next_page_url" @click="goToPage(users.next_page_url)">Próximo</Button>
                </div>
            </div>
        </div>

        <!-- ========== CREATE USER OFF-CANVAS ========== -->
        <div v-if="showCreatePanel" class="fixed inset-0 z-50" @click.self="closePanel">
            <div class="fixed inset-0 bg-black/50" @click="closePanel" />
            <div class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-xl border-l">
                <div class="flex items-center justify-between border-b px-6 py-4 shrink-0">
                    <h2 class="text-lg font-semibold text-gray-900">Novo Usuário</h2>
                    <button @click="closePanel" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <form @submit.prevent="submitCreate" class="space-y-5">
                        <!-- Avatar Upload -->
                        <div class="flex items-center gap-4">
                            <UserAvatar :user="createPreviewUser" size="lg" />
                            <div class="flex flex-col gap-2">
                                <input ref="createFileInput" type="file" accept="image/*" class="hidden" @change="onCreateAvatarSelected" />
                                <button type="button" @click="selectCreateAvatar" class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
                                    <Camera class="h-4 w-4" />
                                    Foto
                                </button>
                                <button v-if="createAvatarPreview" type="button" @click="removeCreateAvatar" class="inline-flex items-center gap-2 rounded-md border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50 transition-colors">
                                    <Trash2 class="h-3.5 w-3.5" />
                                    Remover
                                </button>
                            </div>
                            <p v-if="createForm.errors.avatar" class="text-sm text-red-500">{{ createForm.errors.avatar }}</p>
                        </div>

                        <div>
                            <label for="create-name" class="text-sm font-medium text-gray-700">Nome</label>
                            <input
                                id="create-name"
                                v-model="createForm.name"
                                type="text"
                                placeholder="Nome completo"
                                class="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <p v-if="createForm.errors.name" class="text-sm text-red-500 mt-1">{{ createForm.errors.name }}</p>
                        </div>
                        <div>
                            <label for="create-email" class="text-sm font-medium text-gray-700">E-mail</label>
                            <input
                                id="create-email"
                                v-model="createForm.email"
                                type="email"
                                placeholder="usuario@exemplo.com"
                                class="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <p v-if="createForm.errors.email" class="text-sm text-red-500 mt-1">{{ createForm.errors.email }}</p>
                        </div>
                        <div>
                            <label for="create-password" class="text-sm font-medium text-gray-700">Senha</label>
                            <input
                                id="create-password"
                                v-model="createForm.password"
                                type="password"
                                placeholder="Mínimo 6 caracteres"
                                class="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <p v-if="createForm.errors.password" class="text-sm text-red-500 mt-1">{{ createForm.errors.password }}</p>
                        </div>
                        <div>
                            <label for="create-role" class="text-sm font-medium text-gray-700">Função</label>
                            <select
                                id="create-role"
                                v-model="createForm.role"
                                class="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="user">Usuário</option>
                                <option value="admin">Admin</option>
                            </select>
                            <p v-if="createForm.errors.role" class="text-sm text-red-500 mt-1">{{ createForm.errors.role }}</p>
                        </div>
                        <div class="flex gap-3 pt-4 border-t">
                            <Button type="submit" class="flex-1" :disabled="createForm.processing">Criar Usuário</Button>
                            <Button type="button" variant="outline" @click="closePanel">Cancelar</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ========== EDIT USER OFF-CANVAS ========== -->
        <div v-if="showEditPanel" class="fixed inset-0 z-50" @click.self="closePanel">
            <div class="fixed inset-0 bg-black/50" @click="closePanel" />
            <div class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-xl border-l">
                <div class="flex items-center justify-between border-b px-6 py-4 shrink-0">
                    <h2 class="text-lg font-semibold text-gray-900">Editar Usuário</h2>
                    <button @click="closePanel" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <form @submit.prevent="submitEdit" class="space-y-5">
                        <!-- Avatar Upload -->
                        <div class="flex items-center gap-4">
                            <UserAvatar :user="editPreviewUser" size="lg" />
                            <div class="flex flex-col gap-2">
                                <input ref="editFileInput" type="file" accept="image/*" class="hidden" @change="onEditAvatarSelected" />
                                <button type="button" @click="selectEditAvatar" class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
                                    <Camera class="h-4 w-4" />
                                    Alterar Foto
                                </button>
                                <button v-if="selectedUser?.avatar_url || editAvatarPreview" type="button" @click="removeEditAvatar" class="inline-flex items-center gap-2 rounded-md border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50 transition-colors">
                                    <Trash2 class="h-3.5 w-3.5" />
                                    Remover Foto
                                </button>
                            </div>
                            <p v-if="editForm.errors.avatar" class="text-sm text-red-500">{{ editForm.errors.avatar }}</p>
                        </div>

                        <div>
                            <label for="edit-name" class="text-sm font-medium text-gray-700">Nome</label>
                            <input
                                id="edit-name"
                                v-model="editForm.name"
                                type="text"
                                class="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <p v-if="editForm.errors.name" class="text-sm text-red-500 mt-1">{{ editForm.errors.name }}</p>
                        </div>
                        <div>
                            <label for="edit-email" class="text-sm font-medium text-gray-700">E-mail</label>
                            <input
                                id="edit-email"
                                v-model="editForm.email"
                                type="email"
                                class="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <p v-if="editForm.errors.email" class="text-sm text-red-500 mt-1">{{ editForm.errors.email }}</p>
                        </div>
                        <div>
                            <label for="edit-password" class="text-sm font-medium text-gray-700">Senha (deixe vazio para manter)</label>
                            <input
                                id="edit-password"
                                v-model="editForm.password"
                                type="password"
                                placeholder="••••••"
                                class="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <p v-if="editForm.errors.password" class="text-sm text-red-500 mt-1">{{ editForm.errors.password }}</p>
                        </div>
                        <div>
                            <label for="edit-role" class="text-sm font-medium text-gray-700">Função</label>
                            <select
                                id="edit-role"
                                v-model="editForm.role"
                                class="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="user">Usuário</option>
                                <option value="admin">Admin</option>
                            </select>
                            <p v-if="editForm.errors.role" class="text-sm text-red-500 mt-1">{{ editForm.errors.role }}</p>
                        </div>
                        <div class="flex gap-3 pt-4 border-t">
                            <Button type="submit" class="flex-1" :disabled="editForm.processing">Salvar Alterações</Button>
                            <Button type="button" variant="outline" @click="closePanel">Cancelar</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ========== DELETE CONFIRMATION ========== -->
        <Dialog v-model:open="showDeleteDialog">
            <template #default="{ close }">
                <h2 class="text-lg font-semibold mb-2">Confirmar Exclusão</h2>
                <p class="text-sm text-muted-foreground mb-6">
                    Tem certeza que deseja desativar o usuário
                    <strong>{{ selectedUser?.name }}</strong>?
                </p>
                <div class="flex justify-end gap-3">
                    <Button type="button" variant="outline" @click="close">Cancelar</Button>
                    <Button variant="destructive" @click="submitDelete">Desativar</Button>
                </div>
            </template>
        </Dialog>

        <!-- ========== RESTORE CONFIRMATION ========== -->
        <Dialog v-model:open="showRestoreDialog">
            <template #default="{ close }">
                <h2 class="text-lg font-semibold mb-2">Confirmar Restauração</h2>
                <p class="text-sm text-muted-foreground mb-6">
                    Tem certeza que deseja restaurar o usuário
                    <strong>{{ selectedUser?.name }}</strong>?
                </p>
                <div class="flex justify-end gap-3">
                    <Button type="button" variant="outline" @click="close">Cancelar</Button>
                    <Button @click="submitRestore" class="bg-emerald-600 hover:bg-emerald-700 text-white">Restaurar</Button>
                </div>
            </template>
        </Dialog>

        <!-- ========== TOAST NOTIFICATION ========== -->
        <div
            v-if="showToast"
            class="fixed bottom-6 right-6 z-[100] flex items-center gap-3 rounded-lg border px-4 py-3 shadow-lg transition-all duration-300"
            :class="toastType === 'success'
                ? 'border-emerald-500/30 bg-emerald-50 text-emerald-800'
                : 'border-red-500/30 bg-red-50 text-red-800'"
        >
            <p class="text-sm font-medium">{{ toastMessage }}</p>
            <button @click="showToast = false" class="ml-2 rounded-md p-0.5 opacity-60 hover:opacity-100">
                <X class="h-4 w-4" />
            </button>
        </div>
    </AuthenticatedLayout>
</template>
