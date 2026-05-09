<template>
    <div>
        <div class="d-flex align-center justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold">Audit history</h1>
                <p class="text-body-2 text-medium-emphasis">Your past production-readiness reviews.</p>
            </div>
            <VBtn to="/" prepend-icon="tabler-plus" color="primary" rounded="pill">
                New review
            </VBtn>
        </div>

        <VAlert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">{{ error }}</VAlert>

        <div v-if="loading" class="d-flex justify-center py-10">
            <VProgressCircular indeterminate color="primary" />
        </div>

        <VCard v-else-if="!items.length" variant="outlined">
            <VCardText class="text-center py-10">
                <VIcon icon="tabler-history" size="56" color="primary" class="mb-3" />
                <h3 class="text-h6 mb-1">No reviews yet</h3>
                <p class="text-body-2 text-medium-emphasis mb-4">
                    Run your first review and it'll show up here.
                </p>
                <VBtn to="/" color="primary" rounded="pill">
                    Run a review
                </VBtn>
            </VCardText>
        </VCard>

        <template v-else>
            <!-- Filters -->
            <VCard variant="outlined" class="mb-4">
                <VCardText class="py-3 px-4">
                    <div class="d-flex flex-wrap align-center ga-x-4 ga-y-3">
                        <div class="d-flex flex-wrap align-center ga-2">
                            <div class="text-caption text-medium-emphasis font-weight-bold">
                                STATUS
                            </div>
                            <VChip
                                v-for="opt in readinessOptions"
                                :key="opt.value"
                                :variant="readinessFilter === opt.value ? 'flat' : 'tonal'"
                                :color="readinessFilter === opt.value ? 'primary' : 'default'"
                                size="small"
                                class="font-weight-bold"
                                @click="readinessFilter = opt.value"
                            >
                                {{ opt.label }}
                            </VChip>
                        </div>

                        <VDivider vertical class="d-none d-md-block" />

                        <div class="d-flex flex-wrap align-center ga-2">
                            <div class="text-caption text-medium-emphasis font-weight-bold">
                                VERIFICATION
                            </div>
                            <VChip
                                v-for="opt in verificationOptions"
                                :key="opt.value"
                                :variant="verificationFilter === opt.value ? 'flat' : 'tonal'"
                                :color="verificationFilter === opt.value ? 'primary' : 'default'"
                                size="small"
                                class="font-weight-bold"
                                @click="verificationFilter = opt.value"
                            >
                                {{ opt.label }}
                            </VChip>
                        </div>

                        <VSpacer />
                        <span class="text-caption text-medium-emphasis">
                            {{ filtered.length }} of {{ items.length }}
                        </span>
                    </div>
                </VCardText>
            </VCard>

            <VRow>
                <VCol v-for="item in filtered" :key="item.id" cols="12" md="6">
                    <VCard variant="outlined" class="h-100" :to="`/analyses/${item.id}`">
                        <VCardText class="pa-5">
                            <div class="d-flex align-center justify-space-between ga-2 mb-2">
                                <div class="d-flex align-center ga-2 text-body-2 text-medium-emphasis min-w-0">
                                    <VIcon icon="tabler-brand-github" size="16" />
                                    <span class="font-mono text-truncate">{{ item.repoFullName }}</span>
                                </div>
                                <VerificationBadge :status="item.verificationStatus" size="x-small" />
                            </div>

                            <div class="d-flex align-center justify-space-between mb-3">
                                <div class="d-flex align-baseline ga-3">
                                    <div class="text-h4 font-weight-bold" :style="{ color: scoreColor(item.overallScore) }">
                                        {{ item.overallScore }}
                                    </div>
                                    <span class="text-body-2 text-medium-emphasis">/ 100 overall</span>
                                </div>
                                <VChip
                                    v-if="item.readinessStatus"
                                    :color="readinessChipColor(item.readinessStatus)"
                                    variant="tonal"
                                    size="small"
                                    class="font-weight-bold"
                                >
                                    {{ readinessLabel(item.readinessStatus) }}
                                </VChip>
                            </div>

                            <div class="d-flex flex-wrap ga-2 mb-3">
                                <VChip size="small" variant="tonal" color="error" prepend-icon="tabler-shield">
                                    Sec {{ item.securityScore }}
                                </VChip>
                                <VChip size="small" variant="tonal" color="warning" prepend-icon="tabler-bolt">
                                    Perf {{ item.performanceScore }}
                                </VChip>
                                <VChip size="small" variant="tonal" color="primary" prepend-icon="tabler-code">
                                    Quality {{ item.qualityScore }}
                                </VChip>
                                <VChip
                                    v-if="(item.criticalBlockerCount || 0) > 0"
                                    size="small"
                                    variant="flat"
                                    color="error"
                                    prepend-icon="tabler-alert-octagon"
                                >
                                    {{ item.criticalBlockerCount }} critical
                                </VChip>
                            </div>

                            <div class="d-flex justify-space-between text-caption text-medium-emphasis">
                                <span>{{ item.filesScanned }} files · {{ item.totalIssues }} issues</span>
                                <span>{{ formatDate(item.createdAt) }}</span>
                            </div>
                        </VCardText>
                    </VCard>
                </VCol>
            </VRow>

            <VCard v-if="!filtered.length" variant="outlined" class="mt-2">
                <VCardText class="text-center py-8 text-medium-emphasis">
                    No reviews match the current filters.
                </VCardText>
            </VCard>
        </template>
    </div>
</template>

<script setup>
import VerificationBadge from "@/components/VerificationBadge.vue"
import { fetchHistory } from "@/utils/codeCheck"

const items = ref([])
const loading = ref(true)
const error = ref("")
const readinessFilter = ref("all")
const verificationFilter = ref("all")

const readinessOptions = [
    { value: "all",             label: "All" },
    { value: "launch_ready",    label: "Launch ready" },
    { value: "needs_attention", label: "Needs attention" },
    { value: "blocked",         label: "Blocked" },
]

const verificationOptions = [
    { value: "all",                  label: "All" },
    { value: "human_verified",       label: "Verified" },
    { value: "human_review_pending", label: "Pending" },
    { value: "ai_scan_complete",     label: "AI only" },
]

const load = async () => {
    loading.value = true
    error.value = ""
    try {
        const r = await fetchHistory()
        items.value = r.items || []
    } catch (e) {
        error.value = e?.data?.message || e.message
    } finally {
        loading.value = false
    }
}

const filtered = computed(() => {
    return items.value.filter(it => {
        if (readinessFilter.value !== "all" && it.readinessStatus !== readinessFilter.value) return false
        if (verificationFilter.value !== "all" && it.verificationStatus !== verificationFilter.value) return false
        return true
    })
})

const scoreColor = s => {
    if (s >= 85) return "rgb(76,175,80)"
    if (s >= 70) return "rgb(255,193,7)"
    if (s >= 50) return "rgb(255,152,0)"
    return "rgb(244,67,54)"
}

const readinessChipColor = s => ({
    launch_ready: "success",
    needs_attention: "warning",
    blocked: "error",
}[s] || "default")

const readinessLabel = s => ({
    launch_ready: "Launch ready",
    needs_attention: "Needs attention",
    blocked: "Blocked",
}[s] || s)

const formatDate = d => {
    try { return new Date(d).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" }) }
    catch { return d }
}

onMounted(load)
</script>

<style scoped>
.min-w-0 { min-width: 0; }
</style>
