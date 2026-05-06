<script setup>
defineProps({ label: String, modelValue: [String, Number], error: String, step: { type: String, default: '0.001' }, min: { type: String, default: '0' } });
defineEmits(['update:modelValue']);
</script>

<template>
    <label class="block">
        <span v-if="label" class="text-sm font-semibold text-slate-700">{{ label }}</span>
        <input
            type="number"
            :min="min"
            :step="step"
            class="mt-1 w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <span v-if="error" class="mt-1 block text-xs text-red-600">{{ error }}</span>
    </label>
</template>
