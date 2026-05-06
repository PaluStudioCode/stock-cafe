<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'button' },
    variant: { type: String, default: 'primary' },
    loading: Boolean,
});

const classes = computed(() => ({
    primary: 'bg-orange-600 text-white hover:bg-orange-700 border-orange-600',
    secondary: 'bg-white text-slate-700 hover:bg-stone-50 border-slate-200',
    danger: 'bg-red-600 text-white hover:bg-red-700 border-red-600',
    ghost: 'bg-transparent text-slate-700 hover:bg-orange-50 border-transparent',
}[props.variant] || 'bg-orange-600 text-white hover:bg-orange-700 border-orange-600'));
</script>

<template>
    <button
        :type="type"
        class="inline-flex h-10 items-center justify-center gap-2 rounded-md border px-4 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
        :class="classes"
        :disabled="loading || $attrs.disabled"
    >
        <span v-if="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
        <slot />
    </button>
</template>
