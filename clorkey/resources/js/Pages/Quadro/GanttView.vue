<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import { useQuadroStore } from '@/stores/quadroStore';

const props = defineProps({
    users: { type: Array, default: () => [] },
});

const emit = defineEmits(['task-click']);

const store = useQuadroStore();

// ─── Constants ────────────────────────────────────────────────────────────────
const COL_WIDTH = 40;        // px per day
const ROW_HEIGHT = 40;       // px per task row
const ROW_LABEL_WIDTH = 200; // px for task name column on the left
const BATCH_SIZE = 30;

// ─── Date helpers ─────────────────────────────────────────────────────────────
const now = new Date();
const year = now.getFullYear();
const month = now.getMonth(); // 0-based

const daysInMonth = new Date(year, month + 1, 0).getDate();
const monthDays = Array.from({ length: daysInMonth }, (_, i) => i + 1);

const monthLabel = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' })
    .format(new Date(year, month, 1));

const todayStr = now.toISOString().slice(0, 10);
const todayDay = now.getFullYear() === year && now.getMonth() === month ? now.getDate() : null;
const todayLeft = todayDay !== null ? ROW_LABEL_WIDTH + (todayDay - 1) * COL_WIDTH : null;

const DAY_LABELS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
function dayLabel(day) {
    return DAY_LABELS[new Date(year, month, day).getDay()];
}

// ─── Tasks ────────────────────────────────────────────────────────────────────
const ganttTasks = computed(() =>
    store.filteredTasks
        .filter(t => t.date)
        .slice()
        .sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0)),
);

// ─── Lazy load ────────────────────────────────────────────────────────────────
const visibleCount = ref(BATCH_SIZE);
const visibleTasks = computed(() => ganttTasks.value.slice(0, visibleCount.value));
const sentinelRef = ref(null);
let observer = null;

onMounted(() => {
    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && visibleCount.value < ganttTasks.value.length) {
            visibleCount.value += BATCH_SIZE;
        }
    }, { threshold: 0.1 });
    if (sentinelRef.value) observer.observe(sentinelRef.value);
});

onUnmounted(() => {
    observer?.disconnect();
});

// ─── Bar helpers ──────────────────────────────────────────────────────────────
function clampDay(d) {
    return Math.max(1, Math.min(daysInMonth, d));
}

function taskStartDay(task) {
    if (!task.date) return 1;
    const d = new Date(task.date + 'T00:00:00');
    if (d.getFullYear() !== year || d.getMonth() !== month) {
        return d < new Date(year, month, 1) ? 1 : daysInMonth + 1;
    }
    return d.getDate();
}

function taskEndDay(task) {
    if (!task.deadline) return taskStartDay(task);
    const d = new Date(task.deadline + 'T00:00:00');
    if (d.getFullYear() !== year || d.getMonth() !== month) {
        return d < new Date(year, month, 1) ? 0 : daysInMonth;
    }
    return d.getDate();
}

function barStyle(task) {
    const start = clampDay(taskStartDay(task));
    const end = clampDay(taskEndDay(task));
    const duration = Math.max(1, end - start + 1);
    return {
        left: ROW_LABEL_WIDTH + (start - 1) * COL_WIDTH + 'px',
        width: duration * COL_WIDTH - 4 + 'px',
    };
}

const STATUS_COLORS = {
    done: 'bg-emerald-500',
    doing: 'bg-amber-400',
    stopped: 'bg-red-400',
    todo: 'bg-blue-400',
};

function barColor(task) {
    if (task.status === 'done') return STATUS_COLORS.done;
    if (task.deadline && task.deadline < todayStr && task.status !== 'done') return 'bg-red-500';
    return STATUS_COLORS[task.status] ?? STATUS_COLORS.todo;
}

function barWidth(task) {
    const start = clampDay(taskStartDay(task));
    const end = clampDay(taskEndDay(task));
    return Math.max(1, end - start + 1) * COL_WIDTH - 4;
}

function tooltipText(task) {
    const parts = [task.name];
    if (task.date) parts.push('Início: ' + task.date);
    if (task.deadline) parts.push('Prazo: ' + task.deadline);
    return parts.join('\n');
}

// ─── Drag-resize (Pointer Events) ─────────────────────────────────────────────
const gridBodyRef = ref(null);
const dragging = ref(null); // { task, originalDeadline, startX }

function onResizePointerDown(e, task) {
    e.preventDefault();
    e.stopPropagation();
    e.target.setPointerCapture(e.pointerId);
    dragging.value = {
        task,
        originalDeadline: task.deadline,
        startX: e.clientX,
    };
}

function onResizePointerMove(e) {
    if (!dragging.value) return;
    const { task } = dragging.value;

    const gridRect = gridBodyRef.value?.getBoundingClientRect();
    if (!gridRect) return;

    const relX = e.clientX - gridRect.left - ROW_LABEL_WIDTH;
    const newEndDay = clampDay(Math.round(relX / COL_WIDTH));
    const startDay = clampDay(taskStartDay(task));
    const endDay = Math.max(startDay, newEndDay);

    const d = new Date(year, month, endDay);
    task.deadline = d.toISOString().slice(0, 10);
}

async function onResizePointerUp(e) {
    if (!dragging.value) return;
    const { task, originalDeadline } = dragging.value;
    dragging.value = null;

    try {
        await axios.patch(route('agenda.tasks.update', task.id), {
            deadline: task.deadline,
            context: 'gantt_resize',
        }, { headers: { Accept: 'application/json' } });
    } catch (err) {
        task.deadline = originalDeadline;
        console.error('Gantt resize PATCH failed', err);
    }
}

function onResizePointerCancel(e) {
    if (!dragging.value) return;
    dragging.value.task.deadline = dragging.value.originalDeadline;
    dragging.value = null;
}

// ─── Scroll ───────────────────────────────────────────────────────────────────
const scrollRef = ref(null);
function scrollLeft() { scrollRef.value?.scrollBy({ left: -COL_WIDTH * 7, behavior: 'smooth' }); }
function scrollRight() { scrollRef.value?.scrollBy({ left: COL_WIDTH * 7, behavior: 'smooth' }); }
</script>

<template>
    <div class="flex flex-col h-full min-h-0 rounded-lg border bg-white shadow-sm overflow-hidden">

        <!-- Top bar: month label + scroll arrows -->
        <div class="flex items-center justify-between border-b px-4 py-2 shrink-0 bg-gray-50">
            <span class="text-sm font-semibold text-gray-700 capitalize">{{ monthLabel }}</span>
            <div class="flex items-center gap-1">
                <button @click="scrollLeft"
                    class="rounded p-1 text-gray-500 hover:bg-gray-200 transition-colors"
                    title="Rolar 7 dias para a esquerda">
                    ←
                </button>
                <button @click="scrollRight"
                    class="rounded p-1 text-gray-500 hover:bg-gray-200 transition-colors"
                    title="Rolar 7 dias para a direita">
                    →
                </button>
            </div>
        </div>

        <!-- Scrollable grid -->
        <div ref="scrollRef" class="flex-1 overflow-auto min-h-0">
            <div
                :style="{ minWidth: ROW_LABEL_WIDTH + daysInMonth * COL_WIDTH + 'px' }"
                class="relative"
            >
                <!-- Day header -->
                <div class="flex sticky top-0 z-20 bg-white border-b select-none">
                    <!-- Label column header -->
                    <div
                        :style="{ width: ROW_LABEL_WIDTH + 'px', minWidth: ROW_LABEL_WIDTH + 'px' }"
                        class="shrink-0 px-3 py-2 text-xs font-semibold text-gray-500 border-r bg-gray-50"
                    >
                        Tarefa
                    </div>
                    <!-- Day cells -->
                    <div
                        v-for="day in monthDays"
                        :key="day"
                        :style="{ width: COL_WIDTH + 'px', minWidth: COL_WIDTH + 'px' }"
                        :class="[
                            'shrink-0 flex flex-col items-center justify-center py-1 border-r text-center',
                            day === todayDay ? 'bg-indigo-50' : '',
                        ]"
                    >
                        <span class="text-[10px] text-gray-400">{{ dayLabel(day) }}</span>
                        <span :class="['text-xs font-medium', day === todayDay ? 'text-indigo-600' : 'text-gray-600']">
                            {{ day }}
                        </span>
                    </div>
                </div>

                <!-- Grid body -->
                <div ref="gridBodyRef" class="relative">

                    <!-- Today line -->
                    <div
                        v-if="todayLeft !== null"
                        :style="{ left: todayLeft + 'px' }"
                        class="absolute top-0 bottom-0 z-10 pointer-events-none"
                        style="border-left: 2px dashed #6366f1;"
                    >
                        <span class="absolute -top-0 left-1 text-[10px] font-semibold text-indigo-500 bg-white px-0.5">
                            Hoje
                        </span>
                    </div>

                    <!-- Task rows -->
                    <template v-if="visibleTasks.length">
                        <div
                            v-for="(task, idx) in visibleTasks"
                            :key="task.id"
                            :style="{ height: ROW_HEIGHT + 'px' }"
                            :class="['flex items-center relative border-b', idx % 2 === 0 ? 'bg-white' : 'bg-gray-50']"
                        >
                            <!-- Task label (fixed left column) -->
                            <div
                                :style="{ width: ROW_LABEL_WIDTH + 'px', minWidth: ROW_LABEL_WIDTH + 'px' }"
                                class="shrink-0 px-3 text-xs text-gray-700 truncate border-r h-full flex items-center"
                                :title="task.name"
                            >
                                {{ task.name }}
                            </div>

                            <!-- Bar -->
                            <div
                                :style="barStyle(task)"
                                :class="['absolute h-6 rounded flex items-center cursor-pointer select-none', barColor(task)]"
                                :title="tooltipText(task)"
                                @click="emit('task-click', task)"
                            >
                                <!-- Name inside bar (only if wide enough) -->
                                <span
                                    v-if="barWidth(task) >= COL_WIDTH * 3"
                                    class="px-2 text-[11px] text-white font-medium truncate pointer-events-none"
                                    :style="{ maxWidth: barWidth(task) - 16 + 'px' }"
                                >
                                    {{ task.name }}
                                </span>

                                <!-- Resize handle (right edge) -->
                                <div
                                    class="absolute right-0 top-0 bottom-0 w-2 cursor-ew-resize rounded-r"
                                    style="background: rgba(0,0,0,0.15);"
                                    @pointerdown.stop="onResizePointerDown($event, task)"
                                    @pointermove="onResizePointerMove"
                                    @pointerup="onResizePointerUp"
                                    @pointercancel="onResizePointerCancel"
                                />
                            </div>
                        </div>
                    </template>

                    <!-- Empty state -->
                    <div v-else class="py-16 text-center text-sm text-gray-400">
                        Nenhuma tarefa com data agendada.
                    </div>

                    <!-- Lazy load sentinel -->
                    <div ref="sentinelRef" class="h-px" />
                </div>
            </div>
        </div>
    </div>
</template>
