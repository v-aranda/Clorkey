<template>
  <div class="space-y-2">
    <div
      v-for="v in items"
      :key="v.id"
      class="rounded-xl border border-amber-200 bg-amber-50/60 px-3 py-2.5"
    >
      <div class="flex items-center justify-between gap-2">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-1.5">
            <ShieldCheck class="h-3.5 w-3.5 shrink-0 text-amber-500" />
            <span class="text-xs font-semibold text-gray-800 truncate">{{ v.requester_name }}</span>
            <span class="text-[10px] text-gray-400">→</span>
            <span class="text-xs text-gray-600 truncate">{{ v.target_name }}</span>
          </div>
          <div v-if="v.note" class="mt-1 text-xs text-gray-600 line-clamp-2">{{ v.note }}</div>
        </div>

        <!-- Solicitante vê apenas o status -->
        <div v-if="isRequester(v)" class="flex items-center gap-1.5 shrink-0">
          <span class="flex items-center gap-1 rounded-lg border border-amber-300 bg-amber-100 px-2.5 py-1 text-[11px] font-medium text-amber-700">
            <Clock class="h-3 w-3" />
            Pendente de aprovação
          </span>
        </div>

        <!-- Destinatário (ou outros) vê botões de ação -->
        <div v-else class="flex items-center gap-1.5 shrink-0">
          <button
            @click="$emit('approve', v)"
            class="px-2.5 py-1 rounded-lg bg-green-600 text-white text-[11px] font-medium hover:bg-green-700 transition-colors"
          >
            Aprovar
          </button>
          <button
            @click="$emit('reject', v)"
            class="px-2.5 py-1 rounded-lg bg-red-50 border border-red-200 text-red-600 text-[11px] font-medium hover:bg-red-100 transition-colors"
          >
            Rejeitar
          </button>
        </div>
      </div>
      <div class="mt-1 text-[10px] text-gray-400">{{ formatTimestamp(v.created_at) }}</div>
    </div>
  </div>
</template>

<script setup>
import { ShieldCheck, Clock } from 'lucide-vue-next';

const props = defineProps({
  items:       { type: Array,  default: () => [] },
  currentUser: { type: Object, default: null },
});
defineEmits(['approve', 'reject']);

function isRequester(v) {
  if (!props.currentUser) return false;
  return Number(v.requester_id) === Number(props.currentUser.id);
}

function formatTimestamp(ts) {
  if (!ts) return '';
  try {
    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(ts));
  } catch (e) { return ts; }
}
</script>
