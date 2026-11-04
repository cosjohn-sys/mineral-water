<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    sale: { type: Object, required: true },
    items: { type: Array, required: true },
    payments: { type: Array, required: true },
    paymentSubmissionToken: { type: String, required: true },
    gallonExchange: { type: Object, default: null },
    timezone: { type: String, required: true },
    status: { type: String, default: null },
});

const paymentForm = useForm({
    submission_token: props.paymentSubmissionToken,
    amount: '',
    payment_method: 'cash',
});

const gallonForm = useForm({
    filled_gallons_out: props.gallonExchange?.filled_gallons_out ?? 0,
    empty_gallons_returned: props.gallonExchange?.empty_gallons_returned ?? 0,
});

const reversingPayment = ref(null);
const reversalForm = useForm({ reason: '' });

const printReceipt = () => window.print();
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Receipt ${sale.sale_number}`" />
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Sale receipt</h2>
        </template>

        <div class="py-12 print:py-0">
            <div class="mx-auto flex max-w-4xl flex-col gap-6 px-4 sm:px-6 lg:px-8 print:max-w-none print:gap-0 print:px-0">
                <p v-if="status" role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800 print:hidden">{{ status }}</p>
                <div class="flex flex-wrap items-center gap-4 print:hidden">
                    <SecondaryButton @click="printReceipt">Print receipt</SecondaryButton>
                    <Link :href="route('sales.create')" class="text-sm text-indigo-600 underline hover:text-indigo-900">New sale</Link>
                    <Link :href="route('sales.index')" class="text-sm text-gray-600 underline hover:text-gray-900">Back to sales</Link>
                </div>
                <article aria-label="Sale receipt" class="flex flex-col gap-6 bg-white p-6 shadow-sm sm:rounded-lg print:rounded-none print:p-0 print:shadow-none">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">Sales receipt</h1>
                        <p class="mt-2 break-all font-medium text-gray-700">{{ sale.sale_number }}</p>
                    </div>
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-sm text-gray-500">Date ({{ timezone }})</dt><dd class="mt-1 text-gray-900">{{ sale.sold_at }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Recorded by</dt><dd class="mt-1 break-words text-gray-900">{{ sale.recorded_by }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Sale type</dt><dd class="mt-1 text-gray-900">{{ sale.sale_type === 'delivery' ? 'Delivery' : 'Walk-in' }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Customer</dt><dd class="mt-1 break-words text-gray-900">{{ sale.customer_name ?? 'No customer selected' }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Status</dt><dd class="mt-1 text-gray-900">{{ sale.status === 'completed' ? 'Completed' : 'Cancelled' }}</dd></div>
                        <div v-if="sale.sale_type === 'delivery'" class="sm:col-span-2"><dt class="text-sm text-gray-500">Delivery address</dt><dd class="mt-1 whitespace-pre-wrap break-words text-gray-900">{{ sale.delivery_address }}</dd></div>
                    </dl>
                    <div class="overflow-x-auto print:overflow-visible">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <caption class="sr-only">Purchased items and their prices at the time of sale</caption>
                            <thead class="text-gray-600">
                                <tr>
                                    <th scope="col" class="py-3 pr-4 font-medium">Product</th>
                                    <th scope="col" class="px-2 py-3 text-right font-medium">Quantity</th>
                                    <th scope="col" class="px-2 py-3 text-right font-medium">Unit price</th>
                                    <th scope="col" class="py-3 pl-2 text-right font-medium">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="item in items" :key="item.id" class="break-inside-avoid">
                                    <td class="max-w-xs break-words py-4 pr-4 text-gray-900">{{ item.product_name }}</td>
                                    <td class="px-2 py-4 text-right tabular-nums text-gray-700">{{ item.quantity }}</td>
                                    <td class="px-2 py-4 text-right tabular-nums text-gray-700">{{ Number(item.unit_price).toFixed(2) }}</td>
                                    <td class="py-4 pl-2 text-right tabular-nums text-gray-900">{{ Number(item.subtotal).toFixed(2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-300"><th scope="row" colspan="3" class="py-4 pr-2 text-right text-base font-semibold text-gray-900">Total</th><td class="py-4 pl-2 text-right text-base font-semibold tabular-nums text-gray-900">{{ Number(sale.total_amount).toFixed(2) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                    <section v-if="gallonExchange" aria-labelledby="receipt-gallons-title" class="flex flex-col gap-4 border-t border-gray-200 pt-6">
                        <h2 id="receipt-gallons-title" class="text-lg font-medium text-gray-900">Reusable gallon movement</h2>
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div><dt class="text-sm text-gray-500">Filled gallons supplied</dt><dd class="mt-1 font-medium tabular-nums text-gray-900">{{ gallonExchange.filled_gallons_out }}</dd></div>
                            <div><dt class="text-sm text-gray-500">Empty gallons returned</dt><dd class="mt-1 font-medium tabular-nums text-gray-900">{{ gallonExchange.empty_gallons_returned }}</dd></div>
                        </dl>
                        <p class="text-sm text-gray-600">Gallon movement does not count as payment or a discount.</p>
                        <p v-if="sale.status === 'cancelled'" class="text-sm text-gray-600">This record is preserved and read-only. It is excluded from completed-sale gallon totals. Cancellation does not record a physical return.</p>
                    </section>
                    <dl class="grid gap-4 border-t border-gray-200 pt-6 sm:grid-cols-2">
                        <div><dt class="text-sm text-gray-500">Total paid</dt><dd class="mt-1 font-medium tabular-nums text-gray-900">{{ Number(sale.amount_paid).toFixed(2) }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Outstanding balance</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ Number(sale.outstanding_balance).toFixed(2) }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Net cash received</dt><dd class="mt-1 tabular-nums text-gray-900">{{ Number(sale.cash_received).toFixed(2) }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Net GCash received</dt><dd class="mt-1 tabular-nums text-gray-900">{{ Number(sale.gcash_received).toFixed(2) }}</dd></div>
                    </dl>
                    <p v-if="sale.status === 'completed'" class="text-sm font-medium text-gray-700">{{ Number(sale.outstanding_balance) <= 0 ? 'Fully paid' : Number(sale.amount_paid) > 0 ? 'Partially paid' : 'Unpaid' }}</p>
                    <section class="flex flex-col gap-4" aria-labelledby="payment-history-title">
                        <h2 id="payment-history-title" class="text-lg font-medium text-gray-900">Payment history</h2>
                        <p v-if="payments.length === 0" class="text-sm text-gray-500">No payments received.</p>
                        <div v-else class="overflow-x-auto print:overflow-visible">
                            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                                <thead class="text-gray-600"><tr><th scope="col" class="py-3 pr-4 font-medium">Date ({{ timezone }})</th><th scope="col" class="px-2 py-3 font-medium">Method</th><th scope="col" class="px-2 py-3 font-medium">Recorded by</th><th scope="col" class="py-3 pl-2 text-right font-medium">Amount</th></tr></thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="payment in payments" :key="payment.id" class="break-inside-avoid"><td class="py-3 pr-4 text-gray-600"><span class="whitespace-nowrap">{{ payment.paid_at }}</span><p v-if="payment.reversed_at" class="mt-1 text-xs text-red-700">Reversed {{ payment.reversed_at }}: {{ payment.reversal_reason }}</p><button v-else type="button" @click="reversingPayment = payment; reversalForm.reset(); reversalForm.clearErrors()" class="mt-1 block text-xs text-red-700 underline print:hidden">Record full reversal</button></td><td class="px-2 py-3 text-gray-600">{{ payment.payment_method === 'cash' ? 'Cash' : 'GCash' }}</td><td class="break-words px-2 py-3 text-gray-600">{{ payment.recorded_by ?? 'Not recorded' }}</td><td class="py-3 pl-2 text-right tabular-nums text-gray-900" :class="{ 'line-through': payment.reversed_at }">{{ Number(payment.amount).toFixed(2) }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </article>
                <section v-if="sale.status === 'completed'" class="flex flex-col gap-6 rounded-lg bg-white p-6 shadow-sm print:hidden" aria-labelledby="record-gallons-title">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <h2 id="record-gallons-title" class="text-lg font-medium text-gray-900">{{ gallonExchange ? 'Update gallon totals' : 'Record gallon totals' }}</h2>
                        <Link :href="route('gallon-exchanges.index')" class="text-sm text-indigo-600 underline hover:text-indigo-900">Exchange history</Link>
                    </div>
                    <p class="text-sm text-gray-600">Use the total quantities for this sale. Saving replaces the previous totals; it does not add to them or change payments.</p>
                    <form @submit.prevent="!gallonForm.processing && gallonForm.put(route('sales.gallon-exchange.update', sale.id), { preserveScroll: true })" class="flex flex-col gap-6">
                        <fieldset :disabled="gallonForm.processing" class="grid gap-6 sm:grid-cols-2">
                            <legend class="sr-only">Gallon quantities</legend>
                            <div>
                                <InputLabel for="receipt-filled-gallons" value="Filled gallons supplied" />
                                <TextInput id="receipt-filled-gallons" v-model="gallonForm.filled_gallons_out" type="number" min="0" max="4294967295" step="1" required class="mt-1 block w-full" :aria-invalid="Boolean(gallonForm.errors.filled_gallons_out)" aria-describedby="receipt-filled-gallons-error" />
                                <InputError id="receipt-filled-gallons-error" :message="gallonForm.errors.filled_gallons_out" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="receipt-empty-gallons" value="Empty gallons returned" />
                                <TextInput id="receipt-empty-gallons" v-model="gallonForm.empty_gallons_returned" type="number" min="0" max="4294967295" step="1" required class="mt-1 block w-full" :aria-invalid="Boolean(gallonForm.errors.empty_gallons_returned)" aria-describedby="receipt-empty-gallons-error" />
                                <InputError id="receipt-empty-gallons-error" :message="gallonForm.errors.empty_gallons_returned" class="mt-2" />
                            </div>
                        </fieldset>
                        <InputError :message="gallonForm.errors.gallon_exchange" />
                        <div><PrimaryButton :disabled="gallonForm.processing">{{ gallonForm.processing ? 'Saving...' : 'Save gallon totals' }}</PrimaryButton></div>
                    </form>
                </section>
                <section v-if="sale.status === 'completed' && Number(sale.outstanding_balance) > 0" id="record-payment" class="flex flex-col gap-6 rounded-lg bg-white p-6 shadow-sm print:hidden" aria-labelledby="record-payment-title">
                    <h2 id="record-payment-title" class="text-lg font-medium text-gray-900">Record payment</h2>
                    <form @submit.prevent="!paymentForm.processing && paymentForm.post(route('sales.payments.store', sale.id), { preserveScroll: true, onSuccess: (page) => { paymentForm.reset('amount'); paymentForm.submission_token = page.props.paymentSubmissionToken; } })" class="flex flex-col gap-6">
                        <fieldset :disabled="paymentForm.processing" class="grid gap-6 sm:grid-cols-2">
                            <legend class="sr-only">Payment details</legend>
                            <div>
                                <InputLabel for="payment-amount" value="Amount received" />
                                <TextInput id="payment-amount" v-model="paymentForm.amount" type="number" min="0.01" :max="Number(sale.outstanding_balance).toFixed(2)" step="0.01" required class="mt-1 block w-full" :aria-invalid="Boolean(paymentForm.errors.amount)" aria-describedby="payment-amount-error" />
                                <InputError id="payment-amount-error" :message="paymentForm.errors.amount" class="mt-2" />
                                <button type="button" @click="paymentForm.amount = Number(sale.outstanding_balance).toFixed(2)" class="mt-2 text-sm text-indigo-600 underline hover:text-indigo-900">Use remaining balance</button>
                            </div>
                            <div>
                                <InputLabel for="payment-method" value="Payment method" />
                                <select id="payment-method" v-model="paymentForm.payment_method" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" aria-describedby="payment-method-error"><option value="cash">Cash</option><option value="gcash">GCash</option></select>
                                <InputError id="payment-method-error" :message="paymentForm.errors.payment_method" class="mt-2" />
                            </div>
                        </fieldset>
                        <InputError :message="paymentForm.errors.submission_token" />
                        <div><PrimaryButton :disabled="paymentForm.processing">{{ paymentForm.processing ? 'Saving...' : 'Record payment' }}</PrimaryButton></div>
                    </form>
                </section>
            </div>
        </div>
        <Modal :show="Boolean(reversingPayment)" :closeable="!reversalForm.processing" @close="reversingPayment = null" aria-labelledby="reverse-payment-title">
            <form v-if="reversingPayment" @submit.prevent="!reversalForm.processing && reversalForm.post(route('payments.reversal.store', reversingPayment.id), { preserveScroll: true, onSuccess: () => { reversingPayment = null; reversalForm.reset(); } })" class="flex flex-col gap-4 p-6">
                <h2 id="reverse-payment-title" class="text-lg font-medium text-gray-900">Record full payment reversal?</h2>
                <p class="text-sm text-gray-600">Amount: {{ Number(reversingPayment.amount).toFixed(2) }}. Record this only after money was returned or an erroneous collection was voided. The original payment stays in history. A completed sale's outstanding balance will increase.</p>
                <div><InputLabel for="reversal-reason" value="Reason" /><textarea id="reversal-reason" v-model="reversalForm.reason" rows="3" maxlength="1000" required :disabled="reversalForm.processing" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" aria-describedby="reversal-reason-error" /><InputError id="reversal-reason-error" :message="reversalForm.errors.reason" class="mt-2" /></div>
                <div class="flex justify-end gap-3"><SecondaryButton :disabled="reversalForm.processing" @click="reversingPayment = null">Cancel</SecondaryButton><DangerButton type="submit" :disabled="reversalForm.processing">Record full reversal</DangerButton></div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
