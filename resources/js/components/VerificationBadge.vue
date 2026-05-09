<template>
    <VChip
        v-if="config"
        :color="config.color"
        :variant="config.variant"
        :size="size"
        :prepend-icon="config.icon"
        class="font-weight-bold"
    >
        {{ config.label }}
    </VChip>
</template>

<script setup>
const props = defineProps({
    status: { type: String, default: null },
    size: { type: String, default: "small" },
})

const STATUS = {
    ai_scan_complete: {
        label: "AI scan",
        color: "default",
        variant: "tonal",
        icon: "tabler-robot",
    },
    human_review_pending: {
        label: "Pending review",
        color: "warning",
        variant: "tonal",
        icon: "tabler-clock",
    },
    human_verified: {
        label: "Human verified",
        color: "primary",
        variant: "flat",
        icon: "tabler-shield-check",
    },
    finalized: {
        label: "Finalized",
        color: "primary",
        variant: "flat",
        icon: "tabler-lock",
    },
}

const config = computed(() => STATUS[props.status] ?? null)
</script>
