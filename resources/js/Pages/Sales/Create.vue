<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import ProductWaterIcon from '@/Components/ProductWaterIcon.vue';
import SalesNavigation from '@/Components/SalesNavigation.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    customers: { type: Array, required: true },
    products: { type: Array, required: true },
    submissionToken: { type: String, required: true },
    todaySummary: { type: Object, required: true },
    timezone: { type: String, required: true },
});

const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const number = new Intl.NumberFormat('en-PH');
const search = ref('');
const checkoutOpen = ref(false);
const form = useForm({
    submission_token: props.submissionToken,
    sale_type: 'walk_in',
    customer_id: '',
    delivery_address: '',
    items: [],
    initial_payment_type: 'full',
    initial_payment_amount: '',
    payment_method: 'cash',
    track_gallon_exchange: false,
    filled_gallons_out: 0,
    empty_gallons_returned: 0,
});

const selectedCustomer = computed(() => props.customers.find(customer => String(customer.id) === String(form.customer_id)));
const catalogProducts = computed(() => props.products
    .filter(product => product.name.toLowerCase().includes(search.value.trim().toLowerCase()))
    .map(product => {
        const cartItem = form.items.find(item => String(item.product_id) === String(product.id));
        const inCart = Number(cartItem?.quantity ?? 0);
        const quantityLimit = Math.min(4294967295, product.stock_quantity === null ? 4294967295 : Number(product.stock_quantity));

        return {
            ...product,
            inCart,
            quantityLimit,
            canAdd: Number.isInteger(inCart) && inCart >= 0 && inCart < quantityLimit && (cartItem !== undefined || form.items.length < 100),
        };
    }));
const itemPreviews = computed(() => form.items.map(item => {
    const product = props.products.find(product => String(product.id) === String(item.product_id));
    const unitPriceCents = Math.round(Number(product?.price ?? 0) * 100);
    const quantity = Number(item.quantity);

    return {
        product,
        unitPriceCents,
        quantityLimit: Math.min(4294967295, product?.stock_quantity == null ? 4294967295 : Number(product.stock_quantity)),
        subtotalCents: unitPriceCents * (Number.isInteger(quantity) && quantity > 0 ? quantity : 0),
    };
}));
const totalCents = computed(() => itemPreviews.value.reduce((total, item) => total + item.subtotalCents, 0));
const totalUnits = computed(() => form.items.reduce((total, item) => total + (Number.isInteger(Number(item.quantity)) && Number(item.quantity) > 0 ? Number(item.quantity) : 0), 0));
const cartValid = computed(() => form.items.length > 0 && Number.isSafeInteger(totalCents.value) && totalCents.value <= 9999999999 && form.items.every((item, index) =>
    itemPreviews.value[index].product && Number.isInteger(Number(item.quantity)) && Number(item.quantity) > 0 && Number(item.quantity) <= itemPreviews.value[index].quantityLimit));
const initialPaymentCents = computed(() => form.initial_payment_type === 'full' ? totalCents.value : form.initial_payment_type === 'partial' ? Math.round(Number(form.initial_payment_amount || 0) * 100) : 0);

const addToCart = (product) => {
    if (form.processing || !product.canAdd) {
        return;
    }

    const existingItem = form.items.find(item => String(item.product_id) === String(product.id));
    if (existingItem) {
        existingItem.quantity = Number(existingItem.quantity) + 1;
    } else {
        form.items.push({ product_id: product.id, quantity: 1 });
    }
    form.clearErrors();
};

const openCheckout = (event) => {
    if (form.processing || !cartValid.value) {
        return;
    }
    if (event.submitter?.value === 'gcash') {
        form.payment_method = 'gcash';
        if (form.initial_payment_type === 'none') {
            form.initial_payment_type = 'full';
        }
    }
    checkoutOpen.value = true;
};

const submitSale = () => {
    if (form.processing || !cartValid.value) {
        return;
    }
    form.post(route('sales.store'), {
        onError: (errors) => {
            if (Object.keys(errors).some(key => key === 'items' || key.startsWith('items.'))) {
                checkoutOpen.value = false;
            }
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Sales POS Terminal" />
        <template #header><h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-[28px]">Sales POS Terminal</h1></template>

        <div class="grid gap-5 px-5 pb-7 pt-3 sm:px-7 lg:px-8">
            <SalesNavigation />

            <form @submit.prevent="openCheckout" class="pos-grid grid items-start gap-5">
                <fieldset :disabled="form.processing" class="pos-panel min-w-0 p-4 sm:p-5">
                    <legend class="sr-only">Product catalog</legend>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><h2 class="text-lg font-bold text-slate-800">Product catalog</h2><p class="mt-1 text-xs text-slate-500">Select a product to add it to your cart.</p></div>
                        <a href="#cart-summary" class="inline-flex items-center gap-2 rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-600 xl:hidden"><AppIcon name="sales" class="h-4 w-4" />Cart ({{ number.format(totalUnits) }})</a>
                    </div>

                    <div class="relative mt-5">
                        <label for="product-search" class="sr-only">Search products by name</label>
                        <AppIcon name="search" class="pointer-events-none absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                        <input id="product-search" v-model="search" type="search" placeholder="Search products..." class="pos-input w-full pl-10 pr-4" @keydown.enter.prevent />
                    </div>

                    <div v-if="catalogProducts.length" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        <article v-for="product in catalogProducts" :key="product.id" class="product-card flex min-w-0 flex-col rounded-2xl border bg-white p-3.5 sm:p-4" :class="product.inCart > 0 ? 'border-blue-200 ring-1 ring-blue-100' : 'border-slate-200/80'">
                            <div class="flex items-start gap-3">
                                <span class="flex h-16 w-14 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600"><ProductWaterIcon :bottle="/\d+\s*ml|bottl/i.test(product.name)" class="h-14 w-11" /></span>
                                <div class="min-w-0 flex-1"><h3 class="break-words text-sm font-bold leading-snug text-slate-800">{{ product.name }}</h3><span v-if="product.inCart > 0" class="mt-1.5 inline-flex rounded-md bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-600">{{ number.format(product.inCart) }} in cart</span></div>
                            </div>
                            <p class="mt-3 break-words text-xl font-bold tracking-tight tabular-nums text-slate-900">{{ money.format(Number(product.price)) }}</p>
                            <p class="mb-3 mt-1 text-[11px]" :class="product.stock_quantity === 0 ? 'text-orange-600' : 'text-slate-400'">{{ product.stock_quantity === null ? 'Stock not tracked' : product.stock_quantity === 0 ? 'Out of stock' : `${number.format(product.stock_quantity)} available` }}</p>
                            <button type="button" :disabled="!product.canAdd || form.processing" :aria-label="`Add ${product.name} to cart`" class="pos-primary mt-auto flex w-full items-center justify-center gap-1.5 px-2 py-2.5 text-xs sm:text-sm" @click="addToCart(product)"><AppIcon name="plus" class="h-4 w-4 shrink-0" />{{ product.stock_quantity === 0 ? 'Out of stock' : product.inCart >= product.quantityLimit ? 'Stock limit reached' : 'Add to cart' }}</button>
                        </article>
                    </div>
                    <div v-else class="flex min-h-80 flex-col items-center justify-center gap-3 text-center">
                        <span class="rounded-2xl bg-blue-50 p-5 text-blue-400"><AppIcon :name="products.length ? 'search' : 'products'" class="h-9 w-9" /></span>
                        <h3 class="text-sm font-semibold text-slate-700">{{ products.length ? 'No matching products' : 'No active products yet' }}</h3>
                        <p class="max-w-64 text-xs leading-relaxed text-slate-400">{{ products.length ? 'Try another product name or clear your search.' : 'Add or activate a product to start recording sales.' }}</p>
                        <button v-if="products.length" type="button" class="text-sm font-medium text-blue-600 hover:underline" @click="search = ''">Clear search</button>
                        <Link v-else :href="route('products.create')" class="text-sm font-medium text-blue-600 hover:underline">Add a product</Link>
                    </div>
                    <p class="mt-4 text-xs text-slate-400">{{ number.format(catalogProducts.length) }} of {{ number.format(products.length) }} active products</p>
                </fieldset>

                <div class="grid min-w-0 gap-4 xl:sticky xl:top-5">
                    <fieldset id="cart-summary" :disabled="form.processing" tabindex="-1" class="pos-panel min-w-0 overflow-hidden outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                        <legend class="sr-only">Order cart summary</legend>
                        <div class="border-b border-slate-100 px-5 py-5"><h2 class="text-lg font-bold text-slate-800">Order cart summary</h2></div>
                        <div class="flex items-center justify-between gap-3 bg-slate-50/80 px-5 py-2.5"><p class="text-xs font-semibold text-slate-500">Current cart <span class="ml-1 text-slate-400">({{ form.items.length }})</span></p><button type="button" :disabled="!form.items.length || form.processing" class="text-xs text-slate-400 hover:text-red-500 disabled:hidden" @click="form.items = []; form.clearErrors()">Clear cart</button></div>

                        <ul v-if="form.items.length" class="cart-items divide-y divide-slate-100 overflow-y-auto px-5">
                            <li v-for="(item, index) in form.items" :key="item.product_id" class="py-4">
                                <div class="flex items-start gap-2.5">
                                    <span class="flex h-11 w-9 shrink-0 items-center justify-center text-blue-600"><ProductWaterIcon :bottle="/\d+\s*ml|bottl/i.test(itemPreviews[index].product?.name ?? '')" class="h-11 w-9" /></span>
                                    <div class="min-w-0 flex-1"><p class="break-words text-sm font-semibold leading-snug text-slate-700">{{ itemPreviews[index].product?.name ?? 'Product unavailable' }}</p><p class="mt-1 text-[11px] text-slate-400">{{ money.format(itemPreviews[index].unitPriceCents / 100) }} each</p></div>
                                    <button type="button" :aria-label="`Remove ${itemPreviews[index].product?.name ?? 'product'} from cart`" class="rounded-md p-1 text-red-400 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-300" @click="form.items.splice(index, 1); form.clearErrors()"><AppIcon name="trash" class="h-4 w-4" /></button>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-2 pl-11">
                                    <div class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50">
                                        <button type="button" :aria-label="`Decrease ${itemPreviews[index].product?.name ?? 'product'} quantity`" :disabled="!Number.isInteger(Number(item.quantity)) || Number(item.quantity) <= 1" class="quantity-button rounded-l-lg" @click="item.quantity = Number(item.quantity) - 1; form.clearErrors()"><AppIcon name="minus" class="h-3 w-3" /></button>
                                        <label :for="`quantity-${item.product_id}`" class="sr-only">{{ itemPreviews[index].product?.name ?? 'Product' }} quantity</label>
                                        <input :id="`quantity-${item.product_id}`" v-model.number="item.quantity" type="number" min="1" :max="itemPreviews[index].quantityLimit" step="1" required class="quantity-input" :aria-invalid="Boolean(form.errors[`items.${index}.quantity`])" :aria-describedby="`cart-item-${index}-error`" @input="form.clearErrors()" />
                                        <button type="button" :aria-label="`Increase ${itemPreviews[index].product?.name ?? 'product'} quantity`" :disabled="!Number.isInteger(Number(item.quantity)) || Number(item.quantity) >= itemPreviews[index].quantityLimit" class="quantity-button rounded-r-lg" @click="item.quantity = Number(item.quantity) + 1; form.clearErrors()"><AppIcon name="plus" class="h-3 w-3" /></button>
                                    </div>
                                    <p class="break-words text-right text-sm font-bold tabular-nums text-slate-800">{{ money.format(itemPreviews[index].subtotalCents / 100) }}</p>
                                </div>
                                <InputError :id="`cart-item-${index}-error`" :message="form.errors[`items.${index}.product_id`] || form.errors[`items.${index}.quantity`] || form.errors[`items.${index}`]" class="mt-2" />
                            </li>
                        </ul>
                        <div v-else class="flex min-h-56 flex-col items-center justify-center gap-3 px-5 py-8 text-center"><span class="rounded-full bg-slate-50 p-4 text-blue-300"><AppIcon name="sales" class="h-9 w-9" /></span><p class="text-sm font-semibold text-slate-600">Your cart is empty</p><p class="max-w-52 text-xs leading-relaxed text-slate-400">Choose products from the catalog to start a sale.</p></div>

                        <div class="border-t border-slate-100 px-5 py-4" aria-live="polite" aria-atomic="true">
                            <dl class="grid gap-3 text-sm"><div class="flex justify-between gap-3 text-slate-500"><dt>Items</dt><dd class="tabular-nums">{{ number.format(totalUnits) }}</dd></div><div class="flex justify-between gap-3 text-slate-500"><dt>Subtotal</dt><dd class="tabular-nums">{{ money.format(totalCents / 100) }}</dd></div><div class="flex justify-between gap-3 border-t border-slate-100 pt-3 text-lg font-bold text-slate-900"><dt>Estimated total</dt><dd class="break-words text-right tabular-nums">{{ money.format(totalCents / 100) }}</dd></div></dl>
                            <p class="mt-3 text-[11px] leading-relaxed text-slate-400">Final prices and stock are confirmed when the sale is saved.</p>
                        </div>
                    </fieldset>

                    <InputError :message="form.errors.items" />
                    <p v-if="form.items.length && !cartValid" role="status" class="rounded-xl bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-800">Check the cart quantities and available stock. The total cannot exceed {{ money.format(99999999.99) }}.</p>
                    <button type="submit" value="checkout" :disabled="!cartValid || form.processing" class="pos-primary flex min-h-16 w-full items-center justify-center gap-3 px-4 py-4 text-base"><AppIcon name="card" class="h-6 w-6" />Checkout &amp; process</button>
                    <button type="submit" value="gcash" :disabled="!cartValid || form.processing" class="pos-gcash flex min-h-14 w-full items-center justify-center gap-2 rounded-xl border border-blue-200 px-4 py-4 text-sm font-semibold text-blue-700 transition hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-40">Pay with GCash<AppIcon name="arrow" class="h-4 w-4" /></button>
                    <p class="px-3 text-center text-[11px] leading-relaxed text-slate-400">Review payment, delivery, and gallon details before confirming. Full, partial, and unpaid sales are supported.</p>
                </div>
            </form>

            <!-- <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 bg-white px-5 py-4 text-xs text-slate-500">
                <p>Today's POS summary <span class="text-slate-400">({{ timezone }})</span>: <strong class="ml-1 text-blue-700">{{ money.format(Number(todaySummary.revenue)) }}</strong></p>
                <div class="flex flex-wrap items-center gap-4"><p>Completed sales: <strong class="text-slate-700">{{ number.format(todaySummary.sale_count) }}</strong></p><p>Units sold: <strong class="text-slate-700">{{ number.format(todaySummary.units_sold) }}</strong></p></div>
            </div> -->

            <Modal :show="checkoutOpen" :closeable="!form.processing" max-width="2xl" @close="checkoutOpen = false">
                <form @submit.prevent="submitSale" class="p-5 sm:p-7" aria-labelledby="checkout-title">
                    <div class="flex items-start justify-between gap-4"><div><h2 id="checkout-title" class="text-xl font-bold text-slate-900">Review &amp; checkout</h2><p class="mt-1 text-sm text-slate-500">{{ number.format(totalUnits) }} units · {{ money.format(totalCents / 100) }} estimated total</p></div><button type="button" :disabled="form.processing" aria-label="Close checkout" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 disabled:opacity-40" @click="checkoutOpen = false"><AppIcon name="close" class="h-5 w-5" /></button></div>

                    <p v-if="form.hasErrors" role="alert" class="mt-5 rounded-lg bg-red-50 p-3 text-sm text-red-700">The sale could not be saved. Review the highlighted fields and try again.</p>
                    <fieldset :disabled="form.processing" class="mt-6 grid gap-4 sm:grid-cols-2">
                        <legend class="sr-only">Sale details</legend>
                        <div><InputLabel for="sale-type" value="Sale type" /><select id="sale-type" v-model="form.sale_type" required class="pos-input mt-1 w-full" :aria-invalid="Boolean(form.errors.sale_type)" aria-describedby="sale-type-error"><option value="walk_in">Walk-in</option><option value="delivery">Delivery</option></select><InputError id="sale-type-error" :message="form.errors.sale_type" class="mt-2" /></div>
                        <div><InputLabel for="sale-customer" value="Customer (optional)" /><select id="sale-customer" v-model="form.customer_id" class="pos-input mt-1 w-full" :aria-invalid="Boolean(form.errors.customer_id)" aria-describedby="sale-customer-error"><option value="">No customer selected</option><option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}{{ customer.phone ? ` (${customer.phone})` : '' }}</option></select><InputError id="sale-customer-error" :message="form.errors.customer_id" class="mt-2" /></div>
                        <div v-if="form.sale_type === 'delivery'" class="sm:col-span-2"><InputLabel for="delivery-address" value="Delivery address" /><textarea id="delivery-address" v-model="form.delivery_address" rows="2" maxlength="10000" required class="pos-input mt-1 w-full" :aria-invalid="Boolean(form.errors.delivery_address)" aria-describedby="delivery-address-error" /><button v-if="selectedCustomer?.address" type="button" class="mt-2 text-xs font-medium text-blue-600 hover:underline" @click="form.delivery_address = selectedCustomer.address">Use customer address</button><InputError id="delivery-address-error" :message="form.errors.delivery_address" class="mt-2" /></div>
                    </fieldset>

                    <fieldset :disabled="form.processing" class="mt-6 border-t border-slate-100 pt-5">
                        <legend class="sr-only">Initial payment</legend>
                        <h3 class="mb-4 text-sm font-bold text-slate-800">Initial payment</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><InputLabel for="initial-payment-type" value="Payment now" />
                                <select id="initial-payment-type" v-model="form.initial_payment_type" class="pos-input mt-1 w-full" aria-describedby="initial-payment-type-error">
                                    <option value="full">Pay in full</option>
                                    <option value="none">No initial payment</option>
                                    <option value="partial" :disabled="totalCents === 0">Partial payment</option>
                                </select><InputError id="initial-payment-type-error" :message="form.errors.initial_payment_type" class="mt-2" /></div>
                            <div v-if="form.initial_payment_type !== 'none'"><InputLabel for="initial-payment-method" value="Payment method" /><select id="initial-payment-method" v-model="form.payment_method" required class="pos-input mt-1 w-full" aria-describedby="initial-payment-method-error"><option value="cash">Cash</option><option value="gcash">GCash</option></select><InputError id="initial-payment-method-error" :message="form.errors.payment_method" class="mt-2" /></div>
                            <div v-if="form.initial_payment_type === 'partial'" class="sm:col-span-2"><InputLabel for="initial-payment-amount" value="Amount received now" /><TextInput id="initial-payment-amount" v-model="form.initial_payment_amount" type="number" min="0.01" :max="totalCents / 100" step="0.01" required class="pos-input mt-1 block w-full" :aria-invalid="Boolean(form.errors.initial_payment_amount)" aria-describedby="initial-payment-amount-error" /><InputError id="initial-payment-amount-error" :message="form.errors.initial_payment_amount" class="mt-2" /></div>
                        </div>
                        <p class="mt-3 text-xs leading-relaxed text-slate-500">Unpaid amounts remain outstanding. Select a customer above to include the sale in their customer balance. GCash is recorded here after money is received.</p>
                    </fieldset>

                    <fieldset :disabled="form.processing" class="mt-5 border-t border-slate-100 pt-5">
                        <legend class="sr-only">Reusable gallons</legend>
                        <label class="flex items-center gap-2.5"><Checkbox v-model:checked="form.track_gallon_exchange" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" aria-describedby="gallon-tracking-error" /><span class="text-sm font-semibold text-slate-700">Track reusable gallon movement</span></label>
                        <InputError id="gallon-tracking-error" :message="form.errors.track_gallon_exchange" class="mt-2" />
                        <div v-if="form.track_gallon_exchange" class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div><InputLabel for="filled-gallons-out" value="Filled gallons supplied" /><TextInput id="filled-gallons-out" v-model="form.filled_gallons_out" type="number" min="0" max="4294967295" step="1" required class="pos-input mt-1 block w-full" :aria-invalid="Boolean(form.errors.filled_gallons_out)" aria-describedby="filled-gallons-out-error" /><InputError id="filled-gallons-out-error" :message="form.errors.filled_gallons_out" class="mt-2" /></div>
                            <div><InputLabel for="empty-gallons-returned" value="Empty gallons returned" /><TextInput id="empty-gallons-returned" v-model="form.empty_gallons_returned" type="number" min="0" max="4294967295" step="1" required class="pos-input mt-1 block w-full" :aria-invalid="Boolean(form.errors.empty_gallons_returned)" aria-describedby="empty-gallons-returned-error" /><InputError id="empty-gallons-returned-error" :message="form.errors.empty_gallons_returned" class="mt-2" /></div>
                            <p class="text-xs text-slate-500 sm:col-span-2">Record actual counts. Returned empties are separate from payments and discounts.</p>
                        </div>
                    </fieldset>

                    <dl class="mt-6 grid gap-2 rounded-xl bg-blue-50/60 p-4 text-sm" aria-live="polite"><div class="flex justify-between gap-3"><dt class="text-slate-500">Estimated total</dt><dd class="font-bold tabular-nums text-slate-800">{{ money.format(totalCents / 100) }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Payment to record</dt><dd class="font-semibold tabular-nums text-emerald-700">{{ money.format(initialPaymentCents / 100) }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Remaining credit</dt><dd class="font-semibold tabular-nums text-orange-600">{{ money.format(Math.max(0, totalCents - initialPaymentCents) / 100) }}</dd></div></dl>
                    <p class="mt-3 text-[11px] text-slate-400">Prices and totals are recalculated when you save. No payment is recorded until you confirm.</p>
                    <InputError :message="form.errors.submission_token" class="mt-3" />
                    <div class="mt-6 flex flex-wrap items-center justify-end gap-3"><button type="button" :disabled="form.processing" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40" @click="checkoutOpen = false">Back to cart</button><button type="submit" :disabled="form.processing || !cartValid" class="pos-primary inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm"><AppIcon name="card" class="h-4 w-4" />{{ form.processing ? 'Saving sale...' : 'Confirm & save sale' }}</button></div>
                </form>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.pos-panel {
    border: 1px solid #e5edf5;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 2px 6px #172b4d04;
}

.product-card {
    box-shadow: 0 2px 5px #172b4d05;
}

.pos-primary {
    border-radius: 10px;
    background: linear-gradient(125deg, #087cdd, #0864c8);
    color: white;
    font-weight: 600;
    box-shadow: 0 3px 7px #0868cf15;
    transition: opacity 150ms, box-shadow 150ms;
}

.pos-primary:hover:not(:disabled) {
    background: #0861c4;
    box-shadow: 0 3px 10px #0868cf30;
}

.pos-primary:focus-visible {
    outline: 2px solid #3b82f6;
    outline-offset: 3px;
}

.pos-primary:disabled {
    cursor: not-allowed;
    background: #c4d1df;
    box-shadow: none;
}

.pos-gcash {
    background: #eaf4ff;
}

.pos-input {
    border: 1px solid #dce5ef;
    border-radius: 10px;
    background-color: #fff;
    padding-block: 10px;
    font-size: 13px;
}

.pos-input:focus {
    border-color: #3b82f6;
    --tw-ring-color: #3b82f6;
}

.cart-items {
    max-height: min(44dvh, 430px);
}

.quantity-button {
    display: inline-flex;
    height: 30px;
    align-items: center;
    justify-content: center;
    padding-inline: 8px;
    color: #537095;
}

.quantity-button:hover:not(:disabled) {
    background: #e6f0fc;
}

.quantity-button:disabled {
    color: #c1cbd8;
    cursor: not-allowed;
}

.quantity-input {
    width: 42px;
    min-width: 0;
    height: 30px;
    border: 0;
    background: white;
    padding: 0 2px;
    text-align: center;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    appearance: textfield;
    -moz-appearance: textfield;
}

.quantity-input::-webkit-outer-spin-button,
.quantity-input::-webkit-inner-spin-button {
    margin: 0;
    -webkit-appearance: none;
}

@media (min-width: 1280px) {
    .pos-grid {
        grid-template-columns: minmax(0, 1fr) 350px;
    }
}

@media (min-width: 1536px) {
    .pos-grid {
        grid-template-columns: minmax(0, 1fr) 380px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .pos-primary {
        transition: none;
    }
}
</style>
