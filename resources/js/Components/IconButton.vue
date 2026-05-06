<script setup>
defineProps({
    label: { type: String, required: true },
    type: { type: String, default: 'button' },
});
</script>

<template>
    <button
        :type="type"
        class="inline-grid h-10 w-10 place-items-center rounded-md border border-orange-200 bg-white text-slate-700 transition hover:bg-orange-50 disabled:cursor-not-allowed disabled:opacity-60"
        :aria-label="label"
        :title="label"
    >
        <slot />
    </button>
</template>
