<script setup>
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { labelForValue } from '@/utilities/formatters';
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({ adjustments: Object, ingredients: Array, canApprove: Boolean, canCancel: Boolean });
const showForm = ref(false);
const editingAdjustment = ref(null);
const form = useForm({ adjustment_date: new Date().toISOString().slice(0, 16), reason: '', items: [{ ingredient_id: null, counted_stock: 0, notes: '' }] });
const ingredientMap = computed(() => new Map(props.ingredients.map((ingredient) => [ingredient.id, ingredient])));
const systemStock = (item) => Number(ingredientMap.value.get(item.ingredient_id)?.current_stock || 0);
const difference = (item) => Number(item.counted_stock || 0) - systemStock(item);
const ingredientLabel = (ingredient) => `${ingredient.name} (${ingredient.current_stock} ${ingredient.unit?.symbol || '-'})`;

const addItem = () => form.items.push({ ingredient_id: null, counted_stock: 0, notes: '' });
const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};
const resetForm = () => {
    editingAdjustment.value = null;
    form.reset();
    form.adjustment_date = new Date().toISOString().slice(0, 16);
    form.reason = '';
    form.items = [{ ingredient_id: null, counted_stock: 0, notes: '' }];
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
const editAdjustment = (adjustment) => {
    editingAdjustment.value = adjustment;
    form.adjustment_date = adjustment.adjustment_date?.slice(0, 16);
    form.reason = adjustment.reason;
    form.items = adjustment.items.map((item) => ({ ingredient_id: item.ingredient_id, counted_stock: item.counted_stock, notes: item.notes || '' }));
    form.clearErrors();
    showForm.value = true;
};
const submit = () => {
    const options = { preserveScroll: true, onSuccess: closeForm };

    if (editingAdjustment.value) {
        form.put(route('stock-adjustments.update', editingAdjustment.value.id), options);
        return;
    }

    form.post(route('stock-adjustments.store'), options);
};
const approve = (id) => confirm('Setujui penyesuaian stok dan ubah stok?') && router.post(route('stock-adjustments.approve', id));
const cancel = (id) => confirm('Batalkan penyesuaian stok? Penyesuaian yang sudah disetujui akan membuat pergerakan pembalik.') && router.post(route('stock-adjustments.cancel', id));
</script>

<template>
    <AuthenticatedLayout title="Penyesuaian Stok">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Penyesuaian Stok</h1>
            <button type="button" class="inline-flex items-center gap-2 rounded-md bg-orange-600 px-3 py-2 text-sm font-semibold text-white hover:bg-orange-700" @click="openCreate">
                <Plus class="h-4 w-4" /> Tambah
            </button>
        </div>

        <Modal :show="showForm" max-width="2xl" @close="closeForm">
            <form class="max-h-[90vh] overflow-y-auto p-5" @submit.prevent="submit">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-bold text-slate-800">{{ editingAdjustment ? 'Ubah Draf Penyesuaian Stok' : 'Tambah Penyesuaian Stok' }}</h2>
                    <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label="Tutup form" title="Tutup form" @click="closeForm">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <input v-model="form.adjustment_date" type="datetime-local" class="rounded-md border-slate-300" />
                    <input v-model="form.reason" class="rounded-md border-slate-300" placeholder="Alasan wajib" />
                </div>
                <div class="mt-1 grid gap-2 text-xs text-red-600 md:grid-cols-2">
                    <div>{{ form.errors.adjustment_date }}</div>
                    <div>{{ form.errors.reason }}</div>
                </div>

                <div class="mt-4 space-y-2">
                    <div v-for="(item, index) in form.items" :key="index" class="grid gap-2 md:grid-cols-[1.5fr_1fr_1fr_1fr_auto]">
                        <div>
                            <select v-model="item.ingredient_id" class="w-full rounded-md border-slate-300">
                                <option :value="null">Pilih bahan</option>
                                <option v-for="ingredient in ingredients" :key="ingredient.id" :value="ingredient.id">{{ ingredientLabel(ingredient) }}</option>
                            </select>
                            <div v-if="form.errors[`items.${index}.ingredient_id`]" class="mt-1 text-xs text-red-600">{{ form.errors[`items.${index}.ingredient_id`] }}</div>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">Stok sistem {{ systemStock(item).toFixed(3) }}</div>
                        <div>
                            <input v-model="item.counted_stock" type="number" min="0" step="0.001" class="w-full rounded-md border-slate-300" placeholder="Stok hitung" />
                            <div v-if="form.errors[`items.${index}.counted_stock`]" class="mt-1 text-xs text-red-600">{{ form.errors[`items.${index}.counted_stock`] }}</div>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm" :class="difference(item) < 0 ? 'text-red-600' : difference(item) > 0 ? 'text-emerald-700' : 'text-slate-600'">Selisih {{ difference(item).toFixed(3) }}</div>
                        <button type="button" class="rounded-md p-2 text-red-600 hover:bg-red-50 disabled:text-slate-300" :disabled="form.items.length === 1" aria-label="Hapus item" title="Hapus item" @click="removeItem(index)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                        <input v-model="item.notes" class="rounded-md border-slate-300 md:col-span-4" placeholder="Catatan item" />
                    </div>
                </div>
                <div v-if="form.errors.items" class="mt-2 text-xs text-red-600">{{ form.errors.items }}</div>
                <div v-if="form.errors.stock" class="mt-2 text-xs text-red-600">{{ form.errors.stock }}</div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm" @click="addItem">
                        <Plus class="h-4 w-4" /> Tambah Item
                    </button>
                    <button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan...' : editingAdjustment ? 'Perbarui Draf' : 'Simpan Draf' }}
                    </button>
                </div>
            </form>
        </Modal>

        <div class="overflow-x-auto rounded-lg border border-orange-100 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b bg-stone-50 text-left">
                        <th class="p-3">Kode</th>
                        <th>Status</th>
                        <th>Alasan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!adjustments.data.length">
                        <td colspan="4" class="p-6 text-center text-sm text-slate-500">Belum ada penyesuaian stok.</td>
                    </tr>
                    <tr v-for="adjustment in adjustments.data" :key="adjustment.id" class="border-b">
                        <td class="p-3 font-semibold">{{ adjustment.adjustment_code }}</td>
                        <td>{{ labelForValue(adjustment.status) }}</td>
                        <td>{{ adjustment.reason }}</td>
                        <td class="space-x-2">
                            <button v-if="adjustment.status === 'draft'" class="text-orange-700" @click="editAdjustment(adjustment)"><Pencil class="inline h-4 w-4" /> Ubah</button>
                            <button v-if="canApprove && adjustment.status === 'draft'" class="text-orange-700" @click="approve(adjustment.id)">Setujui</button>
                            <button v-if="canCancel && adjustment.status !== 'cancelled'" class="text-red-600" @click="cancel(adjustment.id)">Batalkan</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
