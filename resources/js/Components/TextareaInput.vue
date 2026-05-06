<script setup>
defineProps({ label: String, modelValue: String, error: String, rows: { type: Number, default: 3 } });
defineEmits(['update:modelValue']);
</script>

<template>
    <label class="block">
        <span v-if="label" class="text-sm font-semibold text-slate-700">{{ label }}</span>
        <textarea
            class="mt-1 w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500"
            :rows="rows"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <span v-if="error" class="mt-1 block text-xs text-red-600">{{ error }}</span>
    </label>
</template>
