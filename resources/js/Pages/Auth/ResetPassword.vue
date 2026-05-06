<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <div class="mb-5">
            <h1 class="text-xl font-bold text-slate-900">Buat Password Baru</h1>
            <p class="mt-1 text-sm text-slate-500">Gunakan password baru untuk masuk ke CafeStock.</p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="text-sm font-semibold text-slate-700">Email</label>
                <input id="email" v-model="form.email" type="email" class="mt-1 w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500" required autofocus autocomplete="username" />
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
            <div class="flex justify-end">
                <button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-60" :disabled="form.processing">
                    {{ form.processing ? 'Menyimpan...' : 'Reset Password' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
