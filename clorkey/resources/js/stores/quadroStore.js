import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useQuadroStore = defineStore('quadro', () => {
    const tasks = ref([]);
    const loading = ref(false);

    /**
     * All tasks sorted by sort_order (ascending).
     * This is the single source of truth for ordering,
     * used by both the List and Kanban views.
     */
    const sortedTasks = computed(() =>
        [...tasks.value].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)),
    );

    /**
     * Tasks grouped by status. Within each group, items
     * preserve the sort_order ordering from sortedTasks.
     */
    const tasksByStatus = computed(() => {
        const grouped = { todo: [], doing: [], stopped: [], done: [] };
        for (const task of sortedTasks.value) {
            const status = task.status || 'todo';
            if (grouped[status]) {
                grouped[status].push(task);
            } else {
                grouped.todo.push(task);
            }
        }
        return grouped;
    });

    function setTasks(newTasks) {
        tasks.value = Array.isArray(newTasks) ? newTasks : [];
    }

    async function fetchTasks() {
        loading.value = true;
        try {
            const resp = await axios.get(route('quadro.tasks'));
            setTasks(resp.data.tasks || []);
        } catch (e) {
            console.error('Failed to fetch quadro tasks', e);
        } finally {
            loading.value = false;
        }
    }

    async function updateTaskStatus(taskId, newStatus) {
        const task = tasks.value.find(t => t.id === taskId);
        if (!task) return;
        if (task.status === newStatus) return;

        const oldStatus = task.status;
        // Optimistic update
        task.status = newStatus;

        try {
            await axios.patch(route('agenda.tasks.update', taskId), {
                status: newStatus,
                context: 'quadro_status',
            }, {
                headers: { Accept: 'application/json' },
            });
        } catch (e) {
            // Rollback on error
            task.status = oldStatus;
            console.error('Failed to update task status', e);
            throw e;
        }
    }

    /**
     * Persist the new sort_order after a drag-and-drop reorder.
     * Receives the full reordered array (with updated sort_order values).
     *
     * Strategy:
     *  - Assigns gap-based integers (increment of 1000) to each position.
     *  - Sends only the changed items to the server.
     *  - Rolls back on error.
     */
    async function reorderTasks(reorderedList) {
        const GAP = 1000;
        const snapshot = tasks.value.map(t => ({ id: t.id, sort_order: t.sort_order }));

        // Calculate new sort_order values with consistent gaps
        const items = reorderedList.map((task, index) => ({
            id: task.id,
            sort_order: (index + 1) * GAP,
        }));

        // Optimistic update: apply new sort_order to local state
        for (const item of items) {
            const task = tasks.value.find(t => t.id === item.id);
            if (task) task.sort_order = item.sort_order;
        }

        try {
            await axios.post(route('quadro.reorder'), { items }, {
                headers: { Accept: 'application/json' },
            });
        } catch (e) {
            // Rollback on error
            for (const snap of snapshot) {
                const task = tasks.value.find(t => t.id === snap.id);
                if (task) task.sort_order = snap.sort_order;
            }
            console.error('Failed to persist reorder', e);
            throw e;
        }
    }

    return {
        tasks,
        loading,
        sortedTasks,
        tasksByStatus,
        setTasks,
        fetchTasks,
        updateTaskStatus,
        reorderTasks,
    };
});
