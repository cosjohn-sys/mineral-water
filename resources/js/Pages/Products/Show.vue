<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    product: { type: Object, required: true },
    status: { type: String, default: null },
});

const confirmingDeactivation = ref(false);
const deactivationForm = useForm({});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="product.name" />
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Product details</h2>
                <Link :href="route('products.index')" class="text-sm text-gray-600 underline hover:text-gray-900">Back to products</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <p v-if="status" role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ status }}</p>
                <section class="flex flex-col gap-6 bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="break-words text-lg font-medium text-gray-900">{{ product.name }}</h3>
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="product.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'">{{ product.is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                    <dl class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Price</dt>
                            <dd class="mt-1 tabular-nums text-gray-900">{{ Number(product.price).toFixed(2) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Stock quantity</dt>
                            <dd class="mt-1 text-gray-900">{{ product.stock_quantity ?? 'Not tracked' }}</dd>
                        </div>
                    </dl>
                    <div class="flex flex-wrap items-center gap-4">
                        <Link :href="route('products.edit', product.id)" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Edit product</Link>
                        <DangerButton v-if="product.is_active" @click="confirmingDeactivation = true">Deactivate product</DangerButton>
                    </div>
                    <p v-if="!product.is_active" class="text-sm text-gray-600">Edit this product and check Active product to reactivate it.</p>
                </section>
            </div>
        </div>

        <Modal :show="confirmingDeactivation" :closeable="!deactivationForm.processing" aria-labelledby="deactivate-product-title" @close="confirmingDeactivation = false">
            <form @submit.prevent="deactivationForm.delete(route('products.destroy', product.id), { preserveScroll: true, preserveState: false })" class="flex flex-col gap-4 p-6">
                <h2 id="deactivate-product-title" class="text-lg font-medium text-gray-900">Deactivate product?</h2>
                <p class="text-sm text-gray-600">{{ product.name }} will be marked inactive. Its details and sale history will be kept.</p>
                <InputError :message="deactivationForm.errors.product" />
                <div class="flex justify-end gap-3">
                    <SecondaryButton :disabled="deactivationForm.processing" @click="confirmingDeactivation = false">Cancel</SecondaryButton>
                    <DangerButton type="submit" :disabled="deactivationForm.processing" :class="{ 'opacity-50': deactivationForm.processing }">{{ deactivationForm.processing ? 'Deactivating…' : 'Deactivate product' }}</DangerButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
