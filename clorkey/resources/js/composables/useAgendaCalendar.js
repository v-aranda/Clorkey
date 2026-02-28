import { ref, computed, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Inertia } from '@inertiajs/inertia';
import axios from 'axios';

export function useAgendaCalendar(agendaStore) {
    const calendarDate = ref(new Date());

    // ─── Date helpers ────────────────────────────────────────────────────────

    function formatDateToYMD(d) {
        if (!d) return '';
        if (typeof d === 'string') return d;
        const dt = (d instanceof Date) ? d : new Date(d);
        if (Number.isNaN(dt.getTime())) return '';
        const year = dt.getFullYear();
        const month = String(dt.getMonth() + 1).padStart(2, '0');
        const day = String(dt.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function formatDateLabel(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-').map(Number);
        if (parts.length !== 3) return dateStr;
        const [year, month, day] = parts;
        if (!year || !month || !day) return dateStr;
        return new Intl.DateTimeFormat('pt-BR', {
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
        }).format(new Date(year, month - 1, day));
    }

    function formatTimeLabel(timeStr) {
        if (!timeStr) return '--:--';
        return timeStr.slice(0, 5);
    }

    // ─── Calendar computed ───────────────────────────────────────────────────

    const monthLabel = computed(() =>
        new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' })
            .format(calendarDate.value)
    );

    const weekdayString = computed(() =>
        new Intl.DateTimeFormat('pt-BR', { weekday: 'long' })
            .format(calendarDate.value)
    );

    const weekDayLabels = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

    const calendarDays = computed(() => {
        const year = calendarDate.value.getFullYear();
        const month = calendarDate.value.getMonth();
        const firstDay = new Date(year, month, 1);
        let startDow = firstDay.getDay();
        startDow = startDow === 0 ? 6 : startDow - 1;

        const days = [];
        for (let i = startDow - 1; i >= 0; i--) {
            days.push({ date: new Date(year, month, -i), currentMonth: false });
        }
        const lastDate = new Date(year, month + 1, 0).getDate();
        for (let i = 1; i <= lastDate; i++) {
            days.push({ date: new Date(year, month, i), currentMonth: true });
        }
        const remaining = 42 - days.length;
        for (let i = 1; i <= remaining; i++) {
            days.push({ date: new Date(year, month + 1, i), currentMonth: false });
        }
        return days;
    });

    const calendarWeeks = computed(() => {
        const weeks = [];
        const days = calendarDays.value;
        for (let i = 0; i < days.length; i += 7) {
            weeks.push(days.slice(i, i + 7));
        }
        return weeks;
    });

    // ─── Navigation ──────────────────────────────────────────────────────────

    function prevMonth() {
        const d = new Date(calendarDate.value);
        d.setMonth(d.getMonth() - 1);
        calendarDate.value = d;
    }

    function nextMonth() {
        const d = new Date(calendarDate.value);
        d.setMonth(d.getMonth() + 1);
        calendarDate.value = d;
    }

    // ─── Date selection ──────────────────────────────────────────────────────

    function selectCalendarDay(date) {
        calendarDate.value = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const dateStr = formatDateToYMD(calendarDate.value);
        axios.get(route('agenda.tasks.list'), { params: { date: dateStr } })
            .then(resp => {
                agendaStore.setTasks(resp.data.tasks || []);
                const base = route('agenda.index');
                history.replaceState(null, '', `${base}?date=${dateStr}`);
            })
            .catch(() => {
                Inertia.get(route('agenda.index'), { date: dateStr }, { preserveState: true, only: ['tasks'] });
            });
    }

    function selectToday() {
        const nowLocal = new Date();
        const today = new Date(nowLocal.getFullYear(), nowLocal.getMonth(), nowLocal.getDate());
        selectCalendarDay(today);
    }

    // ─── Helpers for template ─────────────────────────────────────────────────

    function isToday(date) {
        const t = new Date();
        return date.getDate() === t.getDate() &&
            date.getMonth() === t.getMonth() &&
            date.getFullYear() === t.getFullYear();
    }

    function isSelected(date) {
        if (!calendarDate.value) return false;
        const cd = calendarDate.value instanceof Date ? calendarDate.value : new Date(calendarDate.value);
        return date.getDate() === cd.getDate() &&
            date.getMonth() === cd.getMonth() &&
            date.getFullYear() === cd.getFullYear();
    }

    // ─── URL sync on mount ───────────────────────────────────────────────────

    onMounted(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.has('date')) {
            const d = params.get('date');
            if (d) {
                const parts = d.split('-').map(Number);
                if (parts.length === 3 && parts.every(n => Number.isInteger(n))) {
                    const [y, m, day] = parts;
                    const local = new Date(y, m - 1, day);
                    if (!Number.isNaN(local.getTime())) calendarDate.value = local;
                }
            }
        } else {
            const dateStr = formatDateToYMD(calendarDate.value);
            axios.get(route('agenda.tasks.list'), { params: { date: dateStr } })
                .then(resp => {
                    agendaStore.setTasks(resp.data.tasks || []);
                    history.replaceState(null, '', `${route('agenda.index')}?date=${dateStr}`);
                })
                .catch(() => {});
        }

        const page = usePage();
        watch(() => page.url, (newUrl) => {
            if (!newUrl) return;
            try {
                const parsed = new URL(newUrl, window.location.origin);
                const qs = new URLSearchParams(parsed.search);
                if (qs.has('date')) {
                    const d = qs.get('date');
                    if (d) {
                        const parts = d.split('-').map(Number);
                        if (parts.length === 3 && parts.every(n => Number.isInteger(n))) {
                            const [y, m, day] = parts;
                            const local = new Date(y, m - 1, day);
                            if (!Number.isNaN(local.getTime())) calendarDate.value = local;
                        }
                    }
                }
            } catch (e) {}
        });
    });

    return {
        calendarDate,
        monthLabel,
        weekdayString,
        weekDayLabels,
        calendarWeeks,
        prevMonth,
        nextMonth,
        selectCalendarDay,
        selectToday,
        isToday,
        isSelected,
        formatDateToYMD,
        formatDateLabel,
        formatTimeLabel,
    };
}
