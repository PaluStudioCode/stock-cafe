<script setup>
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { labelForValue } from '@/utilities/formatters';
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({ orders: Object, suppliers: Array, ingredients: Array });
const showForm = ref(false);
const editingOrder = ref(null);
const form = useForm({ supplier_id: null, purchase_date: new Date().toISOString().slice(0, 10), discount: 0, notes: '', items: [{ ingredient_id: null, quantity: 1, unit_cost: 0 }] });
const itemSubtotal = (item) => Number(item.quantity || 0) * Number(item.unit_cost || 0);
const formSubtotal = computed(() => form.items.reduce((sum, item) => sum + itemSubtotal(item), 0));
const formTotal = computed(() => formSubtotal.value - Number(form.discount || 0));
const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);
const ingredientLabel = (ingredient) => `${ingredient.name} (${ingredient.unit?.symbol || '-'})`;

const addItem = () => form.items.push({ ingredient_id: null, quantity: 1, unit_cost: 0 });
const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};
const resetForm = () => {
    editingOrder.value = null;
    form.reset();
    form.supplier_id = null;
    form.purchase_date = new Date().toISOString().slice(0, 10);
    form.discount = 0;
    form.notes = '';
    form.items = [{ ingredient_id: null, quantity: 1, unit_cost: 0 }];
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
const editOrder = (order) => {
    editingOrder.value = order;
    form.supplier_id = order.supplier_id;
    form.purchase_date = order.purchase_date?.slice(0, 10);
    form.discount = order.discount;
    form.notes = order.notes || '';
    form.items = order.items.map((item) => ({ ingredient_id: item.ingredient_id, quantity: item.quantity, unit_cost: item.unit_cost }));
    form.clearErrors();
    showForm.value = true;
};
const submit = () => {
    const options = { preserveScroll: true, onSuccess: closeForm };

    if (editingOrder.value) {
        form.put(route('purchase-orders.update', editingOrder.value.id), options);
        return;
    }

    form.post(route('purchase-orders.store'), options);
};
const receive = (id) => confirm('Terima pesanan pembelian dan tambahkan stok?') && router.post(route('purchase-orders.receive', id));
const remove = (id) => confirm('Hapus draf pesanan pembelian?') && router.delete(route('purchase-orders.destroy', id));
</script>

<template>
    <AuthenticatedLayout title="Pesanan Pembelian">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Pesanan Pembelian</h1>
            <button type="button" class="inline-flex items-center gap-2 rounded-md bg-orange-600 px-3 py-2 text-sm font-semibold text-white hover:bg-orange-700" @click="openCreate">
                <Plus class="h-4 w-4" /> Tambah
            </button>
        </div>

        <Modal :show="showForm" max-width="2xl" @close="closeForm">
            <form class="max-h-[90vh] overflow-y-auto p-5" @submit.prevent="submit">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-bold text-slate-800">{{ editingOrder ? 'Ubah Draf Pesanan Pembelian' : 'Tambah Pesanan Pembelian' }}</h2>
                    <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label="Tutup form" title="Tutup form" @click="closeForm">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-4">
                    <select v-model="form.supplier_id" class="rounded-md border-slate-300">
                        <option :value="null">Pilih supplier</option>
                        <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                    </select>
                    <input v-model="form.purchase_date" type="date" class="rounded-md border-slate-300" />
                    <input v-model="form.discount" type="number" min="0" class="rounded-md border-slate-300" placeholder="Diskon" />
                    <input v-model="form.notes" class="rounded-md border-slate-300" placeholder="Catatan" />
                </div>
                <div class="mt-1 grid gap-2 text-xs text-red-600 md:grid-cols-4">
                    <div>{{ form.errors.supplier_id }}</div>
                    <div>{{ form.errors.purchase_date }}</div>
                    <div>{{ form.errors.discount }}</div>
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
                            <input v-model="item.quantity" type="number" step="0.001" min="0.001" class="w-full rounded-md border-slate-300" placeholder="Jumlah" />
                            <div v-if="form.errors[`items.${index}.quantity`]" class="mt-1 text-xs text-red-600">{{ form.errors[`items.${index}.quantity`] }}</div>
                        </div>
                        <div>
                            <input v-model="item.unit_cost" type="number" min="0" class="w-full rounded-md border-slate-300" placeholder="Harga satuan" />
                            <div v-if="form.errors[`items.${index}.unit_cost`]" class="mt-1 text-xs text-red-600">{{ form.errors[`items.${index}.unit_cost`] }}</div>
                        </div>
                        <button type="button" class="rounded-md p-2 text-red-600 hover:bg-red-50 disabled:text-slate-300" :disabled="form.items.length === 1" aria-label="Hapus item" title="Hapus item" @click="removeItem(index)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
                <div v-if="form.errors.items" class="mt-2 text-xs text-red-600">{{ form.errors.items }}</div>
                <div v-if="form.errors.stock" class="mt-2 text-xs text-red-600">{{ form.errors.stock }}</div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-slate-600">Subtotal {{ money(formSubtotal) }} - Total {{ money(formTotal) }}</div>
                    <div class="flex gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm" @click="addItem">
                            <Plus class="h-4 w-4" /> Tambah Item
                        </button>
                        <button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : editingOrder ? 'Perbarui Draf' : 'Simpan Draf' }}
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
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!orders.data.length">
                        <td colspan="5" class="p-6 text-center text-sm text-slate-500">Belum ada pesanan pembelian.</td>
                    </tr>
                    <tr v-for="order in orders.data" :key="order.id" class="border-b">
                        <td class="p-3 font-semibold">{{ order.purchase_code }}</td>
                        <td>{{ order.supplier?.name || '-' }}</td>
                        <td>{{ labelForValue(order.status) }}</td>
                        <td>{{ money(order.total_amount) }}</td>
                        <td class="space-x-2">
                            <button v-if="order.status === 'draft'" class="text-orange-700" @click="editOrder(order)"><Pencil class="inline h-4 w-4" /> Ubah</button>
                            <button v-if="order.status === 'draft'" class="text-orange-700" @click="receive(order.id)">Terima</button>
                            <button v-if="order.status === 'draft'" class="text-red-600" @click="remove(order.id)">Hapus</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
