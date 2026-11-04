<script setup>
defineProps({ name: { type: String, required: true } });

const paths = {
    dashboard: 'm3 10 9-7 9 7M5 9v11h5v-6h4v6h5V9',
    sales: 'M3 3h2l2.5 12h11L21 7H6M9 20h.01M18 20h.01',
    customers: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 3a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.87M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
    products: 'm12 3 9 5-9 5-9-5 9-5ZM3 8v9l9 5 9-5V8M12 13v9M7.5 5.5l9 5',
    gallons: 'M12 3S5 11 5 15a7 7 0 0 0 14 0c0-4-7-12-7-12ZM8 15a4 4 0 0 0 4 4',
    expenses: 'M5 3h14v18l-3-2-4 2-4-2-3 2V3ZM9 7h6M9 11h6M9 15h3',
    reports: 'M4 20V10M10 20V4M16 20v-8M22 20H2',
    settings: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1 1-3Z',
    logout: 'M9 21H4V3h5M14 7l5 5-5 5M8 12h13',
    menu: 'M4 6h16M4 12h16M4 18h16',
    close: 'm6 6 12 12M6 18 18 6',
    chevron: 'm6 9 6 6 6-6',
    arrow: 'M4 12h16m-6-6 6 6-6 6',
    plus: 'M12 5v14M5 12h14',
    minus: 'M5 12h14',
    trash: 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7',
    search: 'm21 21-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z',
    card: 'M3 5h18v14H3V5ZM3 10h18M7 15h3',
    store: 'M3 10h18l-2-6H5l-2 6ZM5 10v11h14V10M9 21v-7h6v7M3 10a3 3 0 0 0 4.5 2.6A3 3 0 0 0 12 10a3 3 0 0 0 4.5 2.6A3 3 0 0 0 21 10',
    calendar: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z',
    info: 'M12 11v6M12 7h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z',
};
</script>

<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path :d="paths[name]" />
    </svg>
</template>
