import { defineStore } from 'pinia';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

export const useAgendaStore = defineStore('agenda', () => {
    const tasks = ref([]);
    const participatingTasks = ref([]);
    const loadingParticipating = ref(false);
    const assignedTasks = ref([]);
    const loadingAssigned = ref(false);

    function setTasks(newTasks) {
        tasks.value = Array.isArray(newTasks) ? newTasks : [];
    }

    // Called on mount to hydrate from Inertia props without overwriting AJAX state
    function setInitialTasks(newTasks) {
        if (tasks.value.length === 0) {
            setTasks(newTasks);
        }
    }

    async function fetchTasksForDate(dateStr) {
        try {
            const resp = await axios.get(route('agenda.tasks.list'), { params: { date: dateStr } });
            setTasks(resp.data.tasks || []);
        } catch (e) {
            // ignore
        }
    }

    async function fetchParticipatingTasks() {
        if (participatingTasks.value.length > 0 || loadingParticipating.value) return;
        loadingParticipating.value = true;
        try {
            const resp = await axios.get(route('agenda.tasks.participating'));
            participatingTasks.value = resp.data.tasks || [];
        } catch (e) {
            participatingTasks.value = [];
        } finally {
            loadingParticipating.value = false;
        }
    }

    function setAssignedTasks(newTasks) {
        assignedTasks.value = Array.isArray(newTasks) ? newTasks : [];
    }

    async function fetchAssignedTasks({ force = false } = {}) {
        if (!force && (assignedTasks.value.length > 0 || loadingAssigned.value)) return;
        loadingAssigned.value = true;
        try {
            const resp = await axios.get(route('agenda.tasks.assigned'));
            setAssignedTasks(resp.data.tasks || []);
        } catch (e) {
            setAssignedTasks([]);
        } finally {
            loadingAssigned.value = false;
        }
    }

    function upsertAssignedTask(task) {
        if (!task?.id) return;
        const idx = assignedTasks.value.findIndex((item) => item.id === task.id);
        if (idx >= 0) {
            assignedTasks.value[idx] = task;
            return;
        }
        assignedTasks.value.unshift(task);
    }

    function removeAssignedTask(taskId) {
        assignedTasks.value = assignedTasks.value.filter((task) => task.id !== taskId);
    }

    function upsertScheduledTask(task) {
        if (!task?.id) return;
        const idx = tasks.value.findIndex((item) => item.id === task.id);
        if (idx >= 0) {
            tasks.value[idx] = task;
        } else {
            tasks.value.push(task);
        }

        tasks.value.sort((a, b) => {
            if (a.start_time === b.start_time) return Number(a.id) - Number(b.id);
            return (a.start_time || '').localeCompare(b.start_time || '');
        });
    }

    function removeScheduledTask(taskId) {
        tasks.value = tasks.value.filter((task) => task.id !== taskId);
    }

    async function updateTask(taskId, payload = {}) {
        const resp = await axios.patch(route('agenda.tasks.update', taskId), payload, {
            headers: { Accept: 'application/json' },
        });

        return resp.data?.task || null;
    }

    function deleteTask(taskId, { onSuccess } = {}) {
        const deleteForm = useForm({});
        deleteForm.delete(route('agenda.tasks.destroy', taskId), {
            preserveScroll: true,
            onSuccess: () => {
                tasks.value = tasks.value.filter(t => t.id !== taskId);
                if (onSuccess) onSuccess();
            },
        });
        return deleteForm;
    }

    return {
        tasks,
        participatingTasks,
        loadingParticipating,
        assignedTasks,
        loadingAssigned,
        setTasks,
        setInitialTasks,
        fetchTasksForDate,
        fetchParticipatingTasks,
        setAssignedTasks,
        fetchAssignedTasks,
        upsertAssignedTask,
        removeAssignedTask,
        upsertScheduledTask,
        removeScheduledTask,
        updateTask,
        deleteTask,
    };
});
