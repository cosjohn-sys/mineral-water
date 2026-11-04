<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ expense: { type: Object, default: null }, today: { type: String, required: true } });
const form = useForm({
    description: props.expense?.description ?? '', category: props.expense?.category ?? '',
    amount: props.expense ? String(props.expense.amount) : '', expense_date: props.expense?.expense_date ?? props.today,
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="expense ? 'Edit expense' : 'Add expense'" />
        <template #header><h2 class="text-xl font-semibold text-gray-800">{{ expense ? 'Edit expense' : 'Add operating expense' }}</h2></template>
        <div class="py-12"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><div class="rounded-lg bg-white p-6 shadow-sm">
            <form @submit.prevent="expense ? form.put(route('expenses.update', expense.id)) : form.post(route('expenses.store'))" class="flex max-w-xl flex-col gap-6">
                <div><InputLabel for="expense-description" value="Description" /><textarea id="expense-description" v-model="form.description" rows="3" maxlength="10000" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" aria-describedby="expense-description-error" /><InputError id="expense-description-error" :message="form.errors.description" class="mt-2" /></div>
                <div><InputLabel for="expense-category" value="Category" /><TextInput id="expense-category" v-model="form.category" maxlength="255" required class="mt-1 block w-full" placeholder="Utilities, transport, supplies..." aria-describedby="expense-category-error" /><InputError id="expense-category-error" :message="form.errors.category" class="mt-2" /></div>
                <div><InputLabel for="expense-amount" value="Amount" /><TextInput id="expense-amount" v-model="form.amount" type="number" min="0.01" max="99999999.99" step="0.01" required class="mt-1 block w-full" aria-describedby="expense-amount-error" /><InputError id="expense-amount-error" :message="form.errors.amount" class="mt-2" /></div>
                <div><InputLabel for="expense-date" value="Expense date" /><TextInput id="expense-date" v-model="form.expense_date" type="date" :max="today" required class="mt-1 block w-full" aria-describedby="expense-date-error" /><InputError id="expense-date-error" :message="form.errors.expense_date" class="mt-2" /></div>
                <div class="flex items-center gap-4"><PrimaryButton :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save expense' }}</PrimaryButton><Link :href="expense ? route('expenses.show', expense.id) : route('expenses.index')" class="text-sm text-gray-600 underline">Cancel</Link></div>
            </form>
        </div></div></div>
    </AuthenticatedLayout>
</template>
