<template>
    <VCard v-if="hasContent" variant="outlined" class="mb-6 exec-summary">
        <VCardText class="pa-6">
            <div class="d-flex align-center ga-2 mb-4">
                <VIcon icon="tabler-clipboard-text" color="primary" />
                <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis" style="letter-spacing: 1px;">
                    Executive summary
                </div>
            </div>

            <p v-if="summary.plain_english" class="text-body-1 mb-6 plain-english">
                {{ summary.plain_english }}
            </p>

            <VRow>
                <!-- Business risks -->
                <VCol v-if="summary.business_risks?.length" cols="12" md="6">
                    <div class="text-overline font-weight-bold text-medium-emphasis mb-3">
                        Business risks
                    </div>
                    <ul class="risk-list">
                        <li v-for="(r, i) in summary.business_risks" :key="i" class="risk-item">
                            <div class="d-flex align-start ga-3">
                                <VIcon icon="tabler-alert-triangle" color="error" size="18" class="mt-1 flex-shrink-0" />
                                <div>
                                    <div class="text-body-2 font-weight-bold mb-1">{{ r.title }}</div>
                                    <div class="text-body-2 text-medium-emphasis">{{ r.impact }}</div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </VCol>

                <!-- Top critical -->
                <VCol v-if="summary.top_critical?.length" cols="12" md="6">
                    <div class="text-overline font-weight-bold text-medium-emphasis mb-3">
                        Top {{ summary.top_critical.length }} priorities
                    </div>
                    <ul class="risk-list">
                        <li v-for="(t, i) in summary.top_critical" :key="i" class="risk-item">
                            <div class="d-flex align-start ga-3">
                                <VChip :color="severityColor(t.severity)" variant="flat" size="x-small" class="font-weight-bold mt-1 flex-shrink-0">
                                    {{ t.severity }}
                                </VChip>
                                <div class="min-w-0">
                                    <div class="text-body-2 font-weight-bold">{{ t.title }}</div>
                                    <div class="text-caption text-medium-emphasis font-mono text-truncate">
                                        {{ t.file }}<span v-if="t.line">:{{ t.line }}</span>
                                    </div>
                                    <div v-if="t.fix_summary" class="text-body-2 text-medium-emphasis mt-1">
                                        {{ t.fix_summary }}
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </VCol>
            </VRow>

            <!-- Next steps -->
            <div v-if="summary.next_steps?.length" class="mt-6 pt-6 border-t">
                <div class="text-overline font-weight-bold text-medium-emphasis mb-3">
                    Recommended next steps
                </div>
                <ol class="next-steps">
                    <li v-for="(step, i) in summary.next_steps" :key="i" class="next-step">
                        <span class="next-step__num">{{ i + 1 }}</span>
                        <span>{{ step }}</span>
                    </li>
                </ol>
            </div>
        </VCardText>
    </VCard>
</template>

<script setup>
const props = defineProps({
    summary: { type: Object, default: null },
})

const hasContent = computed(() => {
    if (! props.summary) return false
    return !!(
        props.summary.plain_english
        || props.summary.business_risks?.length
        || props.summary.top_critical?.length
        || props.summary.next_steps?.length
    )
})

const severityColor = (sev) => {
    switch ((sev || "").toLowerCase()) {
        case "critical": return "error"
        case "high":     return "warning"
        case "medium":   return "primary"
        case "low":      return "info"
        default:         return "default"
    }
}
</script>

<style lang="scss" scoped>
.plain-english {
    line-height: 1.7;
}

.risk-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.risk-item {
    padding: 12px 0;
    border-bottom: 1px solid rgba(150, 150, 160, 0.12);
    &:last-child { border-bottom: none; }
}

.next-steps {
    list-style: none;
    padding: 0;
    margin: 0;
    counter-reset: step;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.next-step {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    line-height: 1.5;
}
.next-step__num {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: rgba(var(--v-theme-on-surface), 0.08);
    color: rgb(var(--v-theme-on-surface));
    font-weight: 800;
    font-size: 12px;
    display: grid;
    place-items: center;
    margin-top: 1px;
}

.border-t { border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.min-w-0 { min-width: 0; }
</style>
