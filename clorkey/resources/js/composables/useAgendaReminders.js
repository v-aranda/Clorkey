import { ref } from 'vue';
import axios from 'axios';

export function useAgendaReminders() {
    const reminders = ref([]);
    const loading = ref(false);

    async function fetchReminders(dateStr) {
        if (!dateStr) return;
        loading.value = true;
        try {
            const { data } = await axios.get(route('agenda.reminders.index'), {
                params: { date: dateStr },
            });
            reminders.value = data;
        } catch {
            reminders.value = [];
        } finally {
            loading.value = false;
        }
    }

    async function createReminder({ title, date, participants }) {
        const { data } = await axios.post(route('agenda.reminders.store'), {
            title,
            date,
            participants: participants?.length ? participants : null,
        });
        reminders.value.push(data);
        return data;
    }

    async function deleteReminder(id) {
        await axios.delete(route('agenda.reminders.destroy', id));
        reminders.value = reminders.value.filter(r => r.id !== id);
    }

    return { reminders, loading, fetchReminders, createReminder, deleteReminder };
}
