<template>
    <div>
        <VBtn to="/history" variant="text" prepend-icon="tabler-arrow-left" class="mb-4">
            Back to history
        </VBtn>

        <VAlert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">{{ error }}</VAlert>

        <div v-if="loading" class="d-flex justify-center py-10">
            <VProgressCircular indeterminate color="primary" />
        </div>

        <template v-else-if="analysis">
            <VCard variant="outlined" class="mb-6">
                <VCardText class="pa-6">
                    <div class="d-flex justify-space-between align-start flex-wrap ga-4 mb-6">
                        <div>
                            <div class="d-flex align-center ga-2 text-body-2 text-medium-emphasis mb-1">
                                <VIcon icon="tabler-brand-github" size="16" />
                                <span class="font-mono">{{ analysis.repoName }}</span>
                            </div>
                            <h2 class="text-h4 font-weight-bold">Code Review Results</h2>
                            <p class="text-body-2 text-medium-emphasis mt-1">
                                {{ analysis.filesScanned }} files · {{ analysis.linesAnalyzed.toLocaleString() }} lines · {{ totalIssues }} issues
                            </p>
                        </div>
                        <VBtn
                            color="primary" rounded="pill"
                            prepend-icon="tabler-download"
                            :href="`/api/analyses/${analysis.id}/report.pdf?token=${token}`"
                        >
                            Download PDF
                        </VBtn>
                    </div>

                    <VRow>
                        <VCol cols="6" md="3"><ScoreRing :score="analysis.overallScore" label="Overall" icon="tabler-trending-up" /></VCol>
                        <VCol cols="6" md="3"><ScoreRing :score="analysis.securityScore" label="Security" icon="tabler-shield" /></VCol>
                        <VCol cols="6" md="3"><ScoreRing :score="analysis.performanceScore" label="Performance" icon="tabler-bolt" /></VCol>
                        <VCol cols="6" md="3"><ScoreRing :score="analysis.qualityScore" label="Quality" icon="tabler-code" /></VCol>
                    </VRow>
                </VCardText>
            </VCard>

            <IssueSection title="Security" icon="tabler-shield" color="error" :issues="analysis.issues?.security || []" />
            <IssueSection title="Performance" icon="tabler-bolt" color="warning" :issues="analysis.issues?.performance || []" />
            <IssueSection title="Code Quality" icon="tabler-code" color="primary" :issues="analysis.issues?.quality || []" />
        </template>
    </div>
</template>

<script setup>
import ScoreRing from "@/components/ScoreRing.vue"
import IssueSection from "@/components/IssueSection.vue"
import { fetchAnalysis } from "@/utils/codeCheck"

const route = useRoute()
const analysis = ref(null)
const loading = ref(true)
const error = ref("")
const token = computed(() => useCookie("accessToken").value || "")

const totalIssues = computed(() => {
    const i = analysis.value?.issues || {}
    return (i.security?.length || 0) + (i.performance?.length || 0) + (i.quality?.length || 0)
})

onMounted(async () => {
    try {
        const r = await fetchAnalysis(route.params.id)
        analysis.value = r.analysis
    } catch (e) {
        error.value = e?.data?.message || e.message
    } finally {
        loading.value = false
    }
})
</script>
