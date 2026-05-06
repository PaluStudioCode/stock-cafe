<script setup>
import { computed } from 'vue';

const props = defineProps({ value: String, tone: String });
const tone = computed(() => props.tone || ({
    draft: 'slate', received: 'emerald', completed: 'emerald', approved: 'emerald',
    cancelled: 'red', active: 'emerald', inactive: 'slate', menipis: 'orange', habis: 'red',
    draf: 'slate', diterima: 'emerald', selesai: 'emerald', disetujui: 'emerald',
    dibatalkan: 'red', aktif: 'emerald', nonaktif: 'slate',
}[String(props.value || '').toLowerCase()] || 'slate'));
const classes = computed(() => ({
    slate: 'bg-slate-100 text-slate-700',
    emerald: 'bg-emerald-100 text-emerald-800',
    orange: 'bg-orange-100 text-orange-800',
    red: 'bg-red-100 text-red-800',
    sky: 'bg-sky-100 text-sky-800',
}[tone.value]));
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold" :class="classes">
        <slot>{{ value }}</slot>
    </span>
</template>
