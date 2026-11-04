<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    customer: { type: Object, default: null },
});

const form = useForm({
    name: props.customer?.name ?? '',
    phone: props.customer?.phone ?? '',
    address: props.customer?.address ?? '',
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="customer ? 'Edit customer' : 'Add customer'" />
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ customer ? 'Edit customer' : 'Add customer' }}</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <form @submit.prevent="customer ? form.put(route('customers.update', customer.id)) : form.post(route('customers.store'))" class="flex max-w-xl flex-col gap-6">
                        <div>
                            <InputLabel for="name" value="Name" />
                            <TextInput id="name" v-model="form.name" type="text" maxlength="255" required autofocus autocomplete="name" class="mt-1 block w-full" :aria-invalid="Boolean(form.errors.name)" aria-describedby="name-error" />
                            <InputError id="name-error" :message="form.errors.name" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="phone" value="Phone (optional)" />
                            <TextInput id="phone" v-model="form.phone" type="tel" maxlength="255" autocomplete="tel" class="mt-1 block w-full" :aria-invalid="Boolean(form.errors.phone)" aria-describedby="phone-error" />
                            <InputError id="phone-error" :message="form.errors.phone" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="address" value="Address (optional)" />
                            <textarea id="address" v-model="form.address" rows="4" maxlength="10000" autocomplete="street-address" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :aria-invalid="Boolean(form.errors.address)" aria-describedby="address-error" />
                            <InputError id="address-error" :message="form.errors.address" class="mt-2" />
                        </div>
                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing" :class="{ 'opacity-50': form.processing }">{{ form.processing ? 'Saving…' : 'Save customer' }}</PrimaryButton>
                            <Link :href="customer ? route('customers.show', customer.id) : route('customers.index')" class="text-sm text-gray-600 underline hover:text-gray-900">Cancel</Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
