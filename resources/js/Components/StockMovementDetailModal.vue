<script setup>
import Modal from '@/Components/Modal.vue';
import Badge from '@/Components/Badge.vue';
import { labelForValue } from '@/utilities/formatters';

defineProps({ show: Boolean, movement: Object });
defineEmits(['close']);
</script>

<template>
    <Modal :show="show" max-width="lg" @close="$emit('close')">
        <div class="p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Detail Pergerakan Stok</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ movement?.ingredient?.name || '-' }}</p>
                </div>
                <Badge v-if="movement" :value="labelForValue(movement.type)" />
            </div>

            <div v-if="movement" class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Stok sebelum</div><div class="font-semibold">{{ movement.stock_before }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Stok sesudah</div><div class="font-semibold">{{ movement.stock_after }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Masuk</div><div class="font-semibold">{{ movement.quantity_in }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Keluar</div><div class="font-semibold">{{ movement.quantity_out }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Harga snapshot</div><div class="font-semibold">{{ movement.unit_cost_snapshot }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Pengguna</div><div class="font-semibold">{{ movement.user?.name || '-' }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Referensi</div><div class="font-semibold">{{ labelForValue(movement.reference_type) }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Catatan</div><div class="font-semibold">{{ movement.notes || '-' }}</div></div>
            </div>
        </div>
    </Modal>
</template>
