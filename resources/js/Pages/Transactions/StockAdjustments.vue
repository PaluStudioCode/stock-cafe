<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({ adjustments: Object, ingredients: Array, canApprove: Boolean, canCancel: Boolean });
const editingAdjustment = ref(null);
const form = useForm({ adjustment_date: new Date().toISOString().slice(0, 16), reason: '', items: [{ ingredient_id: null, counted_stock: 0, notes: '' }] });
const ingredientMap = computed(() => new Map(props.ingredients.map((ingredient) => [ingredient.id, ingredient])));
const systemStock = (item) => Number(ingredientMap.value.get(item.ingredient_id)?.current_stock || 0);
const difference = (item) => Number(item.counted_stock || 0) - systemStock(item);
const addItem = () => form.items.push({ ingredient_id: null, counted_stock: 0, notes: '' });
const removeItem = (index) => {
    if (form.items.length > 1) form.items.splice(index, 1);
};
const resetForm = () => {
    editingAdjustment.value = null;
    form.reset();
    form.adjustment_date = new Date().toISOString().slice(0, 16);
    form.reason = '';
    form.items = [{ ingredient_id: null, counted_stock: 0, notes: '' }];
    form.clearErrors();
};
const editAdjustment = (adjustment) => {
    editingAdjustment.value = adjustment;
    form.adjustment_date = adjustment.adjustment_date?.slice(0, 16);
    form.reason = adjustment.reason;
    form.items = adjustment.items.map((item) => ({ ingredient_id: item.ingredient_id, counted_stock: item.counted_stock, notes: item.notes || '' }));
    form.clearErrors();
};
const submit = () => {
    const options = { onSuccess: resetForm };
    if (editingAdjustment.value) {
        form.put(route('stock-adjustments.update', editingAdjustment.value.id), options);
        return;
    }

    form.post(route('stock-adjustments.store'), options);
};
const approve = (id) => confirm('Setujui adjustment dan ubah stok?') && router.post(route('stock-adjustments.approve', id));
const cancel = (id) => confirm('Batalkan adjustment? Adjustment approved akan membuat movement pembalik.') && router.post(route('stock-adjustments.cancel', id));
const ingredientLabel = (ingredient) => `${ingredient.name} (${ingredient.current_stock} ${ingredient.unit?.symbol || '-'})`;
</script>

<template>
    <AuthenticatedLayout title="Stock Adjustment">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Stock Adjustment</h1>
            <button v-if="editingAdjustment" type="button" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold text-slate-600" @click="resetForm"><X class="h-4 w-4" /> Batal Edit</button>
        </div>

        <form class="mb-6 rounded-lg border border-orange-100 bg-white p-4 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-3 md:grid-cols-2">
                <input v-model="form.adjustment_date" type="datetime-local" class="rounded-md border-slate-300" />
                <input v-model="form.reason" class="rounded-md border-slate-300" placeholder="Alasan wajib" />
            </div>
            <div class="mt-1 grid gap-2 text-xs text-red-600 md:grid-cols-2">
                <div>{{ form.errors.adjustment_date }}</div>
                <div>{{ form.errors.reason }}</div>
            </div>

            <div class="mt-4 space-y-2">
                <div v-for="(item, i) in form.items" :key="i" class="grid gap-2 md:grid-cols-[1.5fr_1fr_1fr_1fr_auto]">
                    <div>
                        <select v-model="item.ingredient_id" class="w-full rounded-md border-slate-300"><option :value="null">Bahan</option><option v-for="ingredient in ingredients" :key="ingredient.id" :value="ingredient.id">{{ ingredientLabel(ingredient) }}</option></select>
                        <div v-if="form.errors[`items.${i}.ingredient_id`]" class="mt-1 text-xs text-red-600">{{ form.errors[`items.${i}.ingredient_id`] }}</div>
                    </div>
                    <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">System {{ systemStock(item).toFixed(3) }}</div>
                    <div>
                        <input v-model="item.counted_stock" type="number" min="0" step="0.001" class="w-full rounded-md border-slate-300" placeholder="Counted" />
                        <div v-if="form.errors[`items.${i}.counted_stock`]" class="mt-1 text-xs text-red-600">{{ form.errors[`items.${i}.counted_stock`] }}</div>
                    </div>
                    <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm" :class="difference(item) < 0 ? 'text-red-600' : difference(item) > 0 ? 'text-emerald-700' : 'text-slate-600'">Diff {{ difference(item).toFixed(3) }}</div>
                    <button type="button" class="rounded-md p-2 text-red-600 hover:bg-red-50 disabled:text-slate-300" :disabled="form.items.length === 1" aria-label="Hapus item" title="Hapus item" @click="removeItem(i)"><Trash2 class="h-4 w-4" /></button>
                    <input v-model="item.notes" class="rounded-md border-slate-300 md:col-span-4" placeholder="Catatan item" />
                </div>
            </div>
            <div v-if="form.errors.items" class="mt-2 text-xs text-red-600">{{ form.errors.items }}</div>
            <div v-if="form.errors.stock" class="mt-2 text-xs text-red-600">{{ form.errors.stock }}</div>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm" @click="addItem"><Plus class="h-4 w-4" /> Item</button>
                <button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">{{ editingAdjustment ? 'Update Draft' : 'Simpan Draft' }}</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-orange-100 bg-white">
            <table class="min-w-full text-sm">
                <thead><tr class="border-b bg-stone-50 text-left"><th class="p-3">Kode</th><th>Status</th><th>Alasan</th><th>Aksi</th></tr></thead>
                <tbody>
                    <tr v-for="adjustment in adjustments.data" :key="adjustment.id" class="border-b">
                        <td class="p-3 font-semibold">{{ adjustment.adjustment_code }}</td>
                        <td>{{ adjustment.status }}</td>
                        <td>{{ adjustment.reason }}</td>
                        <td class="space-x-2">
                            <button v-if="adjustment.status === 'draft'" class="text-orange-700" @click="editAdjustment(adjustment)"><Pencil class="inline h-4 w-4" /> Edit</button>
                            <button v-if="canApprove && adjustment.status === 'draft'" class="text-orange-700" @click="approve(adjustment.id)">Approve</button>
                            <button v-if="canCancel && adjustment.status !== 'cancelled'" class="text-red-600" @click="cancel(adjustment.id)">Cancel</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
