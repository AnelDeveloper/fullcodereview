<template>
    <VCard variant="outlined" class="readiness-card">
        <VCardText class="pa-6">
            <div class="d-flex justify-space-between align-start flex-wrap ga-4 mb-4">
                <div>
                    <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis mb-1" style="letter-spacing: 1px;">
                        Production readiness
                    </div>
                    <div class="d-flex align-baseline ga-2">
                        <span class="text-h2 font-weight-black" :style="{ color: scoreColor }">
                            {{ readinessScore ?? '—' }}
                        </span>
                        <span class="text-h6 text-medium-emphasis">/ 100</span>
                    </div>
                </div>

                <VChip
                    :color="statusColor"
                    variant="flat"
                    size="default"
                    class="font-weight-bold"
                    :prepend-icon="statusIcon"
                >
                    {{ statusLabel }}
                </VChip>
            </div>

            <!-- Score bar -->
            <div class="readiness-bar mb-5">
                <div
                    class="readiness-bar__fill"
                    :style="{ width: `${readinessScore ?? 0}%`, background: scoreColor }"
                ></div>
            </div>

            <!-- Blocker counts -->
            <VRow no-gutters class="readiness-counts">
                <VCol cols="6">
                    <div class="d-flex align-center ga-3">
                        <div class="count-pill count-pill--critical">
                            {{ criticalBlockerCount ?? 0 }}
                        </div>
                        <div>
                            <div class="text-body-2 font-weight-bold">Critical blockers</div>
                            <div class="text-caption text-medium-emphasis">Must fix before launch</div>
                        </div>
                    </div>
                </VCol>
                <VCol cols="6">
                    <div class="d-flex align-center ga-3">
                        <div class="count-pill count-pill--high">
                            {{ highBlockerCount ?? 0 }}
                        </div>
                        <div>
                            <div class="text-body-2 font-weight-bold">High-severity</div>
                            <div class="text-caption text-medium-emphasis">Fix in next sprint</div>
                        </div>
                    </div>
                </VCol>
            </VRow>
        </VCardText>
    </VCard>
</template>

<script setup>
const props = defineProps({
    readinessScore: { type: Number, default: null },
    readinessStatus: { type: String, default: null },
    criticalBlockerCount: { type: Number, default: 0 },
    highBlockerCount: { type: Number, default: 0 },
})

const STATUS = {
    launch_ready:    { label: "Launch ready",    color: "success", icon: "tabler-rocket" },
    needs_attention: { label: "Needs attention", color: "warning", icon: "tabler-alert-triangle" },
    blocked:         { label: "Blocked",         color: "error",   icon: "tabler-shield-x" },
}
const PENDING = { label: "Pending", color: "default", icon: "tabler-clock" }

const status = computed(() => STATUS[props.readinessStatus] ?? PENDING)
const statusLabel = computed(() => status.value.label)
const statusColor = computed(() => status.value.color)
const statusIcon = computed(() => status.value.icon)

const scoreColor = computed(() => {
    const s = props.readinessScore
    if (s == null) return "rgb(150,150,160)"
    if (s >= 85) return "#22c55e"
    if (s >= 70) return "#eab308"
    if (s >= 50) return "#f97316"
    return "#ef4444"
})
</script>

<style lang="scss" scoped>
.readiness-bar {
    height: 8px;
    background: rgba(150, 150, 160, 0.15);
    border-radius: 4px;
    overflow: hidden;
}
.readiness-bar__fill {
    height: 100%;
    transition: width .4s ease;
    border-radius: 4px;
}
.count-pill {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 18px;
    font-weight: 800;
    flex-shrink: 0;
}
.count-pill--critical {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}
.count-pill--high {
    background: rgba(249, 115, 22, 0.12);
    color: #f97316;
}
</style>
