<script setup>
defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="waterwise-logo"
        :class="{ 'waterwise-logo--compact': compact }"
        role="img"
        aria-label="WaterWise POS"
    >
        <svg viewBox="0 0 104 114" fill="currentColor" aria-hidden="true">
            <path d="M57 110C48 88 44 62 50 40C55 22 66 8 80 9C94 10 104 23 101 38C99 51 86 56 77 66C63 82 58 97 57 110Z" />
            <path d="M36 55C27 41 23 27 29 14C35 1 46 0 52 10C59 24 42 29 36 55Z" />
            <path d="M45 110C35 92 19 87 9 82C-2 77 0 62 11 56C25 49 34 66 38 79C42 91 44 101 45 110Z" />
        </svg>

        <span class="waterwise-logo__wordmark" aria-hidden="true">
            <span class="waterwise-logo__name">WaterWise</span>
            <span class="waterwise-logo__pos">POS</span>
        </span>
    </div>
</template>

<style scoped>
.waterwise-logo {
    display: inline-flex;
    align-items: center;
    gap: 0.32em;
    color: #007bff;
    font-family: Arial, sans-serif;
    font-size: clamp(2.25rem, 3.55vw, 3.875rem);
    line-height: 1;
}

.waterwise-logo > svg {
    width: 1.8em;
    height: 1.9em;
    flex-shrink: 0;
}

.waterwise-logo__wordmark {
    display: flex;
    flex-direction: column;
    gap: 0.04em;
    padding-top: 0.15em;
}

.waterwise-logo__name {
    color: #061a3e;
    font-weight: 800;
    letter-spacing: -0.045em;
}

.waterwise-logo__pos {
    display: flex;
    align-items: center;
    gap: 0.35em;
    font-size: 0.6em;
    font-weight: 700;
    letter-spacing: 0.06em;
}

.waterwise-logo__pos::before,
.waterwise-logo__pos::after {
    height: 1.5px;
    flex: 1;
    background: currentColor;
    content: '';
}

.waterwise-logo--compact {
    font-size: clamp(1.8rem, 2.5vw, 2.75rem);
}
</style>
