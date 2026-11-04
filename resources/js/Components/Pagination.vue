<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    pagination: { type: Object, required: true },
});
</script>

<template>
    <div v-if="pagination.total > 0" class="flex flex-col gap-4 border-t border-gray-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-600">
            Showing {{ pagination.from ?? 0 }}–{{ pagination.to ?? 0 }} of {{ pagination.total }}
        </p>
        <nav aria-label="Pagination" class="flex items-center gap-3 text-sm">
            <Link v-if="pagination.prev_page_url" :href="pagination.prev_page_url" preserve-scroll preserve-state rel="prev" class="rounded-md border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                Previous
            </Link>
            <span v-else aria-disabled="true" class="rounded-md border border-gray-200 px-3 py-2 text-gray-400">Previous</span>
            <span aria-live="polite" class="text-gray-600">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
            <Link v-if="pagination.next_page_url" :href="pagination.next_page_url" preserve-scroll preserve-state rel="next" class="rounded-md border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                Next
            </Link>
            <span v-else aria-disabled="true" class="rounded-md border border-gray-200 px-3 py-2 text-gray-400">Next</span>
        </nav>
    </div>
</template>
