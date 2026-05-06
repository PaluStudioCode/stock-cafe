<script setup>
import { AlertTriangle } from 'lucide-vue-next';
defineProps({ title: { type: String, default: 'Terjadi kesalahan' }, message: { type: String, default: 'Silakan periksa input atau coba lagi.' } });
</script>

<template>
    <div class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
        <AlertTriangle class="h-5 w-5 shrink-0" />
        <div>
            <div class="text-sm font-bold">{{ title }}</div>
            <div class="mt-1 text-sm">{{ message }}</div>
        </div>
    </div>
</template>
