<script setup>
defineProps({
    id: String,
    label: String,
    modelValue: [String, Number],
    error: String,
    type: { type: String, default: 'text' },
});
defineEmits(['update:modelValue']);
</script>

<template>
    <label class="block">
        <span v-if="label" class="text-sm font-semibold text-slate-700">{{ label }}</span>
        <input
            :id="id"
            :type="type"
            class="mt-1 w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <span v-if="error" class="mt-1 block text-xs text-red-600">{{ error }}</span>
    </label>
</template>
