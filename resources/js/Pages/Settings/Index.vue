<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Save } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    title: String,
    settings: Object,
    definitions: Array,
});

const form = useForm({
    settings: { ...props.settings },
});
const selectedFormats = ref(String(props.settings?.report_export_formats || '')
    .split(',')
    .map((format) => format.trim())
    .filter(Boolean));

const errorFor = (key) => form.errors[`settings.${key}`];

const submit = () => {
    form.settings.report_export_formats = selectedFormats.value.join(',');
    form.put(route('admin.settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AuthenticatedLayout :title="title">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-bold">{{ title }}</h1>
            <button
                type="button"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-orange-600 px-4 text-sm font-semibold text-white hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="form.processing"
                @click="submit"
            >
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <Save v-else class="h-4 w-4" />
                {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
            </button>
        </div>

        <InputError class="mb-4" :message="form.errors.settings" />

        <form class="grid gap-4 lg:grid-cols-2" @submit.prevent="submit">
            <div
                v-for="definition in definitions"
                :key="definition.key"
                class="rounded-lg border border-orange-100 bg-white p-4 shadow-sm"
            >
                <label class="text-xs font-bold uppercase text-slate-500" :for="definition.key">
                    {{ definition.label }}
                </label>

                <textarea
                    v-if="definition.type === 'textarea'"
                    :id="definition.key"
                    v-model="form.settings[definition.key]"
                    class="mt-2 w-full rounded-md border-slate-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                    rows="3"
                />

                <select
                    v-else-if="definition.type === 'select'"
                    :id="definition.key"
                    v-model="form.settings[definition.key]"
                    class="mt-2 h-10 w-full rounded-md border-slate-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                >
                    <option v-for="option in definition.options" :key="option" :value="option">
                        {{ option }}
                    </option>
                </select>

                <div v-else-if="definition.type === 'formats'" class="mt-3 flex flex-wrap gap-3">
                    <label
                        v-for="option in definition.options"
                        :key="option"
                        class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-200 px-3 text-sm font-semibold text-slate-700"
                    >
                        <input
                            v-model="selectedFormats"
                            type="checkbox"
                            :value="option"
                            class="rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                        />
                        {{ option.toUpperCase() }}
                    </label>
                </div>

                <input
                    v-else
                    :id="definition.key"
                    v-model="form.settings[definition.key]"
                    :type="definition.type === 'number' ? 'number' : 'text'"
                    :min="definition.min"
                    :step="definition.step"
                    class="mt-2 h-10 w-full rounded-md border-slate-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                />

                <InputError class="mt-2" :message="errorFor(definition.key)" />
            </div>
        </form>
    </AuthenticatedLayout>
</template>
