<script setup>
defineProps({ bottle: { type: Boolean, default: false } });
</script>

<template>
    <svg viewBox="0 0 64 80" fill="none" aria-hidden="true">
        <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <g v-if="bottle">
                <path d="M26 5h12v9H26z" fill="#b9e4ff" />
                <path d="M26 14v7L17 35v32a7 7 0 0 0 7 7h16a7 7 0 0 0 7-7V35L38 21v-7" fill="#d6efff" />
                <path d="M18 37h28M18 45h28M18 63h28M18 68h28" />
                <path d="M18 49h28v10H18z" fill="#8ccfff" />
                <path d="M26 25h12M26 8h12" />
            </g>
            <g v-else>
                <path d="M26 5h12v10H26z" fill="#a3d9ff" />
                <path d="M25 15v5l-10 7a9 9 0 0 0-4 8v31a8 8 0 0 0 8 8h26a8 8 0 0 0 8-8V35a9 9 0 0 0-4-8l-10-7v-5" fill="#c9eaff" />
                <path d="M17 37h30v26H17z" fill="#93d0ff" stroke="none" />
                <path d="M12 35h40M12 43h40M12 63h40M12 69h40M23 25h18M26 9h12" />
                <path d="M43 45v16M18 47v11" stroke="#eaf7ff" stroke-width="3" />
                <path d="M42 26c5 3 6 5 6 8" />
            </g>
        </g>
    </svg>
</template>
