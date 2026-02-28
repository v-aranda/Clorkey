// Module-level cache so holidays persist across component lifecycle without re-fetching
const cache = {};
const pending = {};

export function useAgendaHolidays() {
    async function ensureYear(year) {
        if (cache[year] !== undefined) return;
        if (pending[year]) {
            await pending[year];
            return;
        }
        pending[year] = fetch(`https://brasilapi.com.br/api/feriados/v1/${year}`)
            .then(r => r.ok ? r.json() : [])
            .catch(() => [])
            .then(data => {
                cache[year] = Array.isArray(data) ? data : [];
                delete pending[year];
            });
        await pending[year];
    }

    function getHolidaysForDate(dateStr) {
        if (!dateStr) return [];
        const year = dateStr.slice(0, 4);
        return (cache[year] || []).filter(h => h.date === dateStr);
    }

    function getHolidayDatesForYear(year) {
        return new Set((cache[year] || []).map(h => h.date));
    }

    return { ensureYear, getHolidaysForDate, getHolidayDatesForYear };
}
