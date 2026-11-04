<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    customer: { type: Object, required: true },
    hasSales: { type: Boolean, required: true },
    outstandingBalance: { type: [String, Number], required: true },
    sales: { type: Object, required: true },
    payments: { type: Object, required: true },
    timezone: { type: String, required: true },
    status: { type: String, default: null },
});

const confirmingDeletion = ref(false);
const deleteForm = useForm({});
const number = new Intl.NumberFormat('en-PH');
const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="customer.name" />
        <template #header>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-[28px]">Customer Details</h1>
        </template>

        <div class="flex flex-col gap-6 px-5 pb-8 pt-5 sm:px-7 lg:px-8">
            <Link :href="route('customers.index')" class="inline-flex items-center gap-2 self-start rounded text-sm font-medium text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <AppIcon name="arrow" class="h-4 w-4 rotate-180" />
                Customer directory
            </Link>

            <p v-if="status" role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ status }}</p>

            <section aria-labelledby="customer-profile-title" class="min-w-0 overflow-hidden rounded-[20px] border border-slate-200/80 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-5 border-b border-slate-100 px-5 py-5 sm:px-6 sm:py-6">
                    <div class="flex min-w-0 flex-1 items-center gap-4">
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-sky-100 text-blue-600">
                            <AppIcon name="customers" class="h-7 w-7" />
                        </span>
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <p class="text-xs font-medium text-slate-500">Customer profile</p>
                            <h2 id="customer-profile-title" class="break-words text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ customer.name }}</h2>
                        </div>
                    </div>
                    <Link :href="route('customers.edit', customer.id)" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 motion-reduce:transition-none">
                        <AppIcon name="settings" class="h-4 w-4" />
                        Edit customer
                    </Link>
                </div>

                <dl class="grid gap-5 px-5 py-6 sm:grid-cols-[minmax(0,.8fr)_minmax(0,1.2fr)] sm:px-6">
                    <div class="flex min-w-0 flex-col gap-2 rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                        <dt class="text-xs font-medium text-slate-500">Contact number</dt>
                        <dd class="break-words text-sm font-semibold text-slate-700">{{ customer.phone || 'Not provided' }}</dd>
                    </div>
                    <div class="flex min-w-0 flex-col gap-2 rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                        <dt class="text-xs font-medium text-slate-500">Address</dt>
                        <dd class="whitespace-pre-line break-words text-sm leading-relaxed text-slate-700">{{ customer.address || 'Not provided' }}</dd>
                    </div>
                </dl>

                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 bg-slate-50/60 px-5 py-4 sm:px-6">
                    <p class="flex min-w-0 flex-1 items-start gap-2 text-xs leading-relaxed text-slate-500">
                        <AppIcon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-blue-400" />
                        <span>{{ hasSales ? 'This customer has sales and must be kept to preserve financial history.' : 'This customer has no sales history and can be removed if no longer needed.' }}</span>
                    </p>
                    <DangerButton v-if="!hasSales" type="button" class="customer-action customer-delete-trigger gap-2" @click="confirmingDeletion = true">
                        <AppIcon name="trash" class="h-4 w-4" />
                        Delete customer
                    </DangerButton>
                </div>
            </section>

            <dl aria-label="Customer account overview" class="grid gap-4 md:grid-cols-3">
                <div class="flex min-w-0 flex-col gap-3 rounded-2xl border border-[#bfd3e7] bg-gradient-to-br from-[#e7f1ff] to-[#dfecfa] p-5 shadow-sm sm:p-6">
                    <dt class="text-sm font-medium text-slate-800 sm:text-base">Total Orders</dt>
                    <dd class="break-words text-4xl font-bold tracking-tight tabular-nums text-[#206588]">{{ number.format(sales.total) }}</dd>
                    <dd class="text-xs text-[#286481]">Completed sales only</dd>
                    <dd class="mt-auto pt-1">
                        <a href="#customer-sales-title" class="inline-flex items-center gap-2 rounded text-sm font-medium text-[#286481] hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">View order history <AppIcon name="arrow" class="h-4 w-4 shrink-0" /></a>
                    </dd>
                </div>
                <div class="flex min-w-0 flex-col gap-3 rounded-2xl border border-[#b9d8c9] bg-gradient-to-br from-[#e4f5eb] to-[#d9efe4] p-5 shadow-sm sm:p-6">
                    <dt class="text-sm font-medium text-slate-800 sm:text-base">Payment Records</dt>
                    <dd class="break-words text-4xl font-bold tracking-tight tabular-nums text-[#26734e]">{{ number.format(payments.total) }}</dd>
                    <dd class="text-xs text-[#426b58]">Includes reversed payments</dd>
                    <dd class="mt-auto pt-1">
                        <a href="#customer-payments-title" class="inline-flex items-center gap-2 rounded text-sm font-medium text-[#286481] hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">View payment history <AppIcon name="arrow" class="h-4 w-4 shrink-0" /></a>
                    </dd>
                </div>
                <div class="flex min-w-0 flex-col gap-3 rounded-2xl border border-[#e5c4bc] bg-gradient-to-br from-[#ffede7] to-[#f9e2dc] p-5 shadow-sm sm:p-6">
                    <dt class="text-sm font-medium text-slate-800 sm:text-base">Current Balance</dt>
                    <dd class="break-words text-3xl font-bold tracking-tight tabular-nums text-[#b55843] sm:text-4xl">{{ money.format(Number(outstandingBalance)) }}</dd>
                    <dd class="text-xs text-[#945b4c]">Outstanding across completed sales</dd>
                    <dd class="mt-auto pt-1">
                        <a href="#customer-sales-title" class="inline-flex items-center gap-2 rounded text-sm font-medium text-[#286481] hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Review sale balances <AppIcon name="arrow" class="h-4 w-4 shrink-0" /></a>
                    </dd>
                </div>
            </dl>

            <section class="min-w-0 overflow-hidden rounded-[20px] border border-slate-200/80 bg-white shadow-sm" aria-labelledby="customer-sales-title">
                <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-5 sm:px-6 sm:py-6">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-blue-600"><AppIcon name="sales" class="h-5 w-5" /></span>
                        <div class="flex flex-col gap-1">
                            <h2 id="customer-sales-title" tabindex="-1" class="scroll-mt-6 rounded text-lg font-bold text-slate-900 outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:text-xl">Completed Sales</h2>
                            <p class="text-xs text-slate-500">Order totals, payments, and remaining balances</p>
                        </div>
                    </div>
                    <span class="rounded-full border border-blue-100 bg-blue-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-blue-700">{{ number.format(sales.total) }} orders</span>
                </div>
                <div class="overflow-x-auto focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500" role="region" aria-labelledby="customer-sales-title" tabindex="0">
                    <table class="w-full min-w-[740px] text-left text-sm">
                        <caption class="sr-only">Completed sales for {{ customer.name }}, with payment totals and outstanding balances in Philippine pesos.</caption>
                        <thead class="border-y border-slate-200/70 bg-[#f0f5fa] text-slate-700">
                            <tr>
                                <th scope="col" class="px-5 py-4 font-semibold sm:pl-6">Sale Number</th>
                                <th scope="col" class="px-4 py-4 font-semibold">Date <span class="whitespace-nowrap text-xs font-normal text-slate-500">({{ timezone }})</span></th>
                                <th scope="col" class="px-4 py-4 text-right font-semibold">Total</th>
                                <th scope="col" class="px-4 py-4 text-right font-semibold">Paid</th>
                                <th scope="col" class="px-5 py-4 text-right font-semibold sm:pr-6">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="sale in sales.data" :key="sale.id" class="transition-colors hover:bg-blue-50/40 motion-reduce:transition-none">
                                <th scope="row" class="max-w-56 break-all px-5 py-5 text-left font-semibold sm:pl-6"><Link :href="route('sales.show', sale.id)" class="rounded text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500">{{ sale.sale_number }}</Link></th>
                                <td class="whitespace-nowrap px-4 py-5 text-slate-500">{{ sale.sold_at }}</td>
                                <td class="whitespace-nowrap px-4 py-5 text-right font-medium tabular-nums text-slate-700">{{ money.format(Number(sale.total_amount)) }}</td>
                                <td class="whitespace-nowrap px-4 py-5 text-right tabular-nums text-slate-600">{{ money.format(Number(sale.amount_paid)) }}</td>
                                <td class="whitespace-nowrap px-5 py-5 text-right font-semibold tabular-nums text-slate-800 sm:pr-6">{{ money.format(Number(sale.outstanding_balance)) }}</td>
                            </tr>
                            <tr v-if="sales.data.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <span class="rounded-2xl bg-blue-50 p-4 text-blue-400"><AppIcon name="sales" class="h-7 w-7" /></span>
                                        <p class="font-semibold text-slate-700">No completed sales.</p>
                                        <p class="text-xs text-slate-500">Completed orders for this customer will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="[&_nav]:flex-wrap"><Pagination :pagination="sales" /></div>
            </section>

            <section class="min-w-0 overflow-hidden rounded-[20px] border border-slate-200/80 bg-white shadow-sm" aria-labelledby="customer-payments-title">
                <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-5 sm:px-6 sm:py-6">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-600"><AppIcon name="card" class="h-5 w-5" /></span>
                        <div class="flex flex-col gap-1">
                            <h2 id="customer-payments-title" tabindex="-1" class="scroll-mt-6 rounded text-lg font-bold text-slate-900 outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:text-xl">Payment History</h2>
                            <p class="text-xs text-slate-500">Collections and reversals, including cancelled sales</p>
                        </div>
                    </div>
                    <span class="rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-emerald-700">{{ number.format(payments.total) }} records</span>
                </div>
                <div class="overflow-x-auto focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500" role="region" aria-labelledby="customer-payments-title" tabindex="0">
                    <table class="w-full min-w-[800px] text-left text-sm">
                        <caption class="sr-only">Payment history for {{ customer.name }} in Philippine pesos. Reversed payments are marked and their amounts are crossed out.</caption>
                        <thead class="border-y border-slate-200/70 bg-[#f0f5fa] text-slate-700">
                            <tr>
                                <th scope="col" class="px-5 py-4 font-semibold sm:pl-6">Date <span class="whitespace-nowrap text-xs font-normal text-slate-500">({{ timezone }})</span></th>
                                <th scope="col" class="px-4 py-4 font-semibold">Sale Number</th>
                                <th scope="col" class="px-4 py-4 font-semibold">Method</th>
                                <th scope="col" class="px-4 py-4 font-semibold">Recorded By</th>
                                <th scope="col" class="px-5 py-4 text-right font-semibold sm:pr-6">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="payment in payments.data" :key="payment.id" class="transition-colors hover:bg-blue-50/40 motion-reduce:transition-none">
                                <td class="max-w-sm px-5 py-5 text-slate-500 sm:pl-6">
                                    <span class="whitespace-nowrap">{{ payment.paid_at }}</span>
                                    <p v-if="payment.reversed_at" class="mt-2 break-words rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-xs leading-relaxed text-rose-700">Reversed {{ payment.reversed_at }}: {{ payment.reversal_reason }}</p>
                                </td>
                                <td class="max-w-56 px-4 py-5">
                                    <div class="flex flex-col items-start gap-2">
                                        <Link :href="route('sales.show', payment.sale_id)" class="break-all rounded font-semibold text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500">{{ payment.sale_number }}</Link>
                                        <span v-if="payment.sale_status === 'cancelled'" class="rounded-md border border-amber-100 bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-700">Cancelled sale</span>
                                    </div>
                                </td>
                                <td class="px-4 py-5"><span class="inline-flex rounded-lg border px-2.5 py-1 text-xs font-medium" :class="payment.payment_method === 'cash' ? 'border-emerald-100 bg-emerald-50 text-emerald-700' : 'border-blue-100 bg-blue-50 text-blue-700'">{{ payment.payment_method === 'cash' ? 'Cash' : 'GCash' }}</span></td>
                                <td class="max-w-48 break-words px-4 py-5 text-slate-600">{{ payment.recorded_by ?? 'Not recorded' }}</td>
                                <td class="whitespace-nowrap px-5 py-5 text-right font-semibold tabular-nums text-slate-800 sm:pr-6" :class="{ 'line-through': payment.reversed_at }">{{ money.format(Number(payment.amount)) }}</td>
                            </tr>
                            <tr v-if="payments.data.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <span class="rounded-2xl bg-emerald-50 p-4 text-emerald-400"><AppIcon name="card" class="h-7 w-7" /></span>
                                        <p class="font-semibold text-slate-700">No payments received.</p>
                                        <p class="text-xs text-slate-500">Payments recorded for this customer will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="[&_nav]:flex-wrap"><Pagination :pagination="payments" /></div>
            </section>
        </div>

        <Modal :show="confirmingDeletion" :closeable="!deleteForm.processing" max-width="lg" class="[&_.rounded-lg]:rounded-[20px]" aria-labelledby="delete-customer-title" aria-describedby="delete-customer-description" @close="confirmingDeletion = false">
            <form @submit.prevent="deleteForm.delete(route('customers.destroy', customer.id), { preserveScroll: true })" :aria-busy="deleteForm.processing" class="flex flex-col">
                <div class="flex flex-col gap-4 px-6 py-6 sm:px-7 sm:py-7">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl border border-rose-100 bg-rose-50 text-rose-600"><AppIcon name="trash" class="h-6 w-6" /></span>
                    <h2 id="delete-customer-title" class="text-xl font-bold tracking-tight text-slate-900">Delete customer?</h2>
                    <p id="delete-customer-description" class="break-words text-sm leading-relaxed text-slate-500"><span class="font-semibold text-slate-700">{{ customer.name }}</span> will be permanently deleted. Customers with sales cannot be deleted.</p>
                    <InputError :message="deleteForm.errors.customer" />
                </div>
                <div class="flex flex-wrap justify-end gap-3 border-t border-slate-100 bg-slate-50/60 px-6 py-5 sm:px-7">
                    <SecondaryButton class="customer-action" :disabled="deleteForm.processing" @click="confirmingDeletion = false">Cancel</SecondaryButton>
                    <DangerButton type="submit" class="customer-action" :disabled="deleteForm.processing" :class="{ 'opacity-50': deleteForm.processing }">{{ deleteForm.processing ? 'Deleting…' : 'Delete customer' }}</DangerButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.customer-action {
    justify-content: center;
    border-radius: 12px;
    padding: 10px 16px;
    font-size: 0.875rem;
    text-transform: none;
    letter-spacing: normal;
}

.customer-delete-trigger {
    border-color: #fecdd3;
    background-color: #fff1f2;
    color: #be123c;
}

.customer-delete-trigger:hover {
    background-color: #ffe4e6;
}
</style>
