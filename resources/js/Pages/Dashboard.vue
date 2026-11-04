<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    date: String,
    asOf: String,
    timezone: String,
    sales: Object,
    collections: Object,
    credit: Object,
    expenses: [Number, String],
    gallons: Object,
    stock: Object,
    salesOverview: Array,
    topProducts: Array,
    recentSales: Array,
});

const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const number = new Intl.NumberFormat('en-PH');
const compactMoney = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', notation: 'compact', maximumFractionDigits: 1 });
const dateLabel = computed(() => new Intl.DateTimeFormat('en-PH', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${props.date}T00:00:00Z`)));
const overviewTotal = computed(() => props.salesOverview.reduce((total, day) => total + Number(day.revenue), 0));
const chartMaximum = computed(() => Math.max(100, Math.ceil(Math.max(...props.salesOverview.map(day => Number(day.revenue))) * 1.15 / 100) * 100));
const chartPoints = computed(() => props.salesOverview.map((day, index) => ({
    ...day,
    x: 12 + index * 96,
    y: 190 - Number(day.revenue) / chartMaximum.value * 180,
})));
const chartLine = computed(() => chartPoints.value.map((point, index, points) => index === 0
    ? `M ${point.x} ${point.y}`
    : `C ${points[index - 1].x + 48} ${points[index - 1].y}, ${point.x - 48} ${point.y}, ${point.x} ${point.y}`).join(' '));
const chartArea = computed(() => `${chartLine.value} L 588 190 L 12 190 Z`);
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Dashboard" />
        <template #header>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-[28px]">Dashboard</h1>
        </template>

        <div class="dashboard-content grid gap-5 px-5 pb-7 pt-3 sm:px-7 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-xs text-slate-500 sm:text-sm">
                    <AppIcon name="calendar" class="h-4 w-4 text-blue-500" />
                    {{ dateLabel }}
                    <span class="text-slate-300" aria-hidden="true">|</span>
                    <span class="text-xs">As of {{ asOf.slice(11, 16) }} ({{ timezone }})</span>
                </p>
                <Link :href="route('sales.create')" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm shadow-blue-200 transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:text-sm">
                    <AppIcon name="plus" class="h-4 w-4" />
                    New sale
                </Link>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="dashboard-card stat-card">
                    <dt class="text-sm font-medium text-slate-600">Today's sales</dt>
                    <dd class="stat-value text-blue-600">{{ money.format(Number(sales.revenue)) }}</dd>
                    <dd class="stat-caption">{{ number.format(sales.walk_in_count) }} walk-in · {{ number.format(sales.delivery_count) }} delivery</dd>
                    <svg class="stat-wave text-blue-400" viewBox="0 0 260 45" preserveAspectRatio="none" aria-hidden="true"><path d="M0 26C30 50 55 2 91 23S145 36 175 19 227 9 260 19V45H0Z" fill="currentColor" opacity=".12" /><path d="M0 26C30 50 55 2 91 23S145 36 175 19 227 9 260 19" fill="none" stroke="currentColor" stroke-width="1.7" opacity=".55" /></svg>
                </div>
                <div class="dashboard-card stat-card">
                    <dt class="text-sm font-medium text-slate-600">Completed sales today</dt>
                    <dd class="stat-value text-slate-900">{{ number.format(sales.sale_count) }}</dd>
                    <dd class="relative z-10 mt-4"><Link :href="route('sales.index', { date_from: date, date_to: date })" class="inline-flex items-center gap-2 text-xs font-medium text-blue-600 hover:text-blue-800">View all sales <AppIcon name="arrow" class="h-4 w-4" /></Link></dd>
                </div>
                <div class="dashboard-card stat-card">
                    <dt class="text-sm font-medium text-slate-600">Today's net collections</dt>
                    <dd class="stat-value text-emerald-600">{{ money.format(Number(collections.net)) }}</dd>
                    <dd class="stat-caption">Received {{ money.format(Number(collections.gross)) }}</dd>
                    <svg class="stat-wave text-emerald-400" viewBox="0 0 260 45" preserveAspectRatio="none" aria-hidden="true"><path d="M0 22C31 42 57 3 93 22S149 35 178 18 228 9 260 19V45H0Z" fill="currentColor" opacity=".12" /><path d="M0 22C31 42 57 3 93 22S149 35 178 18 228 9 260 19" fill="none" stroke="currentColor" stroke-width="1.7" opacity=".5" /></svg>
                </div>
                <div class="dashboard-card stat-card">
                    <dt class="text-sm font-medium text-slate-600">Outstanding credit</dt>
                    <dd class="stat-value text-orange-600">{{ money.format(Number(credit.outstanding)) }}</dd>
                    <dd class="stat-caption">{{ number.format(credit.sale_count) }} sales with an unpaid balance</dd>
                    <svg class="stat-wave text-orange-400" viewBox="0 0 260 45" preserveAspectRatio="none" aria-hidden="true"><path d="M0 25C34 8 56 13 90 24S143 40 179 21 230 10 260 20V45H0Z" fill="currentColor" opacity=".12" /><path d="M0 25C34 8 56 13 90 24S143 40 179 21 230 10 260 20" fill="none" stroke="currentColor" stroke-width="1.7" opacity=".5" /></svg>
                </div>
            </dl>

            <div class="grid gap-5 xl:grid-cols-[1.15fr_1fr]">
                <section aria-labelledby="sales-overview-title" class="dashboard-card min-w-0 p-5 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="sales-overview-title" class="text-base font-bold text-slate-800">Sales overview</h2>
                        <span class="period-label">Last 7 days</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500"><span class="font-semibold text-blue-600">{{ money.format(overviewTotal) }}</span> in completed sales</p>

                    <figure class="mt-6">
                        <div class="flex gap-2">
                            <div aria-hidden="true" class="flex w-12 shrink-0 flex-col justify-between py-1 text-right text-[10px] text-slate-400 sm:text-xs">
                                <span v-for="fraction in [1, .75, .5, .25, 0]" :key="fraction">{{ compactMoney.format(chartMaximum * fraction) }}</span>
                            </div>
                            <div class="relative h-[200px] min-w-0 flex-1">
                                <svg viewBox="0 0 600 200" preserveAspectRatio="none" class="h-full w-full overflow-visible" role="img" aria-label="Completed sales revenue for the last seven days">
                                    <defs><linearGradient id="sales-chart-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#1688ee" stop-opacity=".2" /><stop offset="100%" stop-color="#1688ee" stop-opacity=".01" /></linearGradient></defs>
                                    <path v-for="height in [10, 55, 100, 145, 190]" :key="height" :d="`M 0 ${height} H 600`" stroke="#edf2f7" stroke-width="1" />
                                    <path :d="chartArea" fill="url(#sales-chart-fill)" />
                                    <path :d="chartLine" fill="none" stroke="#1283e5" stroke-width="3" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                                    <circle v-for="(point, index) in chartPoints" :key="point.date" :cx="point.x" :cy="point.y" :r="index === chartPoints.length - 1 ? 4 : 2.5" fill="#1283e5" stroke="white" stroke-width="1.5"><title>{{ point.date }}: {{ money.format(Number(point.revenue)) }}</title></circle>
                                </svg>
                                <p v-if="overviewTotal === 0" class="absolute inset-x-0 top-16 text-center text-xs text-slate-400">No completed sales in this period.</p>
                            </div>
                        </div>
                        <div aria-hidden="true" class="ml-14 mt-3 grid grid-cols-7 text-center text-[11px] text-slate-500"><span v-for="day in salesOverview" :key="day.date">{{ day.label }}</span></div>
                        <figcaption class="sr-only"><span v-for="day in salesOverview" :key="day.date">{{ day.date }}: {{ money.format(Number(day.revenue)) }}. </span></figcaption>
                    </figure>
                </section>

                <section aria-labelledby="top-products-title" class="dashboard-card min-w-0 p-5 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="top-products-title" class="text-base font-bold text-slate-800">Top products</h2>
                        <span class="period-label">Last 7 days</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Units supplied on completed sales</p>
                    <ul v-if="topProducts.length" class="mt-4 divide-y divide-slate-100">
                        <li v-for="product in topProducts" :key="product.id" class="flex items-center gap-3 py-4 sm:gap-4">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-500"><AppIcon name="gallons" class="h-5 w-5" /></span>
                            <Link :href="route('products.show', product.id)" class="min-w-0 flex-1 break-words text-sm font-semibold text-slate-700 hover:text-blue-600">{{ product.name }}</Link>
                            <span class="shrink-0 text-sm tabular-nums text-slate-500"><span class="font-semibold text-slate-700">{{ number.format(product.units_sold) }}</span> units</span>
                        </li>
                    </ul>
                    <div v-else class="flex min-h-60 flex-col items-center justify-center gap-3 text-center">
                        <span class="rounded-full bg-sky-50 p-4 text-sky-400"><AppIcon name="gallons" class="h-8 w-8" /></span>
                        <p class="text-sm font-medium text-slate-600">Your top products will appear here</p>
                        <p class="max-w-60 text-xs leading-relaxed text-slate-400">Completed sales in the last 7 days determine this list.</p>
                    </div>
                </section>
            </div>

            <section aria-labelledby="recent-sales-title" class="dashboard-card min-w-0 overflow-hidden">
                <div class="flex items-center justify-between gap-4 px-5 pb-4 pt-5 sm:px-6">
                    <h2 id="recent-sales-title" class="text-base font-bold text-slate-800">Recent transactions</h2>
                    <Link :href="route('sales.index')" class="inline-flex shrink-0 items-center gap-1.5 text-xs font-medium text-blue-600 hover:text-blue-800">View all <AppIcon name="arrow" class="h-3.5 w-3.5" /></Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead class="border-y border-slate-100 bg-slate-50/60 text-xs font-medium text-slate-500"><tr><th scope="col" class="px-5 py-3 font-medium sm:pl-6">Sale no.</th><th scope="col" class="px-4 py-3 font-medium">Customer</th><th scope="col" class="px-4 py-3 font-medium">Type</th><th scope="col" class="px-4 py-3 text-right font-medium">Amount</th><th scope="col" class="px-4 py-3 font-medium">Payment status</th><th scope="col" class="whitespace-nowrap px-4 py-3 font-medium sm:pr-6">Sold at ({{ timezone }})</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="sale in recentSales" :key="sale.id" class="text-slate-600 transition-colors hover:bg-sky-50/40">
                                <td class="px-5 py-4 sm:pl-6"><Link :href="route('sales.show', sale.id)" class="block max-w-44 truncate font-medium text-blue-600 hover:underline" :title="sale.sale_number">{{ sale.sale_number }}</Link></td>
                                <td class="max-w-44 break-words px-4 py-4">{{ sale.customer_name ?? 'No customer selected' }}</td>
                                <td class="whitespace-nowrap px-4 py-4">{{ sale.sale_type === 'delivery' ? 'Delivery' : 'Walk-in' }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right font-medium tabular-nums text-slate-800">{{ money.format(Number(sale.total_amount)) }}</td>
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span v-if="sale.status === 'cancelled'" class="payment-badge bg-slate-100 text-slate-500">Cancelled</span>
                                    <span v-else-if="Number(sale.amount_paid) >= Number(sale.total_amount)" class="payment-badge bg-emerald-50 text-emerald-700">Paid</span>
                                    <span v-else-if="Number(sale.amount_paid) > 0" class="payment-badge bg-amber-50 text-amber-700">Partial</span>
                                    <span v-else class="payment-badge bg-orange-50 text-orange-700">Unpaid</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-xs text-slate-500 sm:pr-6">{{ sale.sold_at.slice(0, 16) }}</td>
                            </tr>
                            <tr v-if="!recentSales.length"><td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400">No sales recorded yet. Create a sale to get started.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
                <section aria-labelledby="dashboard-expenses" class="dashboard-card p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3"><h2 id="dashboard-expenses" class="text-sm font-semibold text-slate-800">Today's expenses</h2><AppIcon name="expenses" class="h-5 w-5 text-blue-400" /></div>
                    <p class="mt-3 text-2xl font-bold tabular-nums text-slate-900">{{ money.format(Number(expenses)) }}</p>
                    <p class="mt-2 text-xs leading-relaxed text-slate-500">Operating expenses with today's expense date.</p>
                    <Link :href="route('expenses.index', { date_from: date, date_to: date })" class="mt-4 inline-flex items-center gap-2 text-xs font-medium text-blue-600 hover:text-blue-800">View expenses <AppIcon name="arrow" class="h-3.5 w-3.5" /></Link>
                </section>
                <section aria-labelledby="dashboard-gallons" class="dashboard-card p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3"><h2 id="dashboard-gallons" class="text-sm font-semibold text-slate-800">Today's gallon movement</h2><AppIcon name="gallons" class="h-5 w-5 text-blue-400" /></div>
                    <dl class="mt-4 grid grid-cols-3 gap-3"><div><dt class="text-[11px] text-slate-500">Filled out</dt><dd class="mt-1 text-xl font-bold tabular-nums text-slate-800">{{ number.format(gallons.filled_out) }}</dd></div><div><dt class="text-[11px] text-slate-500">Empties in</dt><dd class="mt-1 text-xl font-bold tabular-nums text-slate-800">{{ number.format(gallons.empty_returned) }}</dd></div><div><dt class="text-[11px] text-slate-500">Net out</dt><dd class="mt-1 text-xl font-bold tabular-nums text-slate-800">{{ number.format(gallons.net_out) }}</dd></div></dl>
                    <Link :href="route('gallon-exchanges.index', { date_from: date, date_to: date })" class="mt-4 inline-flex items-center gap-2 text-xs font-medium text-blue-600 hover:text-blue-800">Exchange history <AppIcon name="arrow" class="h-3.5 w-3.5" /></Link>
                </section>
                <section aria-labelledby="dashboard-stock" class="dashboard-card p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3"><h2 id="dashboard-stock" class="text-sm font-semibold text-slate-800">Current stock</h2><AppIcon name="products" class="h-5 w-5 text-blue-400" /></div>
                    <dl class="mt-4 grid grid-cols-2 gap-3"><div><dt class="text-[11px] text-slate-500">Units on hand</dt><dd class="mt-1 text-xl font-bold tabular-nums text-slate-800">{{ number.format(stock.current_units) }}</dd></div><div><dt class="text-[11px] text-slate-500">Active out of stock</dt><dd class="mt-1 text-xl font-bold tabular-nums" :class="Number(stock.active_out_of_stock) > 0 ? 'text-orange-600' : 'text-slate-800'">{{ number.format(stock.active_out_of_stock) }}</dd></div></dl>
                    <p class="mt-3 text-[11px] text-slate-400">{{ number.format(stock.tracked_products) }} tracked · {{ number.format(stock.untracked_products) }} untracked products</p>
                    <Link :href="route('products.index')" class="mt-3 inline-flex items-center gap-2 text-xs font-medium text-blue-600 hover:text-blue-800">Manage products <AppIcon name="arrow" class="h-3.5 w-3.5" /></Link>
                </section>
            </div>

            <details class="rounded-xl border border-blue-100/80 bg-blue-50/50 px-4 py-3 text-xs text-slate-500">
                <summary class="cursor-pointer font-medium text-slate-600">How these figures are calculated</summary>
                <div class="mt-3 grid gap-3 leading-relaxed sm:grid-cols-2">
                    <p>Sales revenue includes full, partial, and unpaid completed sales. Collections follow payment dates, including payments for older sales, less valid reversals on their reversal dates. Today's reversals: {{ money.format(Number(collections.reversals)) }}. Net cash: {{ money.format(Number(collections.cash)) }}. Net GCash: {{ money.format(Number(collections.gcash)) }}.</p>
                    <p>Outstanding credit includes older sales and sales without a selected customer. Cancelled sales contribute no revenue, credit, or gallon totals; their records remain in history and collected payments remain until reversed. Gallon movement is separate from money. Stock is the current record for active and inactive products.</p>
                </div>
                <Link :href="route('reports.index', { date_from: date, date_to: date })" class="mt-3 inline-flex items-center gap-2 font-medium text-blue-600 hover:text-blue-800">View today's report <AppIcon name="arrow" class="h-3.5 w-3.5" /></Link>
            </details>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.dashboard-card {
    border: 1px solid #e9eff5;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 2px 5px #172b4d03, 0 0 0 1px #fff8;
}

.stat-card {
    position: relative;
    min-height: 158px;
    overflow: hidden;
    padding: 22px 20px;
}

.stat-value {
    position: relative;
    z-index: 1;
    margin-top: 13px;
    overflow-wrap: anywhere;
    font-size: clamp(1.5rem, 2.2vw, 1.9rem);
    font-weight: 750;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.045em;
    line-height: 1.2;
}

.stat-caption {
    position: relative;
    z-index: 1;
    margin-top: 11px;
    color: #8391a4;
    font-size: 10px;
}

.stat-wave {
    position: absolute;
    inset: auto 0 0;
    height: 33px;
    width: 100%;
}

.period-label {
    border: 1px solid #e8eef4;
    border-radius: 9px;
    padding: 6px 10px;
    color: #6b7a8e;
    font-size: 11px;
    line-height: 1;
}

.payment-badge {
    display: inline-block;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 11px;
    font-weight: 600;
}
</style>
