<script setup>
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { labelForValue } from '@/utilities/formatters';
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({ usages: Object, ingredients: Array, usageTypes: Array, canCancel: Boolean });
const showForm = ref(false);
const editingUsage = ref(null);
const form = useForm({ usage_date: new Date().toISOString().slice(0, 16), usage_type: 'waste', status: 'draft', notes: '', items: [{ ingredient_id: null, quantity: 1, notes: '' }] });
const ingredientMap = computed(() => new Map(props.ingredients.map((ingredient) => [ingredient.id, ingredient])));
const itemCost = (item) => Number(item.quantity || 0) * Number(ingredientMap.value.get(item.ingredient_id)?.last_unit_cost || 0);
const formTotal = computed(() => form.items.reduce((sum, item) => sum + itemCost(item), 0));
const ingredientLabel = (ingredient) => `${ingredient.name} (${ingredient.current_stock} ${ingredient.unit?.symbol || '-'})`;
const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);

const addItem = () => form.items.push({ ingredient_id: null, quantity: 1, notes: '' });
const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
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
const openCreate = () => {
    resetForm();
    showForm.value = true;
};
const closeForm = () => {
    showForm.value = false;
    resetForm();
};
const editUsage = (usage) => {
    editingUsage.value = usage;
    form.usage_date = usage.usage_date?.slice(0, 16);
    form.usage_type = usage.usage_type;
    form.status = 'draft';
    form.notes = usage.notes || '';
    form.items = usage.items.map((item) => ({ ingredient_id: item.ingredient_id, quantity: item.quantity, notes: item.notes || '' }));
    form.clearErrors();
    showForm.value = true;
};
const submit = () => {
    const options = { preserveScroll: true, onSuccess: closeForm };

    if (editingUsage.value) {
        form.put(route('stock-usages.update', editingUsage.value.id), options);
        return;
    }

    form.post(route('stock-usages.store'), options);
};
const destroyUsage = (id) => confirm('Hapus draf pemakaian stok?') && router.delete(route('stock-usages.destroy', id));
const cancelUsage = (id) => confirm('Batalkan pemakaian stok dan kembalikan stok?') && router.post(route('stock-usages.cancel', id));
</script>

<template>
    <AuthenticatedLayout title="Pemakaian Stok">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Pemakaian Stok</h1>
            <button type="button" class="inline-flex items-center gap-2 rounded-md bg-orange-600 px-3 py-2 text-sm font-semibold text-white hover:bg-orange-700" @click="openCreate">
                <Plus class="h-4 w-4" /> Tambah
            </button>
        </div>

        <Modal :show="showForm" max-width="2xl" @close="closeForm">
            <form class="max-h-[90vh] overflow-y-auto p-5" @submit.prevent="submit">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-bold text-slate-800">{{ editingUsage ? 'Ubah Draf Pemakaian Stok' : 'Tambah Pemakaian Stok' }}</h2>
                    <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label="Tutup form" title="Tutup form" @click="closeForm">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-4">
                    <input v-model="form.usage_date" type="datetime-local" class="rounded-md border-slate-300" />
                    <select v-model="form.usage_type" class="rounded-md border-slate-300">
                        <option v-for="type in usageTypes" :key="type" :value="type">{{ labelForValue(type) }}</option>
                    </select>
                    <select v-model="form.status" class="rounded-md border-slate-300">
                        <option value="draft">Draf</option>
                        <option value="completed">Selesai</option>
                    </select>
                    <input v-model="form.notes" class="rounded-md border-slate-300" placeholder="Catatan" />
                </div>
                <div class="mt-1 grid gap-2 text-xs text-red-600 md:grid-cols-4">
                    <div>{{ form.errors.usage_date }}</div>
                    <div>{{ form.errors.usage_type }}</div>
                    <div>{{ form.errors.status }}</div>
                    <div>{{ form.errors.notes }}</div>
                </div>

                <div class="mt-4 space-y-2">
                    <div v-for="(item, index) in form.items" :key="index" class="grid gap-2 md:grid-cols-[1.5fr_1fr_1fr_auto]">
                        <div>
                            <select v-model="item.ingredient_id" class="w-full rounded-md border-slate-300">
                                <option :value="null">Pilih bahan</option>
                                <option v-for="ingredient in ingredients" :key="ingredient.id" :value="ingredient.id">{{ ingredientLabel(ingredient) }}</option>
                            </select>
                            <div v-if="form.errors[`items.${index}.ingredient_id`]" class="mt-1 text-xs text-red-600">{{ form.errors[`items.${index}.ingredient_id`] }}</div>
                        </div>
                        <div>
                            <input v-model="item.quantity" type="number" min="0.001" step="0.001" class="w-full rounded-md border-slate-300" placeholder="Jumlah" />
                            <div v-if="form.errors[`items.${index}.quantity`]" class="mt-1 text-xs text-red-600">{{ form.errors[`items.${index}.quantity`] }}</div>
                        </div>
                        <input v-model="item.notes" class="rounded-md border-slate-300" placeholder="Catatan item" />
                        <button type="button" class="rounded-md p-2 text-red-600 hover:bg-red-50 disabled:text-slate-300" :disabled="form.items.length === 1" aria-label="Hapus item" title="Hapus item" @click="removeItem(index)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
                <div v-if="form.errors.items" class="mt-2 text-xs text-red-600">{{ form.errors.items }}</div>
                <div v-if="form.errors.stock" class="mt-2 text-xs text-red-600">{{ form.errors.stock }}</div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-slate-600">Estimasi biaya {{ money(formTotal) }}</div>
                    <div class="flex gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm" @click="addItem">
                            <Plus class="h-4 w-4" /> Tambah Item
                        </button>
                        <button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : editingUsage ? 'Perbarui' : 'Simpan' }}
                        </button>
                    </div>
                </div>
            </form>
        </Modal>

        <div class="overflow-x-auto rounded-lg border border-orange-100 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b bg-stone-50 text-left">
                        <th class="p-3">Kode</th>
                        <th>Jenis</th>
                        <th>Status</th>
                        <th>Estimasi Biaya</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!usages.data.length">
                        <td colspan="5" class="p-6 text-center text-sm text-slate-500">Belum ada pemakaian stok.</td>
                    </tr>
                    <tr v-for="usage in usages.data" :key="usage.id" class="border-b">
                        <td class="p-3 font-semibold">{{ usage.usage_code }}</td>
                        <td>{{ labelForValue(usage.usage_type) }}</td>
                        <td>{{ labelForValue(usage.status) }}</td>
                        <td>{{ money(usage.estimated_total_cost) }}</td>
                        <td class="space-x-2">
                            <button v-if="usage.status === 'draft'" class="text-orange-700" @click="editUsage(usage)"><Pencil class="inline h-4 w-4" /> Ubah</button>
                            <button v-if="usage.status === 'draft'" class="text-red-600" @click="destroyUsage(usage.id)">Hapus</button>
                            <button v-if="canCancel && usage.status === 'completed'" class="text-red-600" @click="cancelUsage(usage.id)">Batalkan</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
