<script setup>
import { Inbox } from 'lucide-vue-next';
defineProps({ title: { type: String, default: 'Belum ada data' }, message: { type: String, default: 'Data akan tampil di sini setelah tersedia.' } });
</script>

<template>
    <div class="grid place-items-center rounded-lg border border-dashed border-slate-200 bg-white p-8 text-center">
        <Inbox class="h-10 w-10 text-orange-500" />
        <h3 class="mt-3 text-sm font-bold text-slate-900">{{ title }}</h3>
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ message }}</p>
    </div>
</template>
