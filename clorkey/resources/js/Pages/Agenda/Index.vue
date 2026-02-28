<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
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
    formatDateToYMD, formatDateLabel,
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

// ─── Current date label for the header ───────────────────────────────────────

const headerDateLabel = computed(() =>
    formatDateLabel(formatDateToYMD(calendarDate.value))
);

// ─── Event handlers ───────────────────────────────────────────────────────────

function openTaskPanel(hour) {
    selectedHour.value = hour;
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
    agendaStore.fetchParticipatingTasks();
}
</script>

<template>
    <Head title="Agenda" />

    <AuthenticatedLayout>
        <!-- Header: period icon + weekday + clock -->
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-3">
                    <div :class="['flex h-9 w-9 items-center justify-center rounded-full transition-colors duration-500', theme.iconBg]">
                        <component :is="periodIcon" :class="['h-4.5 w-4.5 transition-colors duration-500', theme.iconText]" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold capitalize text-gray-900">{{ weekdayString }}</h1>
                        <p class="text-sm text-gray-500">{{ headerDateLabel }}</p>
                    </div>
                </div>
            </div>
        </template>

        <!-- Content: [schedule | side panel] -->
        <div class="flex -m-6" style="height: calc(100vh - 4rem)">

            <!-- Schedule area -->
            <div :class="['flex flex-1 flex-col min-w-0 transition-colors duration-700', theme.pageBg]">
                <ScheduleTimeline
                    :tasks="agendaStore.tasks"
                    :users="users"
                    :theme="theme"
                    :current-hour="currentHour"
                    :now-line-percent="nowLinePercent"
                    :is-night="isNight"
                    :reminders="reminders"
                    :holidays="holidaysForDay"
                    @open-task-panel="openTaskPanel"
                    @open-task-detail="openTaskDetail"
                />
            </div>

            <!-- Side panel -->
            <SidePanel
                v-model:show="showOffcanvas"
                v-model:activeTab="activeOffcanvasTab"
                :theme="theme"
                :participating-tasks="agendaStore.participatingTasks"
                :loading-participating="agendaStore.loadingParticipating"
                :users="users"
                :calendar-date="calendarDate"
                :month-label="monthLabel"
                :calendar-weeks="calendarWeeks"
                :week-day-labels="weekDayLabels"
                :hour-string="hourString"
                :minute-string="minuteString"
                :weekday-short="weekdayShort"
                :day-month-label="dayMonthLabel"
                :period="period"
                :selected-date="formatDateToYMD(calendarDate)"
                :reminders="reminders"
                :holidays="holidaysForDay"
                :reminder-dot-dates="reminderDotDates"
                :reminders-loading="remindersLoading"
                :current-user-id="page.props.auth.user?.id"
                @open-todo-list="onTodoListRequested"
                @open-task-detail="openTaskDetail"
                @select-calendar-day="selectCalendarDay"
                @select-today="selectToday"
                @prev-month="prevMonth"
                @next-month="nextMonth"
                @reminder-created="handleReminderCreated"
                @reminder-deleted="handleReminderDeleted"
            />

        </div>

        <!-- Task Detail Offcanvas -->
        <TaskDetailPanel
            :show="showTaskDetail"
            :task="detailTask"
            :users="users"
            :current-user="page.props.auth.user"
            @close="closeTaskDetail"
            @deleted="handleTaskDeleted"
        />

        <!-- Task Create Offcanvas -->
        <TaskCreatePanel
            :show="showTaskPanel"
            :users="users"
            :initial-hour="selectedHour"
            :initial-date="formatDateToYMD(calendarDate)"
            @close="showTaskPanel = false"
        />

        <!-- Toast -->
        <div
            v-if="showToast"
            class="fixed bottom-6 right-6 z-[100] flex items-center gap-3 rounded-lg border border-emerald-500/30 bg-emerald-50 text-emerald-800 px-4 py-3 shadow-lg transition-all duration-300"
        >
            <p class="text-sm font-medium">{{ toastMessage }}</p>
            <button @click="showToast = false" class="ml-2 rounded-md p-0.5 opacity-60 hover:opacity-100">
                <X class="h-4 w-4" />
            </button>
        </div>

    </AuthenticatedLayout>
</template>
