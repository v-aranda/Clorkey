<template>
  <div class="space-y-3">
    <div
      v-for="m in messages"
      :key="m.id"
      class="flex items-end"
      :class="isMine(m) ? 'justify-end' : 'justify-start'"
    >
      <!-- Avatar: other user (left) -->
      <div v-if="!isMine(m)" class="mr-2 shrink-0">
        <UserAvatar :user="m.user || { id: m.user_id, name: m.user_name, avatar_url: m.user_avatar }" size="sm" />
      </div>

      <!-- Bubble -->
      <div :class="['max-w-[80%] flex flex-col', isMine(m) ? 'items-end' : 'items-start']">
        <div :class="[
          'px-3 py-2 rounded-2xl shadow-sm text-sm',
          isMine(m)
            ? 'bg-indigo-600 text-white rounded-br-sm'
            : 'bg-white border border-gray-100 text-gray-800 rounded-bl-sm'
        ]">
          <!-- Sender name (others only) -->
          <div v-if="!isMine(m)" class="text-[11px] font-semibold mb-1" :class="isMine(m) ? 'text-indigo-200' : 'text-indigo-600'">
            {{ m.user_name || 'Usuário' }}
          </div>

          <!-- Text content (HTML from TipTap) -->
          <div v-if="m.content" class="prose prose-sm max-w-none" :class="isMine(m) ? 'prose-invert' : ''" v-html="m.content" />

          <!-- Attachments -->
          <div v-if="m.attachments && m.attachments.length" class="mt-2 flex flex-wrap gap-2">
            <template v-for="(att, idx) in m.attachments" :key="idx">
              <!-- Image -->
              <a v-if="att.type === 'image'" :href="att.url" target="_blank" rel="noopener">
                <img :src="att.url" :alt="att.original_name" class="max-w-[200px] max-h-[160px] rounded-xl object-cover border border-black/10 hover:opacity-90 transition-opacity" />
              </a>
              <!-- Video -->
              <video
                v-else-if="att.type === 'video'"
                :src="att.url"
                controls
                class="max-w-[240px] max-h-[160px] rounded-xl border border-black/10"
              />
              <!-- Document -->
              <a
                v-else
                :href="att.url"
                target="_blank"
                rel="noopener"
                :class="[
                  'flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-medium transition-colors',
                  isMine(m)
                    ? 'border-indigo-400/50 bg-indigo-500/30 text-indigo-100 hover:bg-indigo-500/50'
                    : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100'
                ]"
              >
                <FileText class="h-4 w-4 shrink-0" />
                <span class="max-w-[140px] truncate">{{ att.original_name }}</span>
              </a>
            </template>
          </div>

          <!-- Legacy single image_url support -->
          <div v-if="!m.attachments?.length && m.image_url" class="mt-2">
            <img :src="m.image_url" class="max-w-xs rounded-xl border border-black/10" />
          </div>
        </div>

        <!-- Timestamp -->
        <div class="mt-1 text-[10px] text-gray-400 px-1">{{ formatTimestamp(m.created_at) }}</div>
      </div>

      <!-- Avatar: own (right) -->
      <div v-if="isMine(m)" class="ml-2 shrink-0">
        <UserAvatar :user="m.user || { id: m.user_id, name: m.user_name, avatar_url: m.user_avatar }" size="sm" />
      </div>
    </div>
  </div>
</template>

<script setup>
import UserAvatar from '@/Components/UserAvatar.vue';
import { FileText } from 'lucide-vue-next';

const props = defineProps({
  messages:    { type: Array,  default: () => [] },
  currentUser: { type: Object, default: null },
});

function isMine(m) {
  if (!props.currentUser) return false;
  return Number(m.user_id) === Number(props.currentUser.id);
}

function formatTimestamp(ts) {
  if (!ts) return '';
  try {
    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(ts));
  } catch (e) { return ts; }
}
</script>
