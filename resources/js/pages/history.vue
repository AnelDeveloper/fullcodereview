<template>
    <div>
        <div class="d-flex align-center justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold">History</h1>
                <p class="text-body-2 text-medium-emphasis">Your past code reviews.</p>
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

        <VRow v-else>
            <VCol v-for="item in items" :key="item.id" cols="12" md="6">
                <VCard variant="outlined" class="h-100" :to="`/analyses/${item.id}`">
                    <VCardText class="pa-5">
                        <div class="d-flex align-center ga-2 text-body-2 text-medium-emphasis mb-2">
                            <VIcon icon="tabler-brand-github" size="16" />
                            <span class="font-mono text-truncate">{{ item.repoFullName }}</span>
                        </div>
                        <div class="d-flex align-center ga-3 mb-3">
                            <div class="text-h4 font-weight-bold" :style="{ color: scoreColor(item.overallScore) }">
                                {{ item.overallScore }}
                            </div>
                            <span class="text-body-2 text-medium-emphasis">/ 100 overall</span>
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
                        </div>

                        <div class="d-flex justify-space-between text-caption text-medium-emphasis">
                            <span>{{ item.filesScanned }} files · {{ item.totalIssues }} issues</span>
                            <span>{{ formatDate(item.createdAt) }}</span>
                        </div>
                    </VCardText>
                </VCard>
            </VCol>
        </VRow>
    </div>
</template>

<script setup>
import { fetchHistory } from "@/utils/codeCheck"

const items = ref([])
const loading = ref(true)
const error = ref("")

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

const scoreColor = s => {
    if (s >= 85) return "rgb(76,175,80)"
    if (s >= 70) return "rgb(255,193,7)"
    if (s >= 50) return "rgb(255,152,0)"
    return "rgb(244,67,54)"
}

const formatDate = d => {
    try { return new Date(d).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" }) }
    catch { return d }
}

onMounted(load)
</script>
