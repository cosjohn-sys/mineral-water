<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    summary: { type: Object, required: true }, periods: { type: Object, required: true },
    outstandingCredit: { type: [Number, String], required: true }, creditSales: { type: Object, required: true },
    stock: { type: Object, required: true }, stockSummary: { type: Object, required: true },
    filters: { type: Object, required: true }, timezone: { type: String, required: true },
});
const filterForm = useForm({ ...props.filters });
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Reports" />
        <template #header><div class="flex flex-wrap items-center justify-between gap-4"><h2 class="text-xl font-semibold text-gray-800">Reports</h2><Link :href="route('expenses.index')" class="text-sm text-indigo-600 underline">Manage expenses</Link></div></template>
        <div class="py-12"><div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
            <form @submit.prevent="filterForm.get(route('reports.index'), { preserveState: true, replace: true })" class="grid gap-4 rounded-lg bg-white p-6 shadow-sm md:grid-cols-2 lg:grid-cols-5">
                <div><InputLabel for="report-from" :value="`From date (${timezone})`" /><TextInput id="report-from" v-model="filterForm.date_from" type="date" required class="mt-1 block w-full" /><InputError :message="filterForm.errors.date_from" class="mt-2" /></div>
                <div><InputLabel for="report-to" :value="`To date (${timezone})`" /><TextInput id="report-to" v-model="filterForm.date_to" type="date" required class="mt-1 block w-full" /><InputError :message="filterForm.errors.date_to" class="mt-2" /></div>
                <div><InputLabel for="report-group" value="Group activity" /><select id="report-group" v-model="filterForm.group_by" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="daily">Daily</option><option value="monthly">Monthly</option></select><InputError :message="filterForm.errors.group_by" class="mt-2" /></div>
                <div><InputLabel for="report-per-page" value="Rows per page" /><select id="report-per-page" v-model="filterForm.per_page" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option></select><InputError :message="filterForm.errors.per_page" class="mt-2" /></div>
                <div class="flex items-end"><PrimaryButton :disabled="filterForm.processing">Apply filters</PrimaryButton></div>
            </form>
            <div class="flex flex-col gap-2 rounded-md bg-blue-50 p-4 text-sm text-blue-900">
                <p>Sales revenue follows the sale date and excludes cancelled sales. Collections follow the payment date and can settle older sales. Full reversals reduce collections on the reversal date.</p>
                <p>A cancelled sale does not automatically refund its payments. Those collections remain until a linked reversal is recorded. Monthly rows include only the dates selected.</p>
            </div>
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="card in [{ label: 'Sales revenue', value: summary.sales_revenue }, { label: 'Gross collections', value: summary.gross_collections }, { label: 'Payment reversals', value: summary.reversals }, { label: 'Net collections', value: summary.net_collections }, { label: 'Net cash collections', value: summary.cash_collections }, { label: 'Net GCash collections', value: summary.gcash_collections }, { label: 'Operating expenses', value: summary.expenses }, { label: 'Outstanding credit through end date', value: outstandingCredit }]" :key="card.label" class="rounded-lg bg-white p-6 shadow-sm"><dt class="text-sm text-gray-500">{{ card.label }}</dt><dd class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ Number(card.value).toFixed(2) }}</dd></div>
            </dl>
            <section aria-labelledby="activity-title" class="overflow-hidden rounded-lg bg-white shadow-sm">
                <h3 id="activity-title" class="px-6 pt-6 text-lg font-medium text-gray-900">{{ filters.group_by === 'monthly' ? 'Monthly' : 'Daily' }} activity</h3>
                <p class="px-6 py-3 text-sm text-gray-500">Periods with activity. Totals above cover every matching period, across all pages.</p>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-right text-sm">
                    <thead class="bg-gray-50 text-gray-600"><tr><th scope="col" class="px-4 py-3 text-left">Period</th><th scope="col" class="px-4 py-3">Sales</th><th scope="col" class="px-4 py-3">Sales revenue</th><th scope="col" class="px-4 py-3">Gross collections</th><th scope="col" class="px-4 py-3">Reversals</th><th scope="col" class="px-4 py-3">Net collections</th><th scope="col" class="px-4 py-3">Expenses</th><th scope="col" class="px-4 py-3">Filled out</th><th scope="col" class="px-4 py-3">Empties returned</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 text-gray-900"><tr v-for="period in periods.data" :key="period.period"><td class="whitespace-nowrap px-4 py-4 text-left">{{ period.period }}</td><td class="px-4 py-4 tabular-nums">{{ period.sale_count }}</td><td class="px-4 py-4 tabular-nums">{{ Number(period.sales_revenue).toFixed(2) }}</td><td class="px-4 py-4 tabular-nums">{{ Number(period.gross_collections).toFixed(2) }}</td><td class="px-4 py-4 tabular-nums">{{ Number(period.reversals).toFixed(2) }}</td><td class="px-4 py-4 font-medium tabular-nums">{{ Number(period.net_collections).toFixed(2) }}</td><td class="px-4 py-4 tabular-nums">{{ Number(period.expenses).toFixed(2) }}</td><td class="px-4 py-4 tabular-nums">{{ period.filled_gallons_out }}</td><td class="px-4 py-4 tabular-nums">{{ period.empty_gallons_returned }}</td></tr><tr v-if="periods.data.length === 0"><td colspan="9" class="px-6 py-10 text-center text-gray-500">No activity in the selected dates.</td></tr></tbody>
                </table></div><Pagination :pagination="periods" />
            </section>
            <section aria-labelledby="report-gallons-title" class="rounded-lg bg-white p-6 shadow-sm">
                <h3 id="report-gallons-title" class="text-lg font-medium text-gray-900">Gallon movement on completed sales</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-3"><div><dt class="text-sm text-gray-500">Filled supplied</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ summary.filled_gallons_out }}</dd></div><div><dt class="text-sm text-gray-500">Empties returned</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ summary.empty_gallons_returned }}</dd></div><div><dt class="text-sm text-gray-500">Net gallons out</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ summary.net_gallons_out }}</dd></div></dl>
                <p class="mt-4 text-sm text-gray-500">Container counts are separate from money. Cancelled-sale records remain in exchange history and are excluded here. Payment reversals do not imply a physical gallon return.</p>
            </section>
            <section aria-labelledby="report-credit-title" class="overflow-hidden rounded-lg bg-white shadow-sm">
                <h3 id="report-credit-title" class="px-6 pt-6 text-lg font-medium text-gray-900">Outstanding credit through {{ filters.date_to }}</h3>
                <p class="px-6 py-3 text-sm text-gray-500">Includes older completed sales, regardless of the start date. Payments and valid reversals are counted through the end date. Sale cancellation status is the current status.</p>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-left text-sm"><thead class="bg-gray-50 text-gray-600"><tr><th scope="col" class="px-6 py-3">Sale</th><th scope="col" class="px-6 py-3">Sold at ({{ timezone }})</th><th scope="col" class="px-6 py-3">Customer</th><th scope="col" class="px-6 py-3 text-right">Outstanding</th></tr></thead><tbody class="divide-y divide-gray-200"><tr v-for="sale in creditSales.data" :key="sale.id"><td class="break-all px-6 py-4"><Link :href="route('sales.show', sale.id)" class="text-indigo-600 underline">{{ sale.sale_number }}</Link></td><td class="whitespace-nowrap px-6 py-4 text-gray-600">{{ sale.sold_at }}</td><td class="break-words px-6 py-4 text-gray-600">{{ sale.customer_name ?? 'No customer selected' }}</td><td class="px-6 py-4 text-right font-medium tabular-nums">{{ Number(sale.outstanding_balance).toFixed(2) }}</td></tr><tr v-if="creditSales.data.length === 0"><td colspan="4" class="px-6 py-10 text-center text-gray-500">No outstanding credit through this date.</td></tr></tbody></table></div><Pagination :pagination="creditSales" />
            </section>
            <section aria-labelledby="report-stock-title" class="overflow-hidden rounded-lg bg-white shadow-sm">
                <h3 id="report-stock-title" class="px-6 pt-6 text-lg font-medium text-gray-900">Current stock and period units sold</h3>
                <p class="px-6 py-3 text-sm text-gray-500">Current tracked units: {{ stockSummary.current_units }}. Tracked products: {{ stockSummary.tracked_products }}. Untracked products: {{ stockSummary.untracked_products }}. Stock is current, not a historical snapshot. Units sold follow completed-sale dates in the selected range.</p>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-left text-sm"><thead class="bg-gray-50 text-gray-600"><tr><th scope="col" class="px-6 py-3">Product</th><th scope="col" class="px-6 py-3">Status</th><th scope="col" class="px-6 py-3 text-right">Current stock</th><th scope="col" class="px-6 py-3 text-right">Period units sold</th></tr></thead><tbody class="divide-y divide-gray-200"><tr v-for="product in stock.data" :key="product.id"><td class="break-words px-6 py-4"><Link :href="route('products.show', product.id)" class="text-indigo-600 underline">{{ product.name }}</Link></td><td class="px-6 py-4 text-gray-600">{{ product.is_active ? 'Active' : 'Inactive' }}</td><td class="px-6 py-4 text-right tabular-nums">{{ product.stock_quantity ?? 'Not tracked' }}</td><td class="px-6 py-4 text-right tabular-nums">{{ product.units_sold }}</td></tr><tr v-if="stock.data.length === 0"><td colspan="4" class="px-6 py-10 text-center text-gray-500">No products found.</td></tr></tbody></table></div><Pagination :pagination="stock" />
            </section>
        </div></div>
    </AuthenticatedLayout>
</template>
