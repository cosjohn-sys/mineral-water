<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    customers: { type: Object, required: true },
    filters: { type: Object, required: true },
    status: { type: String, default: null },
});

const filterForm = useForm({
    search: props.filters.search,
    per_page: props.filters.per_page,
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Customers" />
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Customers</h2>
                <Link :href="route('customers.create')" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Add customer</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <p v-if="status" role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ status }}</p>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <form @submit.prevent="filterForm.get(route('customers.index'), { preserveState: true, replace: true })" class="flex flex-col gap-4 p-6 sm:flex-row sm:items-end">
                        <div class="flex-1">
                            <InputLabel for="customer-search" value="Search customers" />
                            <TextInput id="customer-search" v-model="filterForm.search" type="search" maxlength="255" placeholder="Name, phone, or address" class="mt-1 block w-full" />
                            <InputError :message="filterForm.errors.search" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="customers-per-page" value="Rows per page" />
                            <select id="customers-per-page" v-model="filterForm.per_page" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
                            </select>
                            <InputError :message="filterForm.errors.per_page" class="mt-2" />
                        </div>
                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="filterForm.processing" :class="{ 'opacity-50': filterForm.processing }">Search</PrimaryButton>
                            <Link :href="route('customers.index')" class="text-sm text-gray-600 underline hover:text-gray-900">Clear</Link>
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <caption class="sr-only">Customers matching your search</caption>
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th scope="col" class="px-6 py-3 font-medium">Name</th>
                                    <th scope="col" class="px-6 py-3 font-medium">Phone</th>
                                    <th scope="col" class="px-6 py-3 font-medium">Address</th>
                                    <th scope="col" class="px-6 py-3 text-right font-medium">Outstanding balance</th>
                                    <th scope="col" class="px-6 py-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="customer in customers.data" :key="customer.id">
                                    <td class="max-w-xs break-words px-6 py-4 font-medium text-gray-900">{{ customer.name }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-gray-600">{{ customer.phone || '—' }}</td>
                                    <td class="max-w-sm break-words px-6 py-4 text-gray-600">{{ customer.address || '—' }}</td>
                                    <td class="px-6 py-4 text-right font-medium tabular-nums text-gray-900">{{ Number(customer.outstanding_balance).toFixed(2) }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-4">
                                            <Link :href="route('customers.show', customer.id)" :aria-label="`View ${customer.name}`" class="text-indigo-600 underline hover:text-indigo-900">View</Link>
                                            <Link :href="route('customers.edit', customer.id)" :aria-label="`Edit ${customer.name}`" class="text-indigo-600 underline hover:text-indigo-900">Edit</Link>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="customers.data.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">No customers found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :pagination="customers" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
