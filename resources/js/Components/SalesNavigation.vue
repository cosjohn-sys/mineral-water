<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <nav aria-label="Sales navigation" class="flex gap-7 border-b border-slate-200 text-sm font-semibold">
        <Link :href="route('sales.create')" :aria-current="route().current('sales.create') ? 'page' : undefined" class="border-b-[3px] px-4 pb-3 pt-2 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500" :class="route().current('sales.create') ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-blue-600'">POS</Link>
        <Link :href="route('sales.index')" :aria-current="route().current('sales.index') ? 'page' : undefined" class="border-b-[3px] px-4 pb-3 pt-2 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500" :class="route().current('sales.index') ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-blue-600'">Sales history</Link>
    </nav>
</template>
