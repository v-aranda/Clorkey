<script setup>
import { computed } from 'vue';
import { Sunrise, Sun, Moon, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    calendarDate: { type: Date, required: true },
    monthLabel: { type: String, required: true },
    calendarWeeks: { type: Array, required: true },
    weekDayLabels: { type: Array, required: true },
    theme: { type: Object, required: true },
    hourString: { type: String, required: true },
    minuteString: { type: String, required: true },
    weekdayShort: { type: String, required: true },
    dayMonthLabel: { type: String, required: true },
    period: { type: String, required: true },
    reminderDotDates: { type: Object, default: () => new Set() }, // Set<YYYY-MM-DD>
});

function formatYMD(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

const emit = defineEmits(['prev-month', 'next-month', 'select-day', 'select-today']);

const periodIconComponent = computed(() => {
    switch (props.period) {
        case 'morning':   return Sunrise;
        case 'afternoon': return Sun;
        default:          return Moon;
    }
});

function isToday(date) {
    const t = new Date();
    return date.getDate() === t.getDate() &&
        date.getMonth() === t.getMonth() &&
        date.getFullYear() === t.getFullYear();
}

function isSelected(date) {
    if (!props.calendarDate) return false;
    const cd = props.calendarDate instanceof Date ? props.calendarDate : new Date(props.calendarDate);
    return date.getDate() === cd.getDate() &&
        date.getMonth() === cd.getMonth() &&
        date.getFullYear() === cd.getFullYear();
}
</script>

<template>
    <div>
        <!-- Clock card -->
        <div
            role="button" tabindex="0"
            @click="$emit('select-today')" @keyup.enter="$emit('select-today')"
            :class="['rounded-2xl bg-gradient-to-br px-5 py-4 text-white shadow-sm transition-all duration-500 cursor-pointer select-none', theme.clockGradient]"
            title="Ir para hoje"
        >
            <div class="flex items-stretch gap-3">
                <!-- Hours & Minutes (left) -->
                <div class="flex flex-col leading-none flex-1">
                    <span class="font-mono text-6xl font-bold tabular-nums tracking-tight">{{ hourString }}</span>
                    <span class="font-mono text-6xl font-bold tabular-nums tracking-tight mt-0.5">{{ minuteString }}</span>
                </div>
                <!-- Date & Period icon (right) -->
                <div class="flex flex-col justify-between items-end shrink-0 py-0.5">
                    <div :class="['text-right leading-snug', theme.clockMuted]">
                        <p class="text-xs font-semibold capitalize">{{ weekdayShort }},</p>
                        <p class="text-sm font-semibold">{{ dayMonthLabel }}</p>
                    </div>
                    <component :is="periodIconComponent" :class="['h-7 w-7', theme.clockMuted]" />
                </div>
            </div>
        </div>

        <!-- Month navigation -->
        <div class="mb-3 mt-4 flex items-center">
            <button
                @click="$emit('prev-month')"
                class="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"
            >
                <ChevronLeft class="h-4 w-4" />
            </button>
            <span class="flex-1 text-center text-sm font-semibold capitalize text-gray-700">{{ monthLabel }}</span>
            <button
                @click="$emit('next-month')"
                class="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"
            >
                <ChevronRight class="h-4 w-4" />
            </button>
        </div>

        <!-- Calendar table -->
        <table class="w-full border-collapse">
            <thead>
                <tr>
                    <th
                        v-for="(label, idx) in weekDayLabels"
                        :key="idx"
                        class="pb-1 text-center text-[10px] font-semibold uppercase tracking-wider text-gray-400"
                    >{{ label.charAt(0) }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(week, wIdx) in calendarWeeks" :key="wIdx">
                    <td
                        v-for="(day, dIdx) in week"
                        :key="dIdx"
                        class="p-0 text-center"
                    >
                        <button
                            :class="[
                                'mx-auto flex h-8 w-8 flex-col items-center justify-center rounded-full text-xs transition-colors duration-500',
                                isSelected(day.date)
                                    ? [theme.todayBg, 'font-bold text-white shadow-sm ring-2 ring-offset-2']
                                    : isToday(day.date)
                                        ? ['ring-2 ring-offset-1 ring-indigo-300 text-indigo-700 bg-white']
                                        : day.currentMonth
                                            ? ['text-gray-700', theme.dayHover]
                                            : 'text-gray-300'
                            ]"
                            @click="$emit('select-day', day.date)"
                        >
                            {{ day.date.getDate() }}
                            <span
                                v-if="reminderDotDates.has(formatYMD(day.date))"
                                :class="[
                                    'block h-1 w-1 rounded-full mt-0.5',
                                    isSelected(day.date) ? 'bg-white/70' : 'bg-amber-400'
                                ]"
                            ></span>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
