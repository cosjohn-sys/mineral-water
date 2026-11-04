<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Pagination from '@/Components/Pagination.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    customers: { type: Object, required: true },
    customerStats: { type: Object, required: true },
    filters: { type: Object, required: true },
    status: { type: String, default: null },
});

const filterForm = useForm({
    search: props.filters.search,
    per_page: props.filters.per_page,
});

const number = new Intl.NumberFormat('en-PH');
const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Customers" />
        <template #header>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-[28px]">Customers Management</h1>
        </template>

        <div class="flex flex-col gap-6 px-5 pb-8 pt-5 sm:px-7 lg:px-8">
            <p v-if="status" role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ status }}</p>

            <dl aria-label="Customer overview" class="grid gap-4 md:grid-cols-3">
                <div class="flex min-w-0 flex-col gap-3 rounded-2xl border border-[#bfd3e7] bg-gradient-to-br from-[#e7f1ff] to-[#dfecfa] p-5 shadow-sm sm:p-6">
                    <dt class="text-sm font-medium text-slate-800 sm:text-base">Total Customers</dt>
                    <dd class="break-words text-4xl font-bold tracking-tight text-[#206588] tabular-nums">{{ number.format(customerStats.total) }}</dd>
                    <dd class="mt-auto pt-2">
                        <a href="#customer-directory" class="inline-flex items-center gap-2 rounded text-sm font-medium text-[#286481] hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            View customer directory <AppIcon name="arrow" class="h-4 w-4 shrink-0" />
                        </a>
                    </dd>
                </div>
                <div class="flex min-w-0 flex-col gap-3 rounded-2xl border border-[#b9d8c9] bg-gradient-to-br from-[#e4f5eb] to-[#d9efe4] p-5 shadow-sm sm:p-6">
                    <dt class="text-sm font-medium text-slate-800 sm:text-base">Customers with Orders</dt>
                    <dd class="break-words text-4xl font-bold tracking-tight text-[#26734e] tabular-nums">{{ number.format(customerStats.with_orders) }}</dd>
                    <dd class="mt-auto pt-2">
                        <Link :href="route('sales.index')" class="inline-flex items-center gap-2 rounded text-sm font-medium text-[#286481] hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            View order history <AppIcon name="arrow" class="h-4 w-4 shrink-0" />
                        </Link>
                    </dd>
                </div>
                <div class="flex min-w-0 flex-col gap-3 rounded-2xl border border-[#e5c4bc] bg-gradient-to-br from-[#ffede7] to-[#f9e2dc] p-5 shadow-sm sm:p-6">
                    <dt class="text-sm font-medium text-slate-800 sm:text-base">New Registrations <span class="whitespace-nowrap text-xs font-normal text-slate-600">(This Month)</span></dt>
                    <dd class="break-words text-4xl font-bold tracking-tight text-[#b55843] tabular-nums">{{ number.format(customerStats.new_this_month) }}</dd>
                    <dd class="mt-auto pt-2">
                        <Link :href="route('customers.create')" class="inline-flex items-center gap-2 rounded text-sm font-medium text-[#286481] hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Register a customer <AppIcon name="arrow" class="h-4 w-4 shrink-0" />
                        </Link>
                    </dd>
                </div>
            </dl>

            <section aria-labelledby="customer-directory" class="min-w-0 overflow-hidden rounded-[20px] border border-slate-200/80 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-5 sm:px-6 sm:py-6">
                    <div class="flex items-center gap-3">
                        <h2 id="customer-directory" tabindex="-1" class="scroll-mt-6 rounded text-lg font-bold text-slate-900 outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:text-xl">Customer Directory</h2>
                        <span class="rounded-full border border-blue-100 bg-blue-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-blue-700">{{ number.format(customers.total) }}<span class="sr-only"> matching customers</span></span>
                    </div>
                    <Link :href="route('customers.create')" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 motion-reduce:transition-none">
                        <AppIcon name="plus" class="h-4 w-4" />
                        Add customer
                    </Link>
                </div>

                <form @submit.prevent="filterForm.get(route('customers.index'), { preserveState: true, replace: true })" class="flex flex-col gap-4 border-t border-slate-100 px-5 py-4 sm:px-6 xl:flex-row xl:items-start">
                    <div class="min-w-0 flex-1">
                        <InputLabel for="customer-search" value="Search customers" class="sr-only" />
                        <div class="relative">
                            <AppIcon name="search" class="pointer-events-none absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                            <input id="customer-search" v-model="filterForm.search" type="search" maxlength="255" placeholder="Search by name, phone, or address" :aria-invalid="Boolean(filterForm.errors.search)" aria-describedby="customer-search-error" class="block w-full rounded-xl border-slate-200 bg-slate-50/70 py-2.5 pl-10 pr-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-400 focus:bg-white focus:ring-blue-400" />
                        </div>
                        <InputError id="customer-search-error" :message="filterForm.errors.search" class="mt-2" />
                    </div>
                    <div class="flex flex-wrap items-start gap-3">
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-2">
                                <InputLabel for="customers-per-page" value="Rows" class="text-xs text-slate-500" />
                                <select id="customers-per-page" v-model="filterForm.per_page" :aria-invalid="Boolean(filterForm.errors.per_page)" aria-describedby="customers-per-page-error" class="rounded-xl border-slate-200 bg-white py-2.5 pl-3 pr-8 text-sm text-slate-600 focus:border-blue-400 focus:ring-blue-400">
                                    <option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
                                </select>
                            </div>
                            <InputError id="customers-per-page-error" :message="filterForm.errors.per_page" />
                        </div>
                        <button type="submit" :disabled="filterForm.processing" class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700 transition-colors hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-50 motion-reduce:transition-none">Search</button>
                        <Link :href="route('customers.index')" class="rounded-lg px-2 py-2.5 text-sm text-slate-500 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Clear</Link>
                    </div>
                </form>

                <div class="overflow-x-auto" role="region" aria-labelledby="customer-directory" tabindex="0">
                    <table class="w-full min-w-[860px] text-left text-sm">
                        <caption class="sr-only">Customers matching your search. Total orders counts completed sales only.</caption>
                        <thead class="border-y border-slate-200/70 bg-[#f0f5fa] text-slate-700">
                            <tr>
                                <th scope="col" class="px-5 py-4 font-semibold sm:pl-6">Customer Name</th>
                                <th scope="col" class="px-4 py-4 font-semibold">Contact Number</th>
                                <th scope="col" class="px-4 py-4 font-semibold">Address</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-4 text-center font-semibold">Total Orders</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-4 text-right font-semibold">Current Balance</th>
                                <th scope="col" class="px-5 py-4 text-right font-semibold sm:pr-6">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="customer in customers.data" :key="customer.id" class="transition-colors hover:bg-blue-50/40 motion-reduce:transition-none">
                                <th scope="row" class="max-w-56 break-words px-5 py-6 text-left font-semibold text-slate-800 sm:pl-6">
                                    <Link :href="route('customers.show', customer.id)" class="rounded hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">{{ customer.name }}</Link>
                                </th>
                                <td class="max-w-56 break-words px-4 py-6 text-slate-600">{{ customer.phone || '—' }}</td>
                                <td class="max-w-72 break-words px-4 py-6 leading-relaxed text-slate-600">{{ customer.address || '—' }}</td>
                                <td class="px-4 py-6 text-center font-medium tabular-nums text-slate-700">{{ number.format(customer.total_orders) }}</td>
                                <td class="whitespace-nowrap px-4 py-6 text-right font-semibold tabular-nums text-slate-800">{{ money.format(Number(customer.outstanding_balance)) }}</td>
                                <td class="px-5 py-6 sm:pr-6">
                                    <div class="flex justify-end gap-3">
                                        <Link :href="route('customers.show', customer.id)" :aria-label="`View ${customer.name}`" class="rounded text-xs font-semibold text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500">View</Link>
                                        <Link :href="route('customers.edit', customer.id)" :aria-label="`Edit ${customer.name}`" class="rounded text-xs font-medium text-slate-500 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Edit</Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="customers.data.length === 0">
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <span class="rounded-2xl bg-blue-50 p-4 text-blue-400"><AppIcon name="customers" class="h-7 w-7" /></span>
                                        <p class="font-semibold text-slate-700">No customers found.</p>
                                        <p class="text-xs text-slate-500">{{ filters.search ? 'Try a different name, phone number, or address.' : 'Add your first customer to start building your directory.' }}</p>
                                        <Link v-if="filters.search" :href="route('customers.index')" class="rounded text-sm font-medium text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500">Clear search</Link>
                                        <Link v-else :href="route('customers.create')" class="inline-flex items-center gap-1.5 rounded text-sm font-medium text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500"><AppIcon name="plus" class="h-4 w-4" /> Add customer</Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="[&_nav]:flex-wrap">
                    <Pagination :pagination="customers" />
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
