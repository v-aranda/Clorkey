<script setup>
import { ref, onMounted, nextTick } from 'vue';
import { Plus } from 'lucide-vue-next';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    theme: { type: Object, required: true },
    currentHour: { type: Number, required: true },
    nowLinePercent: { type: Number, required: true },
    isNight: { type: Boolean, default: false },
    reminders: { type: Array, default: () => [] },
    holidays: { type: Array, default: () => [] },
    selectedDate: { type: String, default: '' },
});

const emit = defineEmits(['open-task-panel', 'open-task-detail', 'schedule-assigned-task']);

const allSlots = Array.from({ length: 18 }, (_, i) => i + 6); // 6..23

const SLOT_BASE_HEIGHT = 80;
const SLOT_EXTRA_PER_TASK = 56;

const scheduleRef = ref(null);
const activeDropHour = ref(null);

onMounted(() => {
    nextTick(() => {
        const el = scheduleRef.value?.querySelector('[data-now]');
        if (el) el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    });
});

function formatHour(h) {
    return h === 24 ? '00:00' : `${String(h).padStart(2, '0')}:00`;
}

function isCurrentHour(h) {
    return props.currentHour === h;
}

function tasksForHour(hour) {
    const hStr = String(hour).padStart(2, '0');
    return props.tasks.filter(t => t.start_time && t.start_time.startsWith(hStr + ':'));
}

function slotStyle(hour) {
    const count = tasksForHour(hour).length;
    const extra = Math.max(0, count - 1) * SLOT_EXTRA_PER_TASK;
    return { minHeight: `${SLOT_BASE_HEIGHT + extra}px` };
}

function stripHtml(html) {
    if (!html) return '';
    return html.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
}

function getUser(id) {
    const nid = Number(id);
    return props.users.find(u => Number(u.id) === nid) ?? { id: nid, name: 'Usuário', avatar_url: null };
}

function getDraggedTaskId(dataTransfer) {
    if (!dataTransfer) return null;

    const explicitId = dataTransfer.getData('application/x-agenda-task');
    const fallbackId = dataTransfer.getData('text/plain');
    const parsed = Number(explicitId || fallbackId);

    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function onSlotDragOver(event, hour) {
    const taskId = getDraggedTaskId(event?.dataTransfer);
    if (!taskId) return;

    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    activeDropHour.value = hour;
}

function onSlotDragLeave(hour) {
    if (activeDropHour.value === hour) {
        activeDropHour.value = null;
    }
}

function onSlotDrop(event, hour) {
    const taskId = getDraggedTaskId(event?.dataTransfer);
    if (!taskId || !props.selectedDate) return;

    event.preventDefault();
    activeDropHour.value = null;

    emit('schedule-assigned-task', {
        taskId,
        date: props.selectedDate,
        start_time: `${String(hour).padStart(2, '0')}:00`,
    });
}
</script>

<template>
    <div
        ref="scheduleRef"
        class="flex-1 overflow-y-auto px-6 py-4 agenda-scroll"
        :style="{
            '--scroll-thumb': theme.scrollThumb,
            '--scroll-thumb-hover': theme.scrollThumbHover,
        }"
    >
        <div class="relative">
            <!-- Reminders / Holidays sticky strip -->
            <div
                v-if="holidays.length || reminders.length"
                class="sticky top-0 z-20 mb-2 flex flex-wrap gap-1.5 rounded-lg border border-amber-200 bg-amber-50/95 px-3 py-2 backdrop-blur-sm shadow-sm"
            >
                <div
                    v-for="h in holidays"
                    :key="'holiday-' + h.name"
                    class="inline-flex items-center gap-1.5 rounded-full bg-amber-400 px-2.5 py-0.5 text-xs font-semibold text-white shadow-sm"
                >
                    🎉 {{ h.name }}
                </div>
                <div
                    v-for="r in reminders"
                    :key="'reminder-' + r.id"
                    class="inline-flex items-center gap-1.5 rounded-full bg-yellow-100 border border-yellow-300 px-2.5 py-0.5 text-xs font-medium text-yellow-800"
                >
                    🔔 {{ r.title }}
                </div>
            </div>

            <!-- Hour rows -->
            <div
                v-for="hour in allSlots"
                :key="hour"
                class="relative transition-all duration-200"
                :style="slotStyle(hour)"
                :data-now="isCurrentHour(hour) ? '' : undefined"
            >
                <div class="flex items-start">
                    <!-- Hour label -->
                    <span :class="[
                        'w-14 shrink-0 -mt-2.5 pr-4 text-right font-mono text-xs transition-colors duration-500',
                        theme.hourText
                    ]">
                        {{ formatHour(hour) }}
                    </span>

                    <!-- Slot area -->
                    <div
                        :class="[
                            'flex-1 cursor-pointer border-t px-3 py-2 transition-colors duration-300',
                            theme.slotBorder,
                            theme.slotHover,
                            activeDropHour === hour ? 'bg-primary/10 ring-2 ring-primary/60 ring-inset' : '',
                            'group'
                        ]"
                        @click="$emit('open-task-panel', hour)"
                        @dragover="onSlotDragOver($event, hour)"
                        @dragleave="onSlotDragLeave(hour)"
                        @drop="onSlotDrop($event, hour)"
                    >
                        <!-- Tasks as post-its -->
                        <div v-if="tasksForHour(hour).length" class="flex flex-col gap-2 pt-1">
                            <div
                                v-for="task in tasksForHour(hour)"
                                :key="task.id"
                                :class="[
                                    'rounded-lg px-3 py-2.5 cursor-pointer transition-all hover:shadow-md',
                                    isNight
                                        ? 'bg-indigo-50 border border-indigo-200/70 shadow-sm'
                                        : 'bg-amber-50/80 border border-amber-200/60 shadow-sm'
                                ]"
                                @click.stop="$emit('open-task-detail', task, $event)"
                            >
                                <!-- Row 1: time + title -->
                                <div class="flex items-center gap-2">
                                    <span :class="[
                                        'text-[10px] font-bold px-1.5 py-0.5 rounded',
                                        isNight ? 'bg-indigo-200/60 text-indigo-700' : 'bg-amber-200/60 text-amber-700'
                                    ]">{{ task.start_time?.slice(0, 5) }}</span>
                                    <span :class="[
                                        'text-sm font-semibold truncate',
                                        isNight ? 'text-indigo-900' : 'text-gray-800'
                                    ]">{{ task.name }}</span>
                                </div>

                                <!-- Row 2: description snippet -->
                                <p
                                    v-if="stripHtml(task.description)"
                                    :class="[
                                        'mt-1 text-xs leading-snug line-clamp-1',
                                        isNight ? 'text-indigo-600/70' : 'text-gray-500'
                                    ]"
                                >{{ stripHtml(task.description) }}</p>

                                <!-- Row 3: participants -->
                                <div v-if="task.participants && task.participants.length" class="mt-1.5 flex items-center justify-end">
                                    <div class="flex -space-x-1.5">
                                        <div
                                            v-for="(pid, idx) in task.participants.slice(0, 3)"
                                            :key="`tl-p-${task.id}-${pid}-${idx}`"
                                            class="rounded-full ring-1 ring-white"
                                        >
                                            <UserAvatar :user="getUser(pid)" size="xs" />
                                        </div>
                                        <div
                                            v-if="task.participants.length > 3"
                                            :class="[
                                                'flex items-center justify-center h-6 w-6 rounded-full ring-1 ring-white text-[9px] font-bold',
                                                isNight ? 'bg-indigo-200 text-indigo-700' : 'bg-amber-200 text-amber-700'
                                            ]"
                                        >+{{ task.participants.length - 3 }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <Plus v-else :class="[
                            'mt-1.5 h-3.5 w-3.5 opacity-0 transition-opacity group-hover:opacity-100',
                            theme.plusIcon
                        ]" />
                    </div>
                </div>

                <!-- "Now" line -->
                <div
                    v-if="isCurrentHour(hour)"
                    class="pointer-events-none absolute z-10 flex items-center"
                    :style="{ top: nowLinePercent + '%', left: '-1.5rem', right: '-1.5rem' }"
                >
                    <span :class="[
                        'rounded-r-full px-2.5 py-[2px] text-[10px] font-semibold text-white shadow-sm transition-colors duration-500',
                        theme.nowBg, theme.nowShadow
                    ]">
                        Agora
                    </span>
                    <div :class="[
                        'h-[2px] flex-1 shadow-sm transition-colors duration-500',
                        theme.nowLineBg, theme.nowLineShadow
                    ]"></div>
                </div>
            </div>

            <!-- Closing 00:00 marker -->
            <div class="flex items-start">
                <span :class="[
                    'w-14 shrink-0 -mt-2.5 pr-4 text-right font-mono text-xs transition-colors duration-500',
                    theme.hourText
                ]">00:00</span>
                <div :class="['flex-1 border-t transition-colors duration-500', theme.slotBorder]"></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.agenda-scroll {
    scrollbar-width: thin;
    scrollbar-color: var(--scroll-thumb) transparent;
}
.agenda-scroll::-webkit-scrollbar { width: 6px; }
.agenda-scroll::-webkit-scrollbar-track { background: transparent; }
.agenda-scroll::-webkit-scrollbar-thumb {
    background: var(--scroll-thumb);
    border-radius: 9999px;
}
.agenda-scroll::-webkit-scrollbar-thumb:hover { background: var(--scroll-thumb-hover); }

.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
