<script setup>
import { computed, ref, watch } from 'vue';
import { CheckCircle, XCircle } from 'lucide-vue-next';

const props = defineProps({ message: String, type: { type: String, default: 'success' } });
const visible = ref(false);
const Icon = computed(() => props.type === 'error' ? XCircle : CheckCircle);

watch(() => props.message, (message) => {
    visible.value = !!message;
    if (message) {
        window.setTimeout(() => { visible.value = false; }, 3500);
    }
}, { immediate: true });
</script>

<template>
    <Transition enter-active-class="transition duration-150" enter-from-class="translate-y-2 opacity-0" leave-active-class="transition duration-150" leave-to-class="translate-y-2 opacity-0">
        <div v-if="visible && message" class="fixed right-4 top-20 z-50 flex max-w-sm items-start gap-3 rounded-lg border bg-white p-4 text-sm shadow-lg" :class="type === 'error' ? 'border-red-200 text-red-800' : 'border-emerald-200 text-emerald-800'">
            <component :is="Icon" class="h-5 w-5 shrink-0" />
            <div>{{ message }}</div>
        </div>
    </Transition>
</template>
