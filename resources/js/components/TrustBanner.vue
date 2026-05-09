<template>
    <VAlert
        variant="tonal"
        density="compact"
        color="primary"
        icon="tabler-shield-lock"
        class="trust-banner"
    >
        <div class="d-flex flex-wrap ga-x-4 ga-y-1 align-center text-caption">
            <span class="font-weight-bold">Your code is private.</span>
            <span class="d-flex align-center ga-1">
                <VIcon icon="tabler-circle-check" size="14" /> Read-only access
            </span>
            <span class="d-flex align-center ga-1">
                <VIcon icon="tabler-circle-check" size="14" /> Never used for AI training
            </span>
            <span class="d-flex align-center ga-1">
                <VIcon icon="tabler-circle-check" size="14" /> Revoke anytime
            </span>
            <span class="d-flex align-center ga-1">
                <VIcon icon="tabler-circle-check" size="14" /> NDA available
            </span>
        </div>
    </VAlert>
</template>

<style lang="scss" scoped>
.trust-banner {
    border-radius: 10px;
}
.ga-x-4 { column-gap: 16px; }
.ga-y-1 { row-gap: 4px; }
</style>
