<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, X } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAgendaClock } from '@/composables/useAgendaClock';
import { useAgendaCalendar } from '@/composables/useAgendaCalendar';
import { useToast } from '@/composables/useToast';
import { useAgendaStore } from '@/stores/agendaStore';
import { useAgendaHolidays } from '@/composables/useAgendaHolidays';
import { useAgendaReminders } from '@/composables/useAgendaReminders';
import ScheduleTimeline from './ScheduleTimeline.vue';
import SidePanel from './SidePanel.vue';
import TaskDetailPanel from './TaskDetailPanel.vue';
import TaskCreatePanel from './TaskCreatePanel.vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
});

const page = usePage();

// ─── Store & Composables ─────────────────────────────────────────────────────

const agendaStore = useAgendaStore();

const {
    hourString, minuteString, weekdayShort, dayMonthLabel,
    currentHour, period, periodIcon, theme, isNight, nowLinePercent,
} = useAgendaClock();

const {
    calendarDate, monthLabel, weekdayString, weekDayLabels, calendarWeeks,
    prevMonth, nextMonth, selectCalendarDay, selectToday,
    prevDay, nextDay,
    formatDateToYMD,
} = useAgendaCalendar(agendaStore);

const { showToast, toastMessage, triggerToast } = useToast();

// ─── Holidays & Reminders ────────────────────────────────────────────────────

const { ensureYear, getHolidaysForDate, getHolidayDatesForYear } = useAgendaHolidays();
const { reminders, loading: remindersLoading, fetchReminders, createReminder, deleteReminder } = useAgendaReminders();

// Holidays for the selected date
const holidaysForDay = computed(() => getHolidaysForDate(formatDateToYMD(calendarDate.value)));

// Dot dates for the current month view (holidays + reminder dates)
const reminderDotDates = computed(() => {
    const year = calendarDate.value.getFullYear();
    const holidayDates = getHolidayDatesForYear(year);
    const reminderDates = new Set(reminders.value.map(r => r.date));
    return new Set([...holidayDates, ...reminderDates]);
});

// Fetch reminders + ensure holidays whenever selected date changes
watch(calendarDate, (date) => {
    const dateStr = formatDateToYMD(date);
    fetchReminders(dateStr);
    ensureYear(date.getFullYear());
}, { immediate: true });

async function handleReminderCreated(data) {
    await createReminder(data);
}

async function handleReminderDeleted(id) {
    await deleteReminder(id);
}

// ─── Hydrate store from Inertia props ────────────────────────────────────────

onMounted(() => { agendaStore.setInitialTasks(props.tasks); });
watch(() => props.tasks, (v) => agendaStore.setTasks(v), { deep: true });

// ─── UI state ────────────────────────────────────────────────────────────────

const showOffcanvas = ref(false);
const activeOffcanvasTab = ref('calendar');

const showTaskDetail = ref(false);
const detailTask = ref(null);

const showTaskPanel = ref(false);
const selectedHour = ref(null);
const panelTask = ref(null);
const panelRequireDateTime = ref(true);
const panelContext = ref('default');

// ─── Current date label for the header ───────────────────────────────────────

const headerDateLabel = computed(() => {
    const dateStr = formatDateToYMD(calendarDate.value);
    if (!dateStr) return '';
    const parts = dateStr.split('-').map(Number);
    if (parts.length !== 3) return dateStr;
    const [year, month, day] = parts;
    if (!year || !month || !day) return dateStr;
    return new Intl.DateTimeFormat('pt-BR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
});

const isTodaySelected = computed(() => {
    const selected = calendarDate.value instanceof Date
        ? calendarDate.value
        : new Date(calendarDate.value);
    if (Number.isNaN(selected.getTime())) return true;

    const today = new Date();
    const selectedKey = new Date(selected.getFullYear(), selected.getMonth(), selected.getDate()).getTime();
    const todayKey = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime();
    return selectedKey === todayKey;
});

// ─── Event handlers ───────────────────────────────────────────────────────────

function openTaskPanel(hour) {
    panelTask.value = null;
    panelRequireDateTime.value = true;
    panelContext.value = 'default';
    selectedHour.value = hour;
    showTaskPanel.value = true;
}

function openAssignedTaskCreatePanel() {
    panelTask.value = null;
    panelRequireDateTime.value = false;
    panelContext.value = 'assigned';
    selectedHour.value = null;
    showTaskPanel.value = true;
}

function openAssignedTaskPanel(task) {
    panelTask.value = task;
    panelRequireDateTime.value = false;
    panelContext.value = 'assigned';
    selectedHour.value = null;
    showTaskPanel.value = true;
}

function openTaskDetail(task) {
    detailTask.value = task;
    showTaskDetail.value = true;
}

function closeTaskDetail() {
    showTaskDetail.value = false;
    detailTask.value = null;
}

function handleTaskDeleted(taskId) {
    agendaStore.tasks = agendaStore.tasks.filter(t => t.id !== taskId);
    triggerToast('Tarefa removida com sucesso.');
}

function onTodoListRequested() {
    agendaStore.fetchAssignedTasks();
}

async function handleTaskSaved() {
    await agendaStore.fetchAssignedTasks({ force: true });
    await agendaStore.fetchTasksForDate(formatDateToYMD(calendarDate.value));
}

async function handleAssignedTaskDropped(payload) {
    const taskId = Number(payload?.taskId);
    if (!taskId || !payload?.date || !payload?.start_time) return;

    const sourceTask = agendaStore.assignedTasks.find((item) => Number(item.id) === taskId);
    if (!sourceTask) return;

    const previousAssigned = [...agendaStore.assignedTasks];
    const previousTimeline = [...agendaStore.tasks];

    const optimisticTask = {
        ...sourceTask,
        date: payload.date,
        start_time: payload.start_time,
    };

    agendaStore.removeAssignedTask(taskId);
    agendaStore.upsertScheduledTask(optimisticTask);

    try {
        const updatedTask = await agendaStore.updateTask(taskId, {
            date: payload.date,
            start_time: payload.start_time,
            context: 'drag_drop',
        });

        if (!updatedTask) {
            throw new Error('Resposta inválida ao atualizar tarefa.');
        }

        if (updatedTask.date && updatedTask.start_time) {
            agendaStore.removeAssignedTask(taskId);
            agendaStore.upsertScheduledTask(updatedTask);
        } else {
            agendaStore.removeScheduledTask(taskId);
            agendaStore.upsertAssignedTask(updatedTask);
        }

        triggerToast('Tarefa agendada com sucesso.');
    } catch (error) {
        agendaStore.setAssignedTasks(previousAssigned);
        agendaStore.setTasks(previousTimeline);
        triggerToast('Não foi possível agendar a tarefa. Tente novamente.');
    }
}
</script>

<template>

    <Head title="Agenda" />

    <AuthenticatedLayout>
        <!-- Header: period icon + weekday + clock -->
        <template #header>
            <div class="grid w-full grid-cols-[1fr_auto_1fr] items-center">
                <div class="flex items-center">
                    <div
                        :class="['flex h-9 w-9 items-center justify-center rounded-full transition-colors duration-500', theme.iconBg]">
                        <component :is="periodIcon"
                            :class="['h-4.5 w-4.5 transition-colors duration-500', theme.iconText]" />
                    </div>
                </div>

                <div class="flex flex-col items-center">
                    <h1 class="text-center text-lg font-semibold capitalize text-gray-900">{{ weekdayString }}</h1>
                    <div class="flex items-center gap-1 text-sm text-gray-500">
                        <button type="button"
                            class="rounded p-0.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
                            title="Dia anterior" aria-label="Dia anterior" @click="prevDay">
                            <ChevronLeft class="h-4 w-4" />
                        </button>
                        <p>{{ headerDateLabel }}</p>
                        <button type="button"
                            class="rounded p-0.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
                            title="Próximo dia" aria-label="Próximo dia" @click="nextDay">
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button v-if="!isTodaySelected" type="button"
                        class="inline-flex items-center rounded-md border border-input bg-background px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50"
                        @click="selectToday">
                        Hoje
                    </button>
                </div>
            </div>
        </template>

        <!-- Content: [schedule | side panel] -->
        <div class="flex -m-6 relative overflow-hidden" style="height: calc(100vh - 4rem)">

            <!-- Schedule area -->
            <div :class="['flex flex-1 flex-col min-w-0 transition-colors duration-700', theme.pageBg]">
                <ScheduleTimeline :tasks="agendaStore.tasks" :users="users" :theme="theme" :current-hour="currentHour"
                    :now-line-percent="nowLinePercent" :is-night="isNight" :reminders="reminders"
                    :active-task-id="detailTask?.id" :holidays="holidaysForDay"
                    :selected-date="formatDateToYMD(calendarDate)" @open-task-panel="openTaskPanel"
                    @open-task-detail="openTaskDetail" @schedule-assigned-task="handleAssignedTaskDropped" />
            </div>

            <!-- Side panel -->
            <SidePanel v-model:show="showOffcanvas" v-model:activeTab="activeOffcanvasTab" :theme="theme"
                :assigned-tasks="agendaStore.assignedTasks" :loading-assigned="agendaStore.loadingAssigned"
                :users="users" :calendar-date="calendarDate" :month-label="monthLabel" :calendar-weeks="calendarWeeks"
                :week-day-labels="weekDayLabels" :hour-string="hourString" :minute-string="minuteString"
                :weekday-short="weekdayShort" :day-month-label="dayMonthLabel" :period="period"
                :selected-date="formatDateToYMD(calendarDate)" :reminders="reminders" :holidays="holidaysForDay"
                :reminder-dot-dates="reminderDotDates" :reminders-loading="remindersLoading"
                :current-user-id="page.props.auth.user?.id" @open-assigned-list="onTodoListRequested"
                @create-task="openAssignedTaskCreatePanel" @open-task-form="openAssignedTaskPanel"
                @select-calendar-day="selectCalendarDay" @select-today="selectToday" @prev-month="prevMonth"
                @next-month="nextMonth" @reminder-created="handleReminderCreated"
                @reminder-deleted="handleReminderDeleted" />

            <!-- Task Detail Inner Panel (Absolute to overlay SidePanel without pushing) -->
            <div class="absolute inset-y-0 right-0 z-40 flex shadow-2xl bg-white border-l border-gray-200">
                <TaskDetailPanel :show="showTaskDetail" :task="detailTask" :users="users"
                    :current-user="page.props.auth.user" @close="closeTaskDetail" @deleted="handleTaskDeleted" />
            </div>
        </div>

        <!-- Task Create Offcanvas -->
        <TaskCreatePanel :show="showTaskPanel" :users="users" :current-user-id="page.props.auth.user?.id"
            :initial-hour="selectedHour" :initial-date="formatDateToYMD(calendarDate)" :task="panelTask"
            :require-date-time="panelRequireDateTime" :context="panelContext" @close="showTaskPanel = false"
            @created="handleTaskSaved" @updated="handleTaskSaved" />

        <!-- Toast -->
        <div v-if="showToast"
            class="fixed bottom-6 right-6 z-[100] flex items-center gap-3 rounded-lg border border-emerald-500/30 bg-emerald-50 text-emerald-800 px-4 py-3 shadow-lg transition-all duration-300">
            <p class="text-sm font-medium">{{ toastMessage }}</p>
            <button @click="showToast = false" class="ml-2 rounded-md p-0.5 opacity-60 hover:opacity-100">
                <X class="h-4 w-4" />
            </button>
        </div>

    </AuthenticatedLayout>
</template>
