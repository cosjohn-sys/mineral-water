<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ expense: { type: Object, required: true }, status: { type: String, default: null } });
const confirmingDeletion = ref(false);
const deleteForm = useForm({});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Expense details" />
        <template #header><h2 class="text-xl font-semibold text-gray-800">Expense details</h2></template>
        <div class="py-12"><div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
            <p v-if="status" role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ status }}</p>
            <section class="flex flex-col gap-6 rounded-lg bg-white p-6 shadow-sm">
                <h3 class="whitespace-pre-wrap break-words text-lg font-medium text-gray-900">{{ expense.description }}</h3>
                <dl class="grid gap-6 sm:grid-cols-3"><div><dt class="text-sm text-gray-500">Date</dt><dd class="mt-1 text-gray-900">{{ expense.expense_date }}</dd></div><div><dt class="text-sm text-gray-500">Category</dt><dd class="mt-1 break-words text-gray-900">{{ expense.category }}</dd></div><div><dt class="text-sm text-gray-500">Amount</dt><dd class="mt-1 font-semibold tabular-nums text-gray-900">{{ Number(expense.amount).toFixed(2) }}</dd></div></dl>
                <div class="flex flex-wrap items-center gap-4"><Link :href="route('expenses.edit', expense.id)" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white">Edit expense</Link><DangerButton @click="confirmingDeletion = true">Delete expense</DangerButton><Link :href="route('expenses.index')" class="text-sm text-gray-600 underline">Back to expenses</Link></div>
            </section>
        </div></div>
        <Modal :show="confirmingDeletion" :closeable="!deleteForm.processing" @close="confirmingDeletion = false" aria-labelledby="delete-expense-title"><form @submit.prevent="deleteForm.delete(route('expenses.destroy', expense.id))" class="flex flex-col gap-4 p-6"><h2 id="delete-expense-title" class="text-lg font-medium text-gray-900">Delete this expense?</h2><p class="text-sm text-gray-600">This permanently removes the expense from expense totals and reports.</p><InputError :message="deleteForm.errors.expense" /><div class="flex justify-end gap-3"><SecondaryButton :disabled="deleteForm.processing" @click="confirmingDeletion = false">Cancel</SecondaryButton><DangerButton type="submit" :disabled="deleteForm.processing">Delete expense</DangerButton></div></form></Modal>
    </AuthenticatedLayout>
</template>
