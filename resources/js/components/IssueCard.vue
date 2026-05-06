<template>
    <VCard variant="outlined" class="mb-3" :class="`severity-${issue.severity}`">
        <VCardText>
            <div class="d-flex align-start ga-3">
                <VIcon :icon="iconFor(issue.severity)" :color="colorFor(issue.severity)" size="24" />
                <div class="flex-1-1 min-w-0">
                    <div class="d-flex align-center justify-space-between flex-wrap ga-2">
                        <h4 class="text-subtitle-1 font-weight-semibold">{{ issue.title }}</h4>
                        <VChip size="x-small" :color="colorFor(issue.severity)" variant="flat" class="text-uppercase">
                            {{ issue.severity }}
                        </VChip>
                    </div>
                    <div class="text-caption text-medium-emphasis mt-1 font-mono">
                        <VIcon icon="tabler-file-code" size="14" class="me-1" />
                        {{ issue.file }}<span v-if="issue.line">:{{ issue.line }}</span>
                    </div>
                    <p class="text-body-2 mt-2 mb-3">{{ issue.description }}</p>
                    <div class="suggestion-box pa-3 rounded">
                        <div class="d-flex align-start ga-2">
                            <VIcon icon="tabler-sparkles" color="primary" size="18" class="mt-1" />
                            <div>
                                <div class="text-caption text-primary font-weight-bold">Suggested fix</div>
                                <div class="text-body-2 mt-1">{{ issue.suggestion }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </VCardText>
    </VCard>
</template>

<script setup>
defineProps({ issue: { type: Object, required: true } })

const colorFor = sev => ({
    critical: "error",
    high: "warning",
    medium: "warning",
    low: "info",
    info: "grey",
}[sev] || "grey")

const iconFor = sev => ({
    critical: "tabler-alert-octagon",
    high: "tabler-alert-triangle",
    medium: "tabler-alert-circle",
    low: "tabler-info-circle",
    info: "tabler-info-circle",
}[sev] || "tabler-info-circle")
</script>

<style scoped>
.suggestion-box {
    background: rgba(var(--v-theme-primary), 0.08);
    border: 1px solid rgba(var(--v-theme-primary), 0.2);
}
.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
