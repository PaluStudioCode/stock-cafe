<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ status: String });

const form = useForm({ email: '' });
const submit = () => form.post(route('password.email'));
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <div class="mb-5">
            <h1 class="text-xl font-bold text-slate-900">Reset Password</h1>
            <p class="mt-1 text-sm text-slate-500">Masukkan email akun CafeStock. Link reset akan dikirim lewat email.</p>
        </div>

        <div v-if="status" class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ status }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="text-sm font-semibold text-slate-700">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="flex items-center justify-between gap-3">
                <Link :href="route('login')" class="text-sm font-medium text-orange-700 hover:text-orange-900">Kembali login</Link>
                <button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-60" :disabled="form.processing">
                    {{ form.processing ? 'Mengirim...' : 'Kirim Link' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
