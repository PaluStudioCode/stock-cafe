<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ canResetPassword: Boolean, status: String });

const form = useForm({ email: 'owner@cafestock.test', password: 'password', remember: false });
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <GuestLayout>
        <Head title="Masuk" />
        <div v-if="status" class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ status }}</div>
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="text-sm font-semibold text-slate-700">Email</label>
                <input id="email" v-model="form.email" type="email" class="mt-1 w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500" required autofocus />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>
            <div>
                <label for="password" class="text-sm font-semibold text-slate-700">Password</label>
                <input id="password" v-model="form.password" type="password" class="mt-1 w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500" required />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <Checkbox v-model:checked="form.remember" name="remember" />
                Ingat saya
            </label>
            <div class="flex items-center justify-between gap-3">
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-medium text-orange-700 hover:text-orange-900">Lupa password?</Link>
                <button class="ml-auto rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-60" :disabled="form.processing">
                    {{ form.processing ? 'Memproses...' : 'Masuk' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
