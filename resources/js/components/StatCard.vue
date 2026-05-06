<template>
    <VCard variant="outlined" class="stat-card h-100">
        <VCardText class="pa-5">
            <div class="d-flex align-start justify-space-between mb-3">
                <div class="stat-icon" :style="iconStyle">
                    <VIcon :icon="icon" :color="accent" size="22" />
                </div>
            </div>
            <div class="text-h3 font-weight-bold mb-1">{{ value }}</div>
            <div class="text-caption text-medium-emphasis text-uppercase font-weight-semibold tracking-wide">
                {{ label }}
            </div>
            <div v-if="$slots.footer" class="mt-3 pt-3 border-t-divider">
                <slot name="footer" />
            </div>
        </VCardText>
    </VCard>
</template>

<script setup>
const props = defineProps({
    icon: { type: String, required: true },
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    accent: { type: String, default: "primary" },
})

const iconStyle = computed(() => ({
    background: `rgba(var(--v-theme-${props.accent}), 0.12)`,
    border: `1px solid rgba(var(--v-theme-${props.accent}), 0.3)`,
}))
</script>

<style lang="scss" scoped>
.stat-card {
    transition: transform .2s ease, border-color .2s ease;
    &:hover {
        transform: translateY(-2px);
        border-color: rgba(var(--v-theme-primary), 0.4);
    }
}

.stat-icon {
    width: 44px; height: 44px;
    border-radius: 11px;
    display: grid;
    place-items: center;
}

.tracking-wide { letter-spacing: 1px; }

.border-t-divider {
    border-top: 1px solid rgba(150, 150, 160, 0.12);
}
</style>
