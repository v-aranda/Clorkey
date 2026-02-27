import { ref } from 'vue';

export function useToast() {
    const showToast = ref(false);
    const toastMessage = ref('');
    let toastTimer = null;

    function triggerToast(message) {
        toastMessage.value = message;
        showToast.value = true;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { showToast.value = false; }, 4000);
    }

    return { showToast, toastMessage, triggerToast };
}
