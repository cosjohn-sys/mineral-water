<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    customer: { type: Object, default: null },
});

const form = useForm({
    name: props.customer?.name ?? '',
    phone: props.customer?.phone ?? '',
    address: props.customer?.address ?? '',
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="customer ? 'Edit customer' : 'Add customer'" />
        <template #header>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-[28px]">{{ customer ? 'Edit Customer' : 'Add Customer' }}</h1>
        </template>

        <div class="flex flex-col gap-6 px-5 pb-8 pt-5 sm:px-7 lg:px-8">
            <div class="flex flex-col items-start gap-3">
                <Link :href="route('customers.index')" class="inline-flex items-center gap-2 rounded text-sm font-medium text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <AppIcon name="arrow" class="h-4 w-4 rotate-180" />
                    Customer directory
                </Link>
                <p class="text-sm leading-relaxed text-slate-500">{{ customer ? 'Keep your customer’s contact and delivery details up to date.' : 'Add a customer and keep their contact and delivery details in one place.' }}</p>
            </div>

            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(280px,1fr)]">
                <section aria-labelledby="customer-information" class="min-w-0 overflow-hidden rounded-[20px] border border-slate-200/80 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-7 sm:py-6">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-blue-600">
                                <AppIcon name="customers" class="h-5 w-5" />
                            </span>
                            <div class="flex flex-col gap-1">
                                <h2 id="customer-information" class="text-lg font-bold text-slate-900">Customer Information</h2>
                                <p class="text-xs text-slate-500">Contact details for orders and deliveries</p>
                            </div>
                        </div>
                        <span class="rounded-full border border-[#b9d8c9] bg-[#e4f5eb] px-3 py-1.5 text-xs font-medium text-[#26734e]">{{ customer ? 'Edit profile' : 'New customer' }}</span>
                    </div>

                    <form @submit.prevent="customer ? form.put(route('customers.update', customer.id)) : form.post(route('customers.store'))" :aria-busy="form.processing" class="flex flex-col">
                        <div class="flex flex-col gap-6 px-5 py-6 sm:px-7 sm:py-7">
                            <div class="flex flex-col gap-2">
                                <InputLabel for="name" class="font-semibold text-slate-700">Customer name <span class="text-rose-500" aria-hidden="true">*</span><span class="sr-only"> (required)</span></InputLabel>
                                <TextInput id="name" v-model="form.name" type="text" maxlength="255" required autofocus autocomplete="name" placeholder="e.g. Maria Santos" class="customer-input block w-full px-4 py-3 text-sm text-slate-700 placeholder:text-slate-400" :aria-invalid="Boolean(form.errors.name)" aria-describedby="name-error" />
                                <InputError id="name-error" :message="form.errors.name" />
                            </div>

                            <div class="flex flex-col gap-2">
                                <div class="flex items-center justify-between gap-3">
                                    <InputLabel for="phone" value="Contact number" class="font-semibold text-slate-700" />
                                    <span class="text-xs text-slate-400">Optional</span>
                                </div>
                                <TextInput id="phone" v-model="form.phone" type="tel" maxlength="255" autocomplete="tel" placeholder="e.g. 0917 123 4567" class="customer-input block w-full px-4 py-3 text-sm text-slate-700 placeholder:text-slate-400" :aria-invalid="Boolean(form.errors.phone)" aria-describedby="phone-help phone-error" />
                                <p id="phone-help" class="text-xs leading-relaxed text-slate-500">A number to reach your customer about orders or deliveries. This is optional.</p>
                                <InputError id="phone-error" :message="form.errors.phone" />
                            </div>

                            <div class="flex flex-col gap-2">
                                <div class="flex items-center justify-between gap-3">
                                    <InputLabel for="address" value="Address" class="font-semibold text-slate-700" />
                                    <span class="text-xs text-slate-400">Optional</span>
                                </div>
                                <textarea id="address" v-model="form.address" rows="4" maxlength="10000" autocomplete="street-address" placeholder="House or unit number, street, barangay, and city" class="customer-input block w-full resize-y px-4 py-3 text-sm leading-relaxed text-slate-700 placeholder:text-slate-400" :aria-invalid="Boolean(form.errors.address)" aria-describedby="address-help address-error" />
                                <p id="address-help" class="text-xs leading-relaxed text-slate-500">Include a nearby landmark to make deliveries easier. This is optional.</p>
                                <InputError id="address-error" :message="form.errors.address" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-slate-100 bg-slate-50/60 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                            <p class="text-xs text-slate-500"><span class="font-semibold text-rose-500" aria-hidden="true">*</span> Customer name is required</p>
                            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                                <Link :href="customer ? route('customers.show', customer.id) : route('customers.index')" class="inline-flex justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 motion-reduce:transition-none">Cancel</Link>
                                <button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60 motion-reduce:transition-none">
                                    <AppIcon :name="customer ? 'customers' : 'plus'" class="h-4 w-4" />
                                    {{ form.processing ? 'Saving…' : 'Save customer' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </section>

                <aside aria-label="Customer preview and tips" class="flex min-w-0 flex-col gap-4">
                    <section aria-labelledby="customer-preview" class="overflow-hidden rounded-[20px] border border-[#bfd3e7] bg-gradient-to-br from-[#e7f1ff] to-[#dfecfa] shadow-sm">
                        <div class="flex items-center justify-between gap-3 border-b border-[#bfd3e7]/70 px-5 py-4 sm:px-6">
                            <h2 id="customer-preview" class="text-sm font-semibold text-[#206588]">Customer Preview</h2>
                            <span class="rounded-full bg-white/70 px-2.5 py-1 text-[11px] font-medium text-[#286481]">Live preview</span>
                        </div>
                        <div class="flex flex-col gap-6 p-5 sm:p-6">
                            <div class="flex flex-col items-start gap-4">
                                <span class="flex h-14 w-14 items-center justify-center rounded-2xl border border-white/80 bg-white/70 text-blue-600 shadow-sm"><AppIcon name="customers" class="h-7 w-7" /></span>
                                <div class="flex min-w-0 w-full flex-col gap-1">
                                    <p class="break-words text-xl font-bold tracking-tight text-slate-800">{{ form.name || 'Customer name' }}</p>
                                    <p class="text-xs text-[#286481]">{{ customer ? 'Updating customer details' : 'Your new customer profile' }}</p>
                                </div>
                            </div>
                            <dl class="flex flex-col gap-4 rounded-2xl border border-white/80 bg-white/60 p-4">
                                <div class="flex flex-col gap-1.5">
                                    <dt class="text-xs font-medium text-slate-500">Contact number</dt>
                                    <dd class="break-words text-sm font-medium text-slate-700">{{ form.phone || 'No contact number added' }}</dd>
                                </div>
                                <div class="flex flex-col gap-1.5 border-t border-blue-100/80 pt-4">
                                    <dt class="text-xs font-medium text-slate-500">Address</dt>
                                    <dd class="line-clamp-4 whitespace-pre-line break-words text-sm leading-relaxed text-slate-700">{{ form.address || 'No address added' }}</dd>
                                </div>
                            </dl>
                            <p class="text-xs leading-relaxed text-[#286481]">This preview updates as you type. Select Save customer to {{ customer ? 'apply your changes.' : 'add them to your directory.' }}</p>
                        </div>
                    </section>

                    <div class="flex items-start gap-3 rounded-2xl border border-[#b9d8c9] bg-[#e4f5eb] p-5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/70 text-[#26734e]"><AppIcon name="info" class="h-4 w-4" /></span>
                        <div class="flex flex-col gap-1.5">
                            <h3 class="text-sm font-semibold text-[#26734e]">Ready for the next delivery</h3>
                            <p class="text-xs leading-relaxed text-[#426b58]">A contact number and complete address help your team coordinate orders and find the right doorstep.</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.customer-input {
    border-color: #dbe5ee;
    border-radius: 12px;
    background-color: #f8fafc;
    box-shadow: none;
}

.customer-input:focus {
    --tw-ring-color: #60a5fa;
    border-color: #60a5fa;
    background-color: #fff;
}

.customer-input[aria-invalid='true'] {
    --tw-ring-color: #fb7185;
    border-color: #fb7185;
}
</style>
