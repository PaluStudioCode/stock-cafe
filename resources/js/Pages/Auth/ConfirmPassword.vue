<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ password: '' });
const submit = () => form.post(route('password.confirm'), { onFinish: () => form.reset() });
</script>

<template>
    <GuestLayout>
        <Head title="Konfirmasi Password" />

        <div class="mb-5">
            <h1 class="text-xl font-bold text-slate-900">Konfirmasi Password</h1>
            <p class="mt-1 text-sm text-slate-500">Area ini membutuhkan verifikasi ulang sebelum melanjutkan.</p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="password" class="text-sm font-semibold text-slate-700">Password</label>
                <input id="password" v-model="form.password" type="password" class="mt-1 w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500" required autocomplete="current-password" autofocus />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <div class="flex justify-end">
                <button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-60" :disabled="form.processing">
                    {{ form.processing ? 'Memeriksa...' : 'Konfirmasi' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
