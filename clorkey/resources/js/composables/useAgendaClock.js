import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Sunrise, Sun, Moon } from 'lucide-vue-next';

export function useAgendaClock() {
    const now = ref(new Date());
    let clockInterval = null;

    onMounted(() => {
        clockInterval = setInterval(() => { now.value = new Date(); }, 1000);
    });
    onBeforeUnmount(() => { clearInterval(clockInterval); });

    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    const timeString = computed(() =>
        new Intl.DateTimeFormat('pt-BR', {
            timeZone: userTimezone,
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
        }).format(now.value)
    );

    const hourString = computed(() => timeString.value.substring(0, 2));
    const minuteString = computed(() => timeString.value.substring(3, 5));

    const fullDateString = computed(() =>
        new Intl.DateTimeFormat('pt-BR', {
            timeZone: userTimezone,
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
        }).format(now.value)
    );

    const weekdayShort = computed(() => {
        const s = new Intl.DateTimeFormat('pt-BR', { timeZone: userTimezone, weekday: 'short' })
            .format(now.value)
            .replace(/\.$/, '');
        return s.charAt(0).toUpperCase() + s.slice(1);
    });

    const dayMonthLabel = computed(() => {
        const parts = new Intl.DateTimeFormat('pt-BR', {
            timeZone: userTimezone, day: 'numeric', month: 'short',
        }).formatToParts(now.value);
        const day = parts.find(p => p.type === 'day')?.value ?? '';
        const raw = parts.find(p => p.type === 'month')?.value.replace(/\.$/, '') ?? '';
        const month = raw.charAt(0).toUpperCase() + raw.slice(1);
        return `${day} ${month}`;
    });

    const todayShortLabel = computed(() => {
        const d = now.value;
        const month = new Intl.DateTimeFormat('pt-BR', { month: 'long', timeZone: userTimezone }).format(d);
        return `${d.getDate()} de ${month}, ${d.getFullYear()}`;
    });

    const currentHour = computed(() =>
        parseInt(new Intl.DateTimeFormat('pt-BR', {
            timeZone: userTimezone, hour: 'numeric', hour12: false,
        }).format(now.value))
    );

    const period = computed(() => {
        const h = currentHour.value;
        if (h >= 6 && h < 12) return 'morning';
        if (h >= 12 && h < 18) return 'afternoon';
        return 'night';
    });

    const periodIcon = computed(() => {
        switch (period.value) {
            case 'morning':   return Sunrise;
            case 'afternoon': return Sun;
            default:          return Moon;
        }
    });

    const theme = computed(() => {
        switch (period.value) {
            case 'morning': return {
                pageBg: 'bg-slate-50',
                iconBg: 'bg-sky-500',
                iconText: 'text-white',
                subtitleText: 'text-sky-500',
                hourText: 'text-slate-400',
                slotBorder: 'border-sky-100',
                slotHover: 'hover:bg-sky-50/60',
                plusIcon: 'text-sky-300',
                scrollThumb: 'rgba(56,189,248,0.25)',
                scrollThumbHover: 'rgba(56,189,248,0.45)',
                nowBg: 'bg-sky-500',
                nowShadow: 'shadow-sky-500/40',
                nowLineBg: 'bg-sky-500',
                nowLineShadow: 'shadow-sky-500/20',
                clockGradient: 'from-sky-400 to-sky-600',
                clockMuted: 'text-sky-200',
                todayBg: 'bg-sky-500',
                dayHover: 'hover:bg-sky-50 hover:text-sky-600',
                panelIconBg: 'bg-sky-50',
                panelIconText: 'text-sky-500',
            };
            case 'afternoon': return {
                pageBg: 'bg-amber-50/40',
                iconBg: 'bg-orange-500',
                iconText: 'text-white',
                subtitleText: 'text-orange-500',
                hourText: 'text-orange-300',
                slotBorder: 'border-orange-200/60',
                slotHover: 'hover:bg-orange-50/50',
                plusIcon: 'text-orange-300',
                scrollThumb: 'rgba(251,146,60,0.25)',
                scrollThumbHover: 'rgba(251,146,60,0.45)',
                nowBg: 'bg-orange-500',
                nowShadow: 'shadow-orange-500/40',
                nowLineBg: 'bg-orange-500',
                nowLineShadow: 'shadow-orange-500/20',
                clockGradient: 'from-orange-400 to-amber-600',
                clockMuted: 'text-orange-200',
                todayBg: 'bg-orange-500',
                dayHover: 'hover:bg-orange-50 hover:text-orange-600',
                panelIconBg: 'bg-orange-50',
                panelIconText: 'text-orange-500',
            };
            default: return {
                pageBg: 'bg-indigo-50',
                iconBg: 'bg-indigo-400',
                iconText: 'text-white',
                subtitleText: 'text-indigo-500',
                hourText: 'text-indigo-400',
                slotBorder: 'border-indigo-100',
                slotHover: 'hover:bg-indigo-100/70',
                plusIcon: 'text-indigo-300',
                scrollThumb: 'rgba(129,140,248,0.35)',
                scrollThumbHover: 'rgba(99,102,241,0.55)',
                nowBg: 'bg-indigo-500',
                nowShadow: 'shadow-indigo-500/30',
                nowLineBg: 'bg-indigo-400',
                nowLineShadow: 'shadow-indigo-400/20',
                clockGradient: 'from-indigo-300 to-violet-400',
                clockMuted: 'text-indigo-200',
                todayBg: 'bg-indigo-500',
                dayHover: 'hover:bg-indigo-100 hover:text-indigo-600',
                panelIconBg: 'bg-indigo-100',
                panelIconText: 'text-indigo-600',
            };
        }
    });

    const isNight = computed(() => period.value === 'night');

    const nowLinePercent = computed(() => (now.value.getMinutes() / 60) * 100);

    return {
        now,
        userTimezone,
        timeString,
        hourString,
        minuteString,
        fullDateString,
        weekdayShort,
        dayMonthLabel,
        todayShortLabel,
        currentHour,
        period,
        periodIcon,
        theme,
        isNight,
        nowLinePercent,
    };
}
