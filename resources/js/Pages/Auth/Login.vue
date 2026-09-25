<script setup>
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    canResetPassword: Boolean,
    status: String,
    devCredentials: Object,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const fillDev = () => {
    if (!props.devCredentials) return;
    form.email = props.devCredentials.email;
    form.password = props.devCredentials.password;
};
</script>

<template>
    <Head title="Entrar" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div
            v-if="devCredentials"
            class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900"
        >
            <p class="font-bold">Acesso de desenvolvimento</p>
            <p class="mt-1">
                E-mail:
                <code class="font-semibold">{{ devCredentials.email }}</code>
            </p>
            <p>
                Senha:
                <code class="font-semibold">{{ devCredentials.password }}</code>
            </p>
            <button
                type="button"
                @click="fillDev"
                class="mt-2 inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-bold text-white transition hover:brightness-110"
                style="background: linear-gradient(90deg, #2aabee, #229ed8)"
            >
                Preencher acesso admin
            </button>
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="E-mail" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Senha" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox v-model:checked="form.remember" name="remember" />
                    <span class="ms-2 text-sm text-gray-600"
                        >Lembrar de mim</span
                    >
                </label>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Esqueceu a senha?
                </Link>

                <button
                    type="submit"
                    :disabled="form.processing"
                    :class="{ 'opacity-25': form.processing }"
                    class="ms-4 inline-flex items-center rounded-xl px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 disabled:opacity-50"
                    style="
                        background: linear-gradient(
                            135deg,
                            #2aabee 0%,
                            #1b87b8 100%
                        );
                        box-shadow: 0 4px 12px rgba(42, 171, 238, 0.35);
                    "
                >
                    Entrar
                </button>
            </div>
        </form>
    </AuthenticationCard>
</template>
