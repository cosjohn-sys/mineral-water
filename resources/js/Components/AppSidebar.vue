<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import WaterWiseLogo from '@/Components/WaterWiseLogo.vue';
import { Link } from '@inertiajs/vue3';

defineEmits(['navigate']);

const navigation = [
    { label: 'Dashboard', route: 'dashboard', match: 'dashboard', icon: 'dashboard' },
    { label: 'Sales', route: 'sales.create', match: 'sales.*', icon: 'sales' },
    { label: 'Customers', route: 'customers.index', match: 'customers.*', icon: 'customers' },
    { label: 'Products', route: 'products.index', match: 'products.*', icon: 'products' },
    { label: 'Gallon exchanges', route: 'gallon-exchanges.index', match: 'gallon-exchanges.*', icon: 'gallons' },
    { label: 'Expenses', route: 'expenses.index', match: 'expenses.*', icon: 'expenses' },
    { label: 'Reports', route: 'reports.index', match: 'reports.*', icon: 'reports' },
];
</script>

<template>
    <div class="sidebar-content flex h-full flex-col text-white">
        <Link :href="route('dashboard')" class="flex min-h-32 shrink-0 items-center justify-center px-4 py-7 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-6px] focus-visible:outline-white" @click="$emit('navigate')">
            <WaterWiseLogo compact class="sidebar-logo" />
        </Link>

        <nav aria-label="Main navigation" class="flex flex-1 flex-col gap-2 overflow-y-auto px-3 py-3">
            <Link
                v-for="item in navigation"
                :key="item.route"
                :href="route(item.route)"
                :aria-current="route().current(item.match) ? 'page' : undefined"
                class="sidebar-link flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-white motion-reduce:transition-none"
                :class="route().current(item.match) ? 'sidebar-link-active text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white'"
                @click="$emit('navigate')"
            >
                <AppIcon :name="item.icon" class="h-5 w-5 shrink-0" />
                {{ item.label }}
            </Link>
        </nav>

        <div class="flex shrink-0 flex-col gap-2 px-3 pb-5 pt-6">
            <Link :href="route('profile.edit')" :aria-current="route().current('profile.*') ? 'page' : undefined" class="sidebar-link flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-blue-100 hover:bg-white/10 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" :class="{ 'sidebar-link-active': route().current('profile.*') }" @click="$emit('navigate')">
                <AppIcon name="settings" class="h-5 w-5" />
                Account settings
            </Link>
            <Link :href="route('logout')" method="post" as="button" class="sidebar-link flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-medium text-blue-100 hover:bg-white/10 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" @click="$emit('navigate')">
                <AppIcon name="logout" class="h-5 w-5" />
                Log out
            </Link>
            <p class="px-4 pt-4 text-[11px] tracking-wide text-blue-200">WaterWise POS · Refilling made simple</p>
        </div>
    </div>
</template>

<style scoped>
.sidebar-content {
    background: linear-gradient(170deg, #0879ea 0%, #0063d3 55%, #005cc4 100%);
}

.sidebar-logo.waterwise-logo--compact {
    color: #fff;
    font-size: 1.45rem;
}

.sidebar-logo :deep(.waterwise-logo__name) {
    color: #fff;
}

.sidebar-logo :deep(.waterwise-logo__pos) {
    color: #c8e9ff;
}

.sidebar-link-active {
    background: linear-gradient(110deg, #2199ff, #0c80f4);
    box-shadow: 0 5px 14px #0048a333, inset 0 1px 0 #ffffff20;
}
</style>
