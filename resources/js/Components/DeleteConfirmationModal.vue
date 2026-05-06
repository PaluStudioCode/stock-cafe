<script setup>
import Modal from '@/Components/Modal.vue';
import Button from '@/Components/Button.vue';

defineProps({ show: Boolean, name: { type: String, default: 'data ini' }, loading: Boolean });
defineEmits(['close', 'confirm']);
</script>

<template>
    <Modal :show="show" max-width="md" @close="$emit('close')">
        <div class="p-5">
            <h2 class="text-lg font-bold text-slate-900">Hapus Data</h2>
            <p class="mt-2 text-sm text-slate-600">Data <span class="font-semibold">{{ name }}</span> akan dihapus. Aksi ini tidak dapat dibatalkan.</p>
            <div class="mt-5 flex justify-end gap-2">
                <Button variant="secondary" @click="$emit('close')">Batal</Button>
                <Button variant="danger" :loading="loading" @click="$emit('confirm')">Hapus</Button>
            </div>
        </div>
    </Modal>
</template>
