<template>
    <div>
        <div class="d-flex align-center justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold">Book a review call</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Pick a 30-minute slot with Anel — we'll walk through your audit, prioritize fixes, and sign off on production readiness.
                </p>
            </div>
            <VBtn variant="text" prepend-icon="tabler-arrow-left" to="/">Back to dashboard</VBtn>
        </div>

        <VCard variant="outlined" class="cal-card">
            <div v-if="iframeLoading" class="d-flex flex-column align-center justify-center pa-10">
                <VProgressCircular indeterminate color="primary" class="mb-3" />
                <p class="text-body-2 text-medium-emphasis">Loading calendar…</p>
            </div>
            <iframe
                :src="calUrl"
                class="cal-frame"
                :class="{ 'cal-frame--hidden': iframeLoading }"
                title="Schedule a review call"
                frameborder="0"
                allow="camera; microphone; fullscreen; clipboard-write"
                @load="iframeLoading = false"
            />
        </VCard>

        <p class="text-caption text-medium-emphasis text-center mt-4">
            Trouble loading?
            <a :href="calUrl" target="_blank" rel="noopener noreferrer" class="text-primary">Open the booking page in a new tab</a>.
        </p>
    </div>
</template>

<script setup>
const calUrl = "https://cal.com/anel-kujovic-azkffj/30min"
const iframeLoading = ref(true)
</script>

<style lang="scss" scoped>
.cal-card {
    overflow: hidden;
    border-radius: 16px;
}

.cal-frame {
    width: 100%;
    height: min(82vh, 900px);
    min-height: 640px;
    display: block;
    border: 0;
    background: transparent;

    &--hidden {
        display: none;
    }
}
</style>
