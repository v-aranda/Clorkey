<script setup>
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { BarChart2, Columns3, List, Users } from 'lucide-vue-next';
import { useQuadroStore } from '@/stores/quadroStore';
import KanbanView from './KanbanView.vue';
import TodoListView from './TodoListView.vue';
import GanttView from './GanttView.vue';
import TaskDetailPanel from '@/Pages/Agenda/TaskDetailPanel.vue';

const page = usePage();
const store = useQuadroStore();
const viewMode = ref('kanban'); // 'kanban' | 'list' | 'gantt'

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

function onTaskDeleted(taskIds) {
    const ids = (Array.isArray(taskIds) ? taskIds : [taskIds])
        .map((id) => Number(id))
        .filter((id) => !Number.isNaN(id));

    store.tasks = store.tasks.filter((task) => !ids.includes(Number(task.id)));
    closeDetail();
}

const participantOptions = computed(() => {
    const allUsers = page.props.users || [];
    const usedIds = new Set();
    for (const task of store.tasks) {
        if (task.creator?.id) usedIds.add(Number(task.creator.id));
        for (const id of task.participants ?? []) usedIds.add(Number(id));
    }
    return allUsers.filter(u => usedIds.has(Number(u.id)));
});

function onParticipantChange(e) {
    const val = e.target.value;
    store.setParticipantFilter(val !== '' ? Number(val) : null);
}

onMounted(() => {
    store.setTasks(page.props.tasks || []);
});
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex w-full items-center justify-between gap-4">
                <div class="flex items-center gap-3 shrink-0">
                    <Columns3 class="h-6 w-6 text-gray-600" />
                    <h1 class="text-lg font-semibold text-gray-900">Quadros</h1>
                </div>

                <div class="flex items-center gap-3 ml-auto">
                    <!-- Participant filter -->
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 shadow-sm">
                        <Users class="h-4 w-4 text-gray-400 shrink-0" />
                        <select
                            :value="store.selectedParticipantId ?? ''"
                            @change="onParticipantChange"
                            class="text-sm text-gray-700 bg-transparent border-none outline-none cursor-pointer pr-1"
                        >
                            <option value="">Todos os participantes</option>
                            <option v-for="u in participantOptions" :key="u.id" :value="u.id">
                                {{ u.name }}
                            </option>
                        </select>
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
                        <button @click="viewMode = 'gantt'" :class="[
                            'flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            viewMode === 'gantt'
                                ? 'bg-indigo-500 text-white shadow-sm'
                                : 'text-gray-600 hover:text-gray-900',
                        ]">
                            <BarChart2 class="h-4 w-4" />
                            Gantt
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Content -->
        <div class="flex flex-1 overflow-hidden h-[calc(100vh-4rem)] -m-6">
            <div class="flex-1 overflow-hidden flex flex-col min-w-0 p-6">
                <KanbanView v-if="viewMode === 'kanban'" :users="page.props.users || []"
                    :active-task-id="selectedTask?.id" @open-detail="openDetail" />
                <TodoListView v-else-if="viewMode === 'list'" :users="page.props.users || []"
                    :active-task-id="selectedTask?.id" @open-detail="openDetail" />
                <GanttView v-else :users="page.props.users || []" @task-click="openDetail" />
            </div>

            <!-- Task Detail Inner Panel -->
            <TaskDetailPanel :show="showDetailPanel" :task="selectedTask" :users="page.props.users || []"
                :current-user="page.props.auth?.user" @close="closeDetail" @deleted="onTaskDeleted" />
        </div>
    </AuthenticatedLayout>
</template>
