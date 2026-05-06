<script setup>
import Modal from '@/Components/Modal.vue';
import Badge from '@/Components/Badge.vue';

defineProps({ show: Boolean, movement: Object });
defineEmits(['close']);
</script>

<template>
    <Modal :show="show" max-width="lg" @close="$emit('close')">
        <div class="p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Detail Stock Movement</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ movement?.ingredient?.name || movement?.ingredient_id }}</p>
                </div>
                <Badge v-if="movement" :value="movement.type" />
            </div>

            <div v-if="movement" class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Stock Before</div><div class="font-semibold">{{ movement.stock_before }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Stock After</div><div class="font-semibold">{{ movement.stock_after }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Quantity In</div><div class="font-semibold">{{ movement.quantity_in }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Quantity Out</div><div class="font-semibold">{{ movement.quantity_out }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Unit Cost Snapshot</div><div class="font-semibold">{{ movement.unit_cost_snapshot }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">User</div><div class="font-semibold">{{ movement.user?.name || '-' }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Reference</div><div class="font-semibold">{{ movement.reference_type }} #{{ movement.reference_id || '-' }}</div></div>
                <div class="rounded-md bg-stone-50 p-3"><div class="text-xs text-slate-500">Notes</div><div class="font-semibold">{{ movement.notes || '-' }}</div></div>
            </div>
        </div>
    </Modal>
</template>
