import { defineStore } from 'pinia';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

export const useAgendaStore = defineStore('agenda', () => {
    const tasks = ref([]);
    const participatingTasks = ref([]);
    const loadingParticipating = ref(false);

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
        setTasks,
        setInitialTasks,
        fetchTasksForDate,
        fetchParticipatingTasks,
        deleteTask,
    };
});
