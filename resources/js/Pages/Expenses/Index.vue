<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ expenses: { type: Object, required: true }, totalExpenses: { type: [Number, String], required: true }, filters: { type: Object, required: true }, status: { type: String, default: null } });
const filterForm = useForm({ ...props.filters });
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Operating expenses" />
        <template #header><div class="flex flex-wrap items-center justify-between gap-4"><h2 class="text-xl font-semibold text-gray-800">Operating expenses</h2><Link :href="route('expenses.create')" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Add expense</Link></div></template>
        <div class="py-12"><div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
            <p v-if="status" role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ status }}</p>
            <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Total matching expenses across all pages</p><p class="mt-2 text-3xl font-semibold tabular-nums text-gray-900">{{ Number(totalExpenses).toFixed(2) }}</p></div>
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <form @submit.prevent="filterForm.get(route('expenses.index'), { preserveState: true, replace: true })" class="grid gap-4 p-6 md:grid-cols-2 lg:grid-cols-5">
                    <div><InputLabel for="expense-from" value="From date" /><TextInput id="expense-from" v-model="filterForm.date_from" type="date" class="mt-1 block w-full" /><InputError :message="filterForm.errors.date_from" class="mt-2" /></div>
                    <div><InputLabel for="expense-to" value="To date" /><TextInput id="expense-to" v-model="filterForm.date_to" type="date" class="mt-1 block w-full" /><InputError :message="filterForm.errors.date_to" class="mt-2" /></div>
                    <div><InputLabel for="expense-filter-category" value="Category (exact match)" /><TextInput id="expense-filter-category" v-model="filterForm.category" maxlength="255" class="mt-1 block w-full" /><InputError :message="filterForm.errors.category" class="mt-2" /></div>
                    <div><InputLabel for="expenses-per-page" value="Rows per page" /><select id="expenses-per-page" v-model="filterForm.per_page" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option></select><InputError :message="filterForm.errors.per_page" class="mt-2" /></div>
                    <div class="flex items-end gap-4"><PrimaryButton :disabled="filterForm.processing">Filter</PrimaryButton><Link :href="route('expenses.index')" class="py-2 text-sm text-gray-600 underline">Clear</Link></div>
                </form>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                    <caption class="sr-only">Operating expenses matching the selected dates and category</caption>
                    <thead class="bg-gray-50 text-gray-600"><tr><th scope="col" class="px-6 py-3">Date</th><th scope="col" class="px-6 py-3">Description</th><th scope="col" class="px-6 py-3">Category</th><th scope="col" class="px-6 py-3 text-right">Amount</th><th scope="col" class="px-6 py-3">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-200"><tr v-for="expense in expenses.data" :key="expense.id"><td class="whitespace-nowrap px-6 py-4 text-gray-600">{{ expense.expense_date }}</td><td class="max-w-md break-words px-6 py-4 text-gray-900">{{ expense.description }}</td><td class="break-words px-6 py-4 text-gray-600">{{ expense.category }}</td><td class="px-6 py-4 text-right tabular-nums text-gray-900">{{ Number(expense.amount).toFixed(2) }}</td><td class="px-6 py-4"><div class="flex gap-4"><Link :href="route('expenses.show', expense.id)" class="text-indigo-600 underline">View</Link><Link :href="route('expenses.edit', expense.id)" class="text-indigo-600 underline">Edit</Link></div></td></tr><tr v-if="expenses.data.length === 0"><td colspan="5" class="px-6 py-12 text-center text-gray-500">No expenses found.</td></tr></tbody>
                </table></div>
                <Pagination :pagination="expenses" />
            </div>
        </div></div>
    </AuthenticatedLayout>
</template>
