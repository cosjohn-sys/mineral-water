<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    product: { type: Object, default: null },
});

const form = useForm({
    name: props.product?.name ?? '',
    price: props.product ? String(props.product.price) : '',
    stock_quantity: props.product?.stock_quantity == null ? '' : String(props.product.stock_quantity),
    is_active: Boolean(props.product?.is_active ?? true),
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="product ? 'Edit product' : 'Add product'" />
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ product ? 'Edit product' : 'Add product' }}</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <form @submit.prevent="product ? form.put(route('products.update', product.id)) : form.post(route('products.store'))" class="flex max-w-xl flex-col gap-6">
                        <div>
                            <InputLabel for="name" value="Name" />
                            <TextInput id="name" v-model="form.name" type="text" maxlength="255" required autofocus class="mt-1 block w-full" :aria-invalid="Boolean(form.errors.name)" aria-describedby="name-error" />
                            <InputError id="name-error" :message="form.errors.name" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="price" value="Price" />
                            <TextInput id="price" v-model="form.price" type="number" min="0" max="99999999.99" step="0.01" required class="mt-1 block w-full" :aria-invalid="Boolean(form.errors.price)" aria-describedby="price-error" />
                            <InputError id="price-error" :message="form.errors.price" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="stock_quantity" value="Stock quantity (optional)" />
                            <TextInput id="stock_quantity" v-model="form.stock_quantity" type="number" min="0" max="4294967295" step="1" class="mt-1 block w-full" :aria-invalid="Boolean(form.errors.stock_quantity)" aria-describedby="stock-help stock-error" />
                            <p id="stock-help" class="mt-2 text-sm text-gray-600">Leave blank for products without stock tracking. Enter 0 when tracked stock is empty.</p>
                            <InputError id="stock-error" :message="form.errors.stock_quantity" class="mt-2" />
                        </div>
                        <div>
                            <label class="flex items-center gap-2">
                                <Checkbox v-model:checked="form.is_active" aria-describedby="active-help active-error" />
                                <span class="text-sm text-gray-700">Active product</span>
                            </label>
                            <p id="active-help" class="mt-2 text-sm text-gray-600">Uncheck to deactivate. Check to reactivate an existing product.</p>
                            <InputError id="active-error" :message="form.errors.is_active" class="mt-2" />
                        </div>
                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing" :class="{ 'opacity-50': form.processing }">{{ form.processing ? 'Saving…' : 'Save product' }}</PrimaryButton>
                            <Link :href="product ? route('products.show', product.id) : route('products.index')" class="text-sm text-gray-600 underline hover:text-gray-900">Cancel</Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
