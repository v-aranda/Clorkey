<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, Users, Library, CalendarDays, Columns3, ChevronDown, LogOut, User } from 'lucide-vue-next';
import UserAvatar from '@/Components/UserAvatar.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value.role === 'admin');

const showUserMenu = ref(false);
const sidebarCollapsed = ref(false);

defineProps({
    noPadding: { type: Boolean, default: false },
});
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-50">
        <!-- Sidebar -->
        <aside class="flex w-64 flex-col bg-slate-900 text-white">
            <!-- Logo -->
            <div class="flex h-16 items-center gap-3 border-b border-slate-700 px-6">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-500 text-sm font-bold">
                    PM
                </div>
                <span class="text-lg font-semibold tracking-tight">PMBOK</span>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <!-- Agenda -->
                <Link :href="route('agenda.index')" :class="[
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                    route().current('agenda.*')
                        ? 'bg-slate-800 text-white'
                        : 'text-slate-300 hover:bg-slate-800 hover:text-white',
                ]">
                    <CalendarDays class="h-5 w-5" />
                    Agenda
                </Link>

                <!-- Quadro -->
                <Link :href="route('quadro.index')" :class="[
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                    route().current('quadro.*')
                        ? 'bg-slate-800 text-white'
                        : 'text-slate-300 hover:bg-slate-800 hover:text-white',
                ]">
                    <Columns3 class="h-5 w-5" />
                    Quadros
                </Link>

                <!-- Biblioteca -->
                <Link :href="route('library.index')" :class="[
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                    route().current('library.*')
                        ? 'bg-slate-800 text-white'
                        : 'text-slate-300 hover:bg-slate-800 hover:text-white',
                ]">
                    <Library class="h-5 w-5" />
                    Biblioteca
                </Link>



                <!-- Admin section -->
                <div v-if="isAdmin" class="pt-4">
                    <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Administração
                    </p>
                    <Link :href="route('admin.users.index')" :class="[
                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                        route().current('admin.users.*')
                            ? 'bg-slate-800 text-white'
                            : 'text-slate-300 hover:bg-slate-800 hover:text-white',
                    ]">
                        <Users class="h-5 w-5" />
                        Usuários
                    </Link>

                </div>
            </nav>

            <!-- User section at bottom -->
            <div class="relative border-t border-slate-700 p-3">
                <button @click="showUserMenu = !showUserMenu"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-slate-800">
                    <UserAvatar :user="user" size="sm" />
                    <div class="flex-1 text-left">
                        <p class="text-sm font-medium text-white">{{ user.name }}</p>
                        <p class="text-xs text-slate-400">{{ user.email }}</p>
                    </div>
                    <ChevronDown class="h-4 w-4 text-slate-400" />
                </button>

                <!-- User dropdown -->
                <div v-if="showUserMenu"
                    class="absolute bottom-full left-3 right-3 mb-1 rounded-lg border border-slate-700 bg-slate-800 py-1 shadow-lg">
                    <Link :href="route('profile.edit')"
                        class="flex items-center gap-2 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 hover:text-white">
                        <User class="h-4 w-4" />
                        Perfil
                    </Link>
                    <Link :href="route('logout')" method="post" as="button"
                        class="flex w-full items-center gap-2 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 hover:text-white">
                        <LogOut class="h-4 w-4" />
                        Sair
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- Top bar -->
            <header class="flex h-16 items-center border-b border-gray-200 bg-white px-6 shadow-sm">
                <slot name="header">
                    <h1 class="text-lg font-semibold text-gray-900">Dashboard</h1>
                </slot>
            </header>

            <!-- Page content -->
            <main :class="['flex-1 overflow-y-auto', noPadding ? 'custom-scrollbar' : 'p-6']">
                <slot />
            </main>
        </div>
    </div>
</template>
