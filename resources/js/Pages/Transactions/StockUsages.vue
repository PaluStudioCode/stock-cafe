<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({ usages: Object, ingredients: Array, usageTypes: Array, canCancel: Boolean });
const editingUsage = ref(null);
const form = useForm({ usage_date: new Date().toISOString().slice(0, 16), usage_type: 'waste', status: 'draft', notes: '', items: [{ ingredient_id: null, quantity: 1, notes: '' }] });
const ingredientMap = computed(() => new Map(props.ingredients.map((ingredient) => [ingredient.id, ingredient])));
const itemCost = (item) => Number(item.quantity || 0) * Number(ingredientMap.value.get(item.ingredient_id)?.last_unit_cost || 0);
const formTotal = computed(() => form.items.reduce((sum, item) => sum + itemCost(item), 0));
const addItem = () => form.items.push({ ingredient_id: null, quantity: 1, notes: '' });
const removeItem = (index) => {
    if (form.items.length > 1) form.items.splice(index, 1);
};
const resetForm = () => {
    editingUsage.value = null;
    form.reset();
    form.usage_date = new Date().toISOString().slice(0, 16);
    form.usage_type = 'waste';
    form.status = 'draft';
    form.notes = '';
    form.items = [{ ingredient_id: null, quantity: 1, notes: '' }];
    form.clearErrors();
};
const editUsage = (usage) => {
    editingUsage.value = usage;
    form.usage_date = usage.usage_date?.slice(0, 16);
    form.usage_type = usage.usage_type;
    form.status = 'draft';
    form.notes = usage.notes || '';
    form.items = usage.items.map((item) => ({ ingredient_id: item.ingredient_id, quantity: item.quantity, notes: item.notes || '' }));
    form.clearErrors();
};
const submit = () => {
    const options = { onSuccess: resetForm };
    if (editingUsage.value) {
        form.put(route('stock-usages.update', editingUsage.value.id), options);
        return;
    }

    form.post(route('stock-usages.store'), options);
};
const destroyUsage = (id) => confirm('Hapus draft stock usage?') && router.delete(route('stock-usages.destroy', id));
const cancelUsage = (id) => confirm('Batalkan stock usage dan kembalikan stok?') && router.post(route('stock-usages.cancel', id));
const ingredientLabel = (ingredient) => `${ingredient.name} (${ingredient.current_stock} ${ingredient.unit?.symbol || '-'})`;
const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);
</script>

<template>
    <AuthenticatedLayout title="Stock Usage">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Stock Usage</h1>
            <button v-if="editingUsage" type="button" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold text-slate-600" @click="resetForm"><X class="h-4 w-4" /> Batal Edit</button>
        </div>

        <form class="mb-6 rounded-lg border border-orange-100 bg-white p-4 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-3 md:grid-cols-4">
                <input v-model="form.usage_date" type="datetime-local" class="rounded-md border-slate-300" />
                <select v-model="form.usage_type" class="rounded-md border-slate-300"><option v-for="type in usageTypes" :key="type" :value="type">{{ type }}</option></select>
                <select v-model="form.status" class="rounded-md border-slate-300"><option value="draft">draft</option><option value="completed">completed</option></select>
                <input v-model="form.notes" class="rounded-md border-slate-300" placeholder="Notes" />
            </div>
            <div class="mt-1 grid gap-2 text-xs text-red-600 md:grid-cols-4">
                <div>{{ form.errors.usage_date }}</div>
                <div>{{ form.errors.usage_type }}</div>
                <div>{{ form.errors.status }}</div>
                <div>{{ form.errors.notes }}</div>
            </div>

            <div class="mt-4 space-y-2">
                <div v-for="(item, i) in form.items" :key="i" class="grid gap-2 md:grid-cols-[1.5fr_1fr_1fr_auto]">
                    <div>
                        <select v-model="item.ingredient_id" class="w-full rounded-md border-slate-300"><option :value="null">Bahan</option><option v-for="ingredient in ingredients" :key="ingredient.id" :value="ingredient.id">{{ ingredientLabel(ingredient) }}</option></select>
                        <div v-if="form.errors[`items.${i}.ingredient_id`]" class="mt-1 text-xs text-red-600">{{ form.errors[`items.${i}.ingredient_id`] }}</div>
                    </div>
                    <div>
                        <input v-model="item.quantity" type="number" min="0.001" step="0.001" class="w-full rounded-md border-slate-300" placeholder="Qty" />
                        <div v-if="form.errors[`items.${i}.quantity`]" class="mt-1 text-xs text-red-600">{{ form.errors[`items.${i}.quantity`] }}</div>
                    </div>
                    <input v-model="item.notes" class="rounded-md border-slate-300" placeholder="Catatan item" />
                    <button type="button" class="rounded-md p-2 text-red-600 hover:bg-red-50 disabled:text-slate-300" :disabled="form.items.length === 1" aria-label="Hapus item" title="Hapus item" @click="removeItem(i)"><Trash2 class="h-4 w-4" /></button>
                </div>
            </div>
            <div v-if="form.errors.items" class="mt-2 text-xs text-red-600">{{ form.errors.items }}</div>
            <div v-if="form.errors.stock" class="mt-2 text-xs text-red-600">{{ form.errors.stock }}</div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-slate-600">Estimasi biaya {{ money(formTotal) }}</div>
                <div class="flex gap-2">
                    <button type="button" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm" @click="addItem"><Plus class="h-4 w-4" /> Item</button>
                    <button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">{{ editingUsage ? 'Update' : 'Simpan' }}</button>
                </div>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-orange-100 bg-white">
            <table class="min-w-full text-sm">
                <thead><tr class="border-b bg-stone-50 text-left"><th class="p-3">Kode</th><th>Tipe</th><th>Status</th><th>Total Cost</th><th>Aksi</th></tr></thead>
                <tbody>
                    <tr v-for="usage in usages.data" :key="usage.id" class="border-b">
                        <td class="p-3 font-semibold">{{ usage.usage_code }}</td>
                        <td>{{ usage.usage_type }}</td>
                        <td>{{ usage.status }}</td>
                        <td>{{ money(usage.estimated_total_cost) }}</td>
                        <td class="space-x-2">
                            <button v-if="usage.status === 'draft'" class="text-orange-700" @click="editUsage(usage)"><Pencil class="inline h-4 w-4" /> Edit</button>
                            <button v-if="usage.status === 'draft'" class="text-red-600" @click="destroyUsage(usage.id)">Hapus</button>
                            <button v-if="canCancel && usage.status === 'completed'" class="text-red-600" @click="cancelUsage(usage.id)">Cancel</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
