<script setup>
import FormInput from '@/Components/FormInput.vue';
defineProps({ label: String, modelValue: String, error: String });
defineEmits(['update:modelValue']);
</script>

<template>
    <FormInput type="date" :label="label" :model-value="modelValue" :error="error" @update:model-value="$emit('update:modelValue', $event)" />
</template>
