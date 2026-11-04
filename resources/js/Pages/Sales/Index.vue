<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SalesNavigation from '@/Components/SalesNavigation.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    sales: { type: Object, required: true },
    filters: { type: Object, required: true },
    timezone: { type: String, required: true },
});

const filterForm = useForm({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    per_page: props.filters.per_page,
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Sales history" />
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Sales history</h2>
                <Link :href="route('sales.create')" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">New sale</Link>
            </div>
        </template>

        <div class="px-5 pt-3 sm:px-7 lg:px-8"><SalesNavigation /></div>
        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <form @submit.prevent="filterForm.get(route('sales.index'), { preserveState: true, replace: true })" class="flex flex-col gap-4 p-6 lg:flex-row lg:items-end">
                        <div class="flex-1">
                            <InputLabel for="date-from" :value="`From date (${timezone})`" />
                            <TextInput id="date-from" v-model="filterForm.date_from" type="date" class="mt-1 block w-full" :aria-invalid="Boolean(filterForm.errors.date_from)" aria-describedby="date-from-error" />
                            <InputError id="date-from-error" :message="filterForm.errors.date_from" class="mt-2" />
                        </div>
                        <div class="flex-1">
                            <InputLabel for="date-to" :value="`To date (${timezone})`" />
                            <TextInput id="date-to" v-model="filterForm.date_to" type="date" class="mt-1 block w-full" :aria-invalid="Boolean(filterForm.errors.date_to)" aria-describedby="date-to-error" />
                            <InputError id="date-to-error" :message="filterForm.errors.date_to" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="sales-per-page" value="Rows per page" />
                            <select id="sales-per-page" v-model="filterForm.per_page" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
                            </select>
                            <InputError :message="filterForm.errors.per_page" class="mt-2" />
                        </div>
                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="filterForm.processing">Filter</PrimaryButton>
                            <Link :href="route('sales.index')" class="text-sm text-gray-600 underline hover:text-gray-900">Clear</Link>
                        </div>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <caption class="sr-only">Sales matching the selected dates</caption>
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th scope="col" class="px-6 py-3 font-medium">Sale number</th>
                                    <th scope="col" class="px-6 py-3 font-medium">Date ({{ timezone }})</th>
                                    <th scope="col" class="px-6 py-3 font-medium">Type</th>
                                    <th scope="col" class="px-6 py-3 font-medium">Customer</th>
                                    <th scope="col" class="px-6 py-3 text-right font-medium">Total</th>
                                    <th scope="col" class="px-6 py-3 text-right font-medium">Paid</th>
                                    <th scope="col" class="px-6 py-3 text-right font-medium">Outstanding</th>
                                    <th scope="col" class="px-6 py-3 font-medium">Status</th>
                                    <th scope="col" class="px-6 py-3 text-right font-medium">Receipt</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="sale in sales.data" :key="sale.id">
                                    <td class="break-all px-6 py-4 font-medium text-gray-900">{{ sale.sale_number }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-gray-600">{{ sale.sold_at }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-gray-600">{{ sale.sale_type === 'delivery' ? 'Delivery' : 'Walk-in' }}</td>
                                    <td class="max-w-xs break-words px-6 py-4 text-gray-600">{{ sale.customer_name ?? 'No customer selected' }}</td>
                                    <td class="px-6 py-4 text-right tabular-nums text-gray-900">{{ Number(sale.total_amount).toFixed(2) }}</td>
                                    <td class="px-6 py-4 text-right tabular-nums text-gray-600">{{ Number(sale.amount_paid).toFixed(2) }}</td>
                                    <td class="px-6 py-4 text-right font-medium tabular-nums text-gray-900">{{ Number(sale.outstanding_balance).toFixed(2) }}</td>
                                    <td class="px-6 py-4 text-gray-600">{{ sale.status === 'completed' ? 'Completed' : 'Cancelled' }}</td>
                                    <td class="px-6 py-4 text-right"><Link :href="route('sales.show', sale.id)" :aria-label="`View receipt ${sale.sale_number}`" class="text-indigo-600 underline hover:text-indigo-900">View receipt</Link></td>
                                </tr>
                                <tr v-if="sales.data.length === 0"><td colspan="9" class="px-6 py-12 text-center text-gray-500">No sales found.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :pagination="sales" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
