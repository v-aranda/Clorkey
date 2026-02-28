<script setup>
import { CalendarDays, Users, X } from 'lucide-vue-next';
import MiniCalendar from './MiniCalendar.vue';
import ParticipatingTasksList from './ParticipatingTasksList.vue';
import ReminderList from './ReminderList.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    activeTab: { type: String, default: 'calendar' }, // 'calendar' | 'todolist'
    theme: { type: Object, required: true },
    participatingTasks: { type: Array, default: () => [] },
    loadingParticipating: { type: Boolean, default: false },
    users: { type: Array, default: () => [] },
    // MiniCalendar props
    calendarDate: { type: Date, required: true },
    monthLabel: { type: String, required: true },
    calendarWeeks: { type: Array, required: true },
    weekDayLabels: { type: Array, required: true },
    hourString: { type: String, required: true },
    minuteString: { type: String, required: true },
    weekdayShort: { type: String, required: true },
    dayMonthLabel: { type: String, required: true },
    period: { type: String, required: true },
    // Reminders props
    selectedDate: { type: String, default: '' },
    reminders: { type: Array, default: () => [] },
    holidays: { type: Array, default: () => [] },
    reminderDotDates: { type: Object, default: () => new Set() },
    remindersLoading: { type: Boolean, default: false },
    currentUserId: { type: Number, default: null },
});

const emit = defineEmits([
    'update:show',
    'update:activeTab',
    'open-todo-list',
    'open-task-detail',
    'select-calendar-day',
    'select-today',
    'prev-month',
    'next-month',
    'reminder-created',
    'reminder-deleted',
]);

function openCalendar() {
    emit('update:activeTab', 'calendar');
    emit('update:show', true);
}

function openTodoList() {
    emit('update:activeTab', 'todolist');
    emit('update:show', true);
    emit('open-todo-list');
}
</script>

<template>
    <div class="relative shrink-0 flex">

        <!-- External toggle buttons -->
        <div class="absolute -left-11 top-14 z-30 flex flex-col items-center gap-2">
            <template v-if="!show">
                <button
                    @click="openCalendar"
                    :class="[
                        'flex h-11 w-11 items-center justify-center rounded-l-2xl border border-r-0 shadow transition-all duration-300',
                        'border-transparent text-white', theme.iconBg
                    ]"
                    title="Calendário"
                    aria-label="Abrir calendário"
                >
                    <CalendarDays class="h-5 w-5" />
                </button>

                <button
                    @click="openTodoList"
                    :class="[
                        'flex h-11 w-11 items-center justify-center rounded-l-2xl border border-r-0 shadow transition-all duration-300',
                        'border-transparent text-white', theme.iconBg
                    ]"
                    title="Minha Lista"
                    aria-label="Abrir minha lista"
                >
                    <Users class="h-5 w-5" />
                </button>
            </template>
            <template v-else>
                <button
                    @click="$emit('update:show', false)"
                    class="flex h-11 w-11 items-center justify-center rounded-l-2xl border border-r-0 shadow transition-all duration-300 bg-white text-gray-400"
                    title="Fechar painel"
                    aria-label="Fechar painel"
                >
                    <X class="h-5 w-5" />
                </button>
            </template>
        </div>

        <!-- Collapsible panel -->
        <div
            :class="[
                'flex flex-col bg-white transition-all duration-200 overflow-hidden h-full',
                show ? 'w-80 border-l border-gray-200' : 'w-0'
            ]"
        >
            <div class="flex w-80 shrink-0 flex-col h-full">

                <!-- Panel header (icon-bar) -->
                <div class="flex shrink-0 items-center gap-1 border-b border-gray-100 px-3 py-3">
                    <button
                        @click="$emit('update:activeTab', 'calendar')"
                        :class="[
                            'rounded-md p-2 transition-colors duration-500',
                            activeTab === 'calendar' ? theme.panelIconBg : 'bg-white',
                            activeTab === 'calendar' ? theme.panelIconText : 'text-gray-400'
                        ]"
                        title="Calendário"
                    >
                        <CalendarDays class="h-4 w-4" />
                    </button>

                    <button
                        @click="openTodoList"
                        :class="[
                            'rounded-md p-2 transition-colors duration-500',
                            activeTab === 'todolist' ? theme.panelIconBg : 'bg-white',
                            activeTab === 'todolist' ? theme.panelIconText : 'text-gray-400'
                        ]"
                        title="Minha Lista"
                    >
                        <Users class="h-4 w-4" />
                    </button>
                </div>

                <!-- Panel body -->
                <div class="flex flex-1 flex-col gap-6 overflow-y-auto p-5">

                    <!-- Calendar tab -->
                    <div v-if="activeTab === 'calendar'">
                        <MiniCalendar
                            :calendar-date="calendarDate"
                            :month-label="monthLabel"
                            :calendar-weeks="calendarWeeks"
                            :week-day-labels="weekDayLabels"
                            :theme="theme"
                            :hour-string="hourString"
                            :minute-string="minuteString"
                            :weekday-short="weekdayShort"
                            :day-month-label="dayMonthLabel"
                            :period="period"
                            :reminder-dot-dates="reminderDotDates"
                            @select-day="$emit('select-calendar-day', $event)"
                            @select-today="$emit('select-today')"
                            @prev-month="$emit('prev-month')"
                            @next-month="$emit('next-month')"
                        />
                        <ReminderList
                            :holidays="holidays"
                            :reminders="reminders"
                            :selected-date="selectedDate"
                            :users="users"
                            :current-user-id="currentUserId"
                            :loading="remindersLoading"
                            @reminder-created="$emit('reminder-created', $event)"
                            @reminder-deleted="$emit('reminder-deleted', $event)"
                        />
                    </div>

                    <!-- Todo list tab -->
                    <div v-else>
                        <ParticipatingTasksList
                            :tasks="participatingTasks"
                            :loading="loadingParticipating"
                            :users="users"
                            @open-task="(task, evt) => $emit('open-task-detail', task, evt)"
                        />
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>
