<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    exchanges: { type: Object, required: true },
    summary: { type: Object, required: true },
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
        <Head title="Gallon exchanges" />
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Gallon exchanges</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <section aria-labelledby="gallon-summary-title" class="flex flex-col gap-4">
                    <h3 id="gallon-summary-title" class="text-lg font-medium text-gray-900">Completed-sale gallon summary</h3>
                    <p class="text-sm text-gray-600">Totals cover all completed sales within the selected sale dates, across every page.</p>
                    <dl class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-lg bg-white p-6 shadow-sm"><dt class="text-sm text-gray-500">Filled gallons supplied</dt><dd class="mt-2 text-3xl font-semibold tabular-nums text-gray-900">{{ summary.filled_gallons_out }}</dd></div>
                        <div class="rounded-lg bg-white p-6 shadow-sm"><dt class="text-sm text-gray-500">Empty gallons returned</dt><dd class="mt-2 text-3xl font-semibold tabular-nums text-gray-900">{{ summary.empty_gallons_returned }}</dd></div>
                        <div class="rounded-lg bg-white p-6 shadow-sm"><dt class="text-sm text-gray-500">Net gallons out</dt><dd class="mt-2 text-3xl font-semibold tabular-nums text-gray-900">{{ summary.net_gallons_out }}</dd><p class="mt-2 text-xs text-gray-500">Filled supplied minus empties returned. A negative number means more empties came back.</p></div>
                    </dl>
                </section>
                <div class="flex flex-col gap-2 rounded-md bg-blue-50 p-4 text-sm text-blue-900">
                    <p>Gallon movement does not change payments, prices, discounts, or customer monetary balances.</p>
                    <p>Cancelled sales keep their gallon records in history. Those records are read-only and excluded from the summary. Cancelling a sale does not record a physical gallon return.</p>
                </div>
                <section aria-labelledby="exchange-history-title" class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <h3 id="exchange-history-title" class="px-6 pt-6 text-lg font-medium text-gray-900">Exchange history</h3>
                    <form @submit.prevent="filterForm.get(route('gallon-exchanges.index'), { preserveState: true, replace: true })" class="flex flex-col gap-4 p-6 lg:flex-row lg:items-end">
                        <div class="flex-1">
                            <InputLabel for="exchange-date-from" :value="`Sale date from (${timezone})`" />
                            <TextInput id="exchange-date-from" v-model="filterForm.date_from" type="date" class="mt-1 block w-full" :aria-invalid="Boolean(filterForm.errors.date_from)" aria-describedby="exchange-date-from-error" />
                            <InputError id="exchange-date-from-error" :message="filterForm.errors.date_from" class="mt-2" />
                        </div>
                        <div class="flex-1">
                            <InputLabel for="exchange-date-to" :value="`Sale date to (${timezone})`" />
                            <TextInput id="exchange-date-to" v-model="filterForm.date_to" type="date" class="mt-1 block w-full" :aria-invalid="Boolean(filterForm.errors.date_to)" aria-describedby="exchange-date-to-error" />
                            <InputError id="exchange-date-to-error" :message="filterForm.errors.date_to" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="exchanges-per-page" value="Rows per page" />
                            <select id="exchanges-per-page" v-model="filterForm.per_page" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option></select>
                            <InputError :message="filterForm.errors.per_page" class="mt-2" />
                        </div>
                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="filterForm.processing">Filter</PrimaryButton>
                            <Link :href="route('gallon-exchanges.index')" class="text-sm text-gray-600 underline hover:text-gray-900">Clear</Link>
                        </div>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <caption class="sr-only">One gallon exchange record per applicable sale, including cancelled sales</caption>
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th scope="col" class="px-6 py-3 font-medium">Sale</th>
                                    <th scope="col" class="px-6 py-3 font-medium">Sale date ({{ timezone }})</th>
                                    <th scope="col" class="px-6 py-3 font-medium">Customer</th>
                                    <th scope="col" class="px-6 py-3 font-medium">Type</th>
                                    <th scope="col" class="px-6 py-3 text-right font-medium">Filled out</th>
                                    <th scope="col" class="px-6 py-3 text-right font-medium">Empties returned</th>
                                    <th scope="col" class="px-6 py-3 font-medium">Status</th>
                                    <th scope="col" class="px-6 py-3 font-medium">Last saved ({{ timezone }})</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="exchange in exchanges.data" :key="exchange.id">
                                    <td class="break-all px-6 py-4"><Link :href="route('sales.show', exchange.sale_id)" class="text-indigo-600 underline hover:text-indigo-900">{{ exchange.sale_number }}</Link></td>
                                    <td class="whitespace-nowrap px-6 py-4 text-gray-600">{{ exchange.sold_at }}</td>
                                    <td class="max-w-xs break-words px-6 py-4 text-gray-600">{{ exchange.customer_name ?? 'No customer selected' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-gray-600">{{ exchange.sale_type === 'delivery' ? 'Delivery' : 'Walk-in' }}</td>
                                    <td class="px-6 py-4 text-right tabular-nums text-gray-900">{{ exchange.filled_gallons_out }}</td>
                                    <td class="px-6 py-4 text-right tabular-nums text-gray-900">{{ exchange.empty_gallons_returned }}</td>
                                    <td class="px-6 py-4 text-gray-600">{{ exchange.status === 'completed' ? 'Completed' : 'Cancelled' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-gray-600">{{ exchange.updated_at ?? 'Not recorded' }}</td>
                                </tr>
                                <tr v-if="exchanges.data.length === 0"><td colspan="8" class="px-6 py-12 text-center text-gray-500">No gallon exchanges found. Record gallon totals when creating a sale or from its receipt.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :pagination="exchanges" />
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
