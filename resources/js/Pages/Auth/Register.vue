<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Daftar" />

        <div class="mb-5">
            <h1 class="text-xl font-bold text-slate-900">Buat Akun CafeStock</h1>
            <p class="mt-1 text-sm text-slate-500">Untuk MVP, akun operasional nantinya dikelola oleh Owner.</p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="name" class="text-sm font-semibold text-slate-700">Nama</label>
                <input id="name" v-model="form.name" type="text" class="mt-1 w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500" required autofocus autocomplete="name" />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>
            <div>
                <label for="email" class="text-sm font-semibold text-slate-700">Email</label>
                <input id="email" v-model="form.email" type="email" class="mt-1 w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500" required autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>
            <div>
                <label for="password" class="text-sm font-semibold text-slate-700">Password</label>
                <input id="password" v-model="form.password" type="password" class="mt-1 w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500" required autocomplete="new-password" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <div>
                <label for="password_confirmation" class="text-sm font-semibold text-slate-700">Konfirmasi Password</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500" required autocomplete="new-password" />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>
            <div class="flex items-center justify-between gap-3">
                <Link :href="route('login')" class="text-sm font-medium text-orange-700 hover:text-orange-900">Sudah punya akun?</Link>
                <button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-60" :disabled="form.processing">
                    {{ form.processing ? 'Membuat...' : 'Daftar' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
