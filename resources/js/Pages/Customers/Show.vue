<script setup>
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
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="customer.name" />
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="break-words text-xl font-semibold leading-tight text-gray-800">Customer details</h2>
                <Link :href="route('customers.index')" class="text-sm text-gray-600 underline hover:text-gray-900">Back to customers</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <p v-if="status" role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ status }}</p>
                <section class="flex flex-col gap-6 bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="break-words text-lg font-medium text-gray-900">{{ customer.name }}</h3>
                    <dl class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Phone</dt>
                            <dd class="mt-1 break-words text-gray-900">{{ customer.phone || 'Not provided' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Address</dt>
                            <dd class="mt-1 whitespace-pre-line break-words text-gray-900">{{ customer.address || 'Not provided' }}</dd>
                        </div>
                    </dl>
                    <div class="flex flex-wrap items-center gap-4">
                        <Link :href="route('customers.edit', customer.id)" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Edit customer</Link>
                        <DangerButton v-if="!hasSales" @click="confirmingDeletion = true">Delete customer</DangerButton>
                    </div>
                    <p v-if="hasSales" class="text-sm text-gray-600">This customer has sales and must be kept to preserve financial history.</p>
                </section>
                <section class="rounded-lg bg-white p-6 shadow-sm" aria-labelledby="customer-credit-title">
                    <h3 id="customer-credit-title" class="text-sm font-medium text-gray-500">Outstanding customer balance</h3>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-900">{{ Number(outstandingBalance).toFixed(2) }}</p>
                </section>
                <section class="overflow-hidden rounded-lg bg-white shadow-sm" aria-labelledby="customer-sales-title">
                    <h3 id="customer-sales-title" class="px-6 py-4 text-lg font-medium text-gray-900">Completed sales</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <thead class="bg-gray-50 text-gray-600"><tr><th scope="col" class="px-6 py-3 font-medium">Sale</th><th scope="col" class="px-6 py-3 font-medium">Date ({{ timezone }})</th><th scope="col" class="px-6 py-3 text-right font-medium">Total</th><th scope="col" class="px-6 py-3 text-right font-medium">Paid</th><th scope="col" class="px-6 py-3 text-right font-medium">Outstanding</th></tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="sale in sales.data" :key="sale.id"><td class="break-all px-6 py-4"><Link :href="route('sales.show', sale.id)" class="text-indigo-600 underline hover:text-indigo-900">{{ sale.sale_number }}</Link></td><td class="whitespace-nowrap px-6 py-4 text-gray-600">{{ sale.sold_at }}</td><td class="px-6 py-4 text-right tabular-nums text-gray-600">{{ Number(sale.total_amount).toFixed(2) }}</td><td class="px-6 py-4 text-right tabular-nums text-gray-600">{{ Number(sale.amount_paid).toFixed(2) }}</td><td class="px-6 py-4 text-right font-medium tabular-nums text-gray-900">{{ Number(sale.outstanding_balance).toFixed(2) }}</td></tr>
                                <tr v-if="sales.data.length === 0"><td colspan="5" class="px-6 py-8 text-center text-gray-500">No completed sales.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :pagination="sales" />
                </section>
                <section class="overflow-hidden rounded-lg bg-white shadow-sm" aria-labelledby="customer-payments-title">
                    <h3 id="customer-payments-title" class="px-6 py-4 text-lg font-medium text-gray-900">Payment history</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <thead class="bg-gray-50 text-gray-600"><tr><th scope="col" class="px-6 py-3 font-medium">Date ({{ timezone }})</th><th scope="col" class="px-6 py-3 font-medium">Sale</th><th scope="col" class="px-6 py-3 font-medium">Method</th><th scope="col" class="px-6 py-3 font-medium">Recorded by</th><th scope="col" class="px-6 py-3 text-right font-medium">Amount</th></tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="payment in payments.data" :key="payment.id"><td class="px-6 py-4 text-gray-600"><span class="whitespace-nowrap">{{ payment.paid_at }}</span><p v-if="payment.reversed_at" class="mt-1 text-xs text-red-700">Reversed {{ payment.reversed_at }}: {{ payment.reversal_reason }}</p></td><td class="break-all px-6 py-4"><Link :href="route('sales.show', payment.sale_id)" class="text-indigo-600 underline hover:text-indigo-900">{{ payment.sale_number }}</Link><span v-if="payment.sale_status === 'cancelled'" class="block text-xs text-gray-500">Cancelled sale</span></td><td class="px-6 py-4 text-gray-600">{{ payment.payment_method === 'cash' ? 'Cash' : 'GCash' }}</td><td class="break-words px-6 py-4 text-gray-600">{{ payment.recorded_by ?? 'Not recorded' }}</td><td class="px-6 py-4 text-right tabular-nums text-gray-900" :class="{ 'line-through': payment.reversed_at }">{{ Number(payment.amount).toFixed(2) }}</td></tr>
                                <tr v-if="payments.data.length === 0"><td colspan="5" class="px-6 py-8 text-center text-gray-500">No payments received.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :pagination="payments" />
                </section>
            </div>
        </div>

        <Modal :show="confirmingDeletion" :closeable="!deleteForm.processing" aria-labelledby="delete-customer-title" @close="confirmingDeletion = false">
            <form @submit.prevent="deleteForm.delete(route('customers.destroy', customer.id), { preserveScroll: true })" class="flex flex-col gap-4 p-6">
                <h2 id="delete-customer-title" class="text-lg font-medium text-gray-900">Delete customer?</h2>
                <p class="text-sm text-gray-600">{{ customer.name }} will be permanently deleted. Customers with sales cannot be deleted.</p>
                <InputError :message="deleteForm.errors.customer" />
                <div class="flex justify-end gap-3">
                    <SecondaryButton :disabled="deleteForm.processing" @click="confirmingDeletion = false">Cancel</SecondaryButton>
                    <DangerButton type="submit" :disabled="deleteForm.processing" :class="{ 'opacity-50': deleteForm.processing }">{{ deleteForm.processing ? 'Deleting…' : 'Delete customer' }}</DangerButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
