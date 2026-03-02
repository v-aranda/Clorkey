<script setup>
import { onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Columns3, List } from 'lucide-vue-next';
import { useQuadroStore } from '@/stores/quadroStore';
import KanbanView from './KanbanView.vue';
import TodoListView from './TodoListView.vue';
import TaskDetailPanel from '@/Pages/Agenda/TaskDetailPanel.vue';

const page = usePage();
const store = useQuadroStore();
const viewMode = ref('kanban'); // 'kanban' | 'list'

const selectedTask = ref(null);
const showDetailPanel = ref(false);

function openDetail(task) {
    selectedTask.value = task;
    showDetailPanel.value = true;
}

function closeDetail() {
    showDetailPanel.value = false;
    selectedTask.value = null;
}

function onTaskDeleted(taskId) {
    store.tasks = store.tasks.filter(t => t.id !== taskId);
    closeDetail();
}

onMounted(() => {
    store.setTasks(page.props.tasks || []);
});
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex w-full items-center justify-between">
                <div class="flex items-center gap-3">
                    <Columns3 class="h-6 w-6 text-gray-600" />
                    <h1 class="text-lg font-semibold text-gray-900">Quadros</h1>
                </div>

                <!-- View Toggle -->
                <div class="flex items-center rounded-lg border border-gray-200 bg-white p-1 shadow-sm">
                    <button @click="viewMode = 'kanban'" :class="[
                        'flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        viewMode === 'kanban'
                            ? 'bg-indigo-500 text-white shadow-sm'
                            : 'text-gray-600 hover:text-gray-900',
                    ]">
                        <Columns3 class="h-4 w-4" />
                        Kanban
                    </button>
                    <button @click="viewMode = 'list'" :class="[
                        'flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        viewMode === 'list'
                            ? 'bg-indigo-500 text-white shadow-sm'
                            : 'text-gray-600 hover:text-gray-900',
                    ]">
                        <List class="h-4 w-4" />
                        Lista
                    </button>
                </div>
            </div>
        </template>

        <!-- Content -->
        <div class="flex flex-1 overflow-hidden h-[calc(100vh-4rem)] -m-6">
            <div class="flex-1 overflow-hidden flex flex-col min-w-0 p-6">
                <KanbanView v-if="viewMode === 'kanban'" :users="page.props.users || []"
                    :active-task-id="selectedTask?.id" @open-detail="openDetail" />
                <TodoListView v-else :users="page.props.users || []" :active-task-id="selectedTask?.id"
                    @open-detail="openDetail" />
            </div>

            <!-- Task Detail Inner Panel -->
            <TaskDetailPanel :show="showDetailPanel" :task="selectedTask" :users="page.props.users || []"
                :current-user="page.props.auth?.user" @close="closeDetail" @deleted="onTaskDeleted" />
        </div>
    </AuthenticatedLayout>
</template>
