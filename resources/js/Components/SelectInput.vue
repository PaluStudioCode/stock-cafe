<script setup>
defineProps({
    label: String,
    modelValue: { type: [String, Number, Boolean], default: null },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Pilih' },
    error: String,
});
defineEmits(['update:modelValue']);
</script>

<template>
    <label class="block">
        <span v-if="label" class="text-sm font-semibold text-slate-700">{{ label }}</span>
        <select
            class="mt-1 w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500"
            :value="modelValue"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option value="">{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value ?? option.id ?? option" :value="option.value ?? option.id ?? option">
                {{ option.label ?? option.name ?? option }}
            </option>
        </select>
        <span v-if="error" class="mt-1 block text-xs text-red-600">{{ error }}</span>
    </label>
</template>
