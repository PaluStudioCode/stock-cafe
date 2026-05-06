<script setup>
import NumberInput from '@/Components/NumberInput.vue';
defineProps({ label: String, modelValue: [String, Number], error: String });
defineEmits(['update:modelValue']);
</script>

<template>
    <NumberInput step="1" min="0" :label="label" :model-value="modelValue" :error="error" @update:model-value="$emit('update:modelValue', $event)" />
</template>
