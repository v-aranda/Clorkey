<script setup>
defineProps({
    icon: {
        type: [Object, Function],
        required: true,
    },
});
</script>

<template>
    <div class="flex items-center gap-3">
        <component :is="icon" class="h-6 w-6 text-gray-600" />
        <h1 class="text-lg font-semibold text-gray-900">
            <slot />
        </h1>
    </div>
</template>
