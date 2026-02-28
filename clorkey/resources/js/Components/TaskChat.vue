<template>
  <div class="flex flex-col h-full min-h-0">
    <div ref="scrollContainer" class="flex-1 overflow-y-auto p-4 space-y-3">
      <MessageList :messages="messages" :current-user="currentUser" />

      <!-- Validation requests inline -->
      <template v-if="validationRequests.length">
        <div class="flex items-center gap-2 pt-2">
          <div class="flex-1 border-t border-amber-200"></div>
          <span class="text-[10px] font-semibold uppercase tracking-wider text-amber-500">Validações pendentes</span>
          <div class="flex-1 border-t border-amber-200"></div>
        </div>
        <ValidationList :items="validationRequests" :current-user="currentUser" @approve="handleApprove" @reject="handleReject" />
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import MessageList from '@/Components/MessageList.vue';
import ValidationList from '@/Components/ValidationList.vue';

const props = defineProps({ taskId: { type: [String, Number], required: true }, users: { type: Array, default: () => [] }, currentUser: { type: Object, default: null } });

const scrollContainer = ref(null);
const messages = ref([]);
const validationRequests = ref([]);
let poll = null;

function scrollToBottom() {
  nextTick(() => {
    if (scrollContainer.value) {
      scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
    }
  });
}

watch(() => messages.value.length, scrollToBottom);

async function fetchAll() {
  try {
    const [m, v] = await Promise.all([
      axios.get(`/agenda/tasks/${props.taskId}/messages`),
      axios.get(`/agenda/tasks/${props.taskId}/validations`),
    ]);
    messages.value = m.data.messages || [];
    validationRequests.value = v.data.validations || [];
  } catch (e) { /* ignore */ }
}

onMounted(async () => {
  await fetchAll();
  scrollToBottom();
  poll = setInterval(fetchAll, 2500);
});
onBeforeUnmount(() => { if (poll) clearInterval(poll); });

function onSent(newItem) {
  // optimistic: push message/validation to local lists
  if (!newItem) { fetchAll(); return; }
  if (newItem.type === 'validation') {
    validationRequests.value.unshift(newItem);
  } else {
    messages.value.push(newItem);
  }
}

async function handleApprove(v) {
  try {
    await axios.post(`/agenda/tasks/${props.taskId}/validations/${v.id}/approve`);
    fetchAll();
  } catch (e) { console.error(e); }
}
async function handleReject(v) {
  try {
    await axios.post(`/agenda/tasks/${props.taskId}/validations/${v.id}/reject`);
    fetchAll();
  } catch (e) { console.error(e); }
}
</script>
