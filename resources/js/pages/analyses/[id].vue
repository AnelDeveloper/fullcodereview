<template>
    <div>
        <VBtn to="/history" variant="text" prepend-icon="tabler-arrow-left" class="mb-4">
            Back to history
        </VBtn>

        <VAlert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">{{ error }}</VAlert>
        <VAlert v-if="actionMessage" type="success" variant="tonal" density="compact" closable class="mb-4" @click:close="actionMessage = ''">
            {{ actionMessage }}
        </VAlert>

        <div v-if="loading" class="d-flex justify-center py-10">
            <VProgressCircular indeterminate color="primary" />
        </div>

        <template v-else-if="analysis">
            <!-- Trust strip -->
            <TrustBanner class="mb-4" />

            <!-- Verified-by-engineer note (when applicable) -->
            <VAlert
                v-if="analysis.verificationStatus === 'human_verified' || analysis.verificationStatus === 'finalized'"
                type="success"
                variant="tonal"
                density="comfortable"
                class="mb-4"
                icon="tabler-shield-check"
            >
                <div class="d-flex flex-wrap align-center justify-space-between ga-3">
                    <div>
                        <div class="font-weight-bold">Verified by senior engineer</div>
                        <div class="text-caption">
                            {{ analysis.reviewer?.name || 'Senior engineer' }}
                            · {{ formatDate(analysis.verifiedAt) }}
                        </div>
                    </div>
                    <div v-if="analysis.reviewerNotes" class="text-body-2 text-medium-emphasis" style="max-width:520px;">
                        "{{ analysis.reviewerNotes }}"
                    </div>
                </div>
            </VAlert>

            <!-- Header card -->
            <VCard variant="outlined" class="mb-6">
                <VCardText class="pa-6">
                    <div class="d-flex justify-space-between align-start flex-wrap ga-4 mb-6">
                        <div>
                            <div class="d-flex align-center ga-2 text-body-2 text-medium-emphasis mb-1">
                                <VIcon icon="tabler-brand-github" size="16" />
                                <span class="font-mono">{{ analysis.repoName }}</span>
                            </div>
                            <div class="d-flex align-center ga-2 flex-wrap mb-1">
                                <h2 class="text-h4 font-weight-bold">Code Review Results</h2>
                                <VerificationBadge :status="analysis.verificationStatus" size="small" />
                            </div>
                            <p class="text-body-2 text-medium-emphasis mt-1">
                                {{ analysis.filesScanned }} files · {{ analysis.linesAnalyzed.toLocaleString() }} lines · {{ totalIssues }} issues
                            </p>
                        </div>
                        <div class="d-flex flex-wrap ga-2">
                            <VBtn
                                v-if="analysis.verificationStatus === 'ai_scan_complete'"
                                color="primary"
                                variant="outlined"
                                rounded="pill"
                                prepend-icon="tabler-shield-check"
                                :loading="submitting"
                                @click="onSubmitForReview"
                            >
                                Request human review
                            </VBtn>
                            <VBtn
                                color="primary"
                                rounded="pill"
                                prepend-icon="tabler-download"
                                :href="`/api/analyses/${analysis.id}/report.pdf?token=${token}`"
                            >
                                Download PDF
                            </VBtn>
                        </div>
                    </div>

                    <VRow>
                        <VCol cols="6" md="3"><ScoreRing :score="analysis.overallScore" label="Overall" icon="tabler-trending-up" /></VCol>
                        <VCol cols="6" md="3"><ScoreRing :score="analysis.securityScore" label="Security" icon="tabler-shield" /></VCol>
                        <VCol cols="6" md="3"><ScoreRing :score="analysis.performanceScore" label="Performance" icon="tabler-bolt" /></VCol>
                        <VCol cols="6" md="3"><ScoreRing :score="analysis.qualityScore" label="Quality" icon="tabler-code" /></VCol>
                    </VRow>
                </VCardText>
            </VCard>

            <!-- Production readiness -->
            <ReadinessScoreCard
                v-if="analysis.readinessScore !== null && analysis.readinessScore !== undefined"
                :readiness-score="analysis.readinessScore"
                :readiness-status="analysis.readinessStatus"
                :critical-blocker-count="analysis.criticalBlockerCount"
                :high-blocker-count="analysis.highBlockerCount"
                class="mb-6"
            />

            <!-- Executive summary -->
            <ExecutiveSummaryBlock :summary="analysis.executiveSummary" />

            <IssueSection title="Security" icon="tabler-shield" color="error" :issues="analysis.issues?.security || []" />
            <IssueSection title="Performance" icon="tabler-bolt" color="warning" :issues="analysis.issues?.performance || []" />
            <IssueSection title="Code Quality" icon="tabler-code" color="primary" :issues="analysis.issues?.quality || []" />
        </template>
    </div>
</template>

<script setup>
import ScoreRing from "@/components/ScoreRing.vue"
import IssueSection from "@/components/IssueSection.vue"
import ReadinessScoreCard from "@/components/ReadinessScoreCard.vue"
import ExecutiveSummaryBlock from "@/components/ExecutiveSummaryBlock.vue"
import VerificationBadge from "@/components/VerificationBadge.vue"
import TrustBanner from "@/components/TrustBanner.vue"
import { fetchAnalysis, submitForReview } from "@/utils/codeCheck"

const route = useRoute()
const analysis = ref(null)
const loading = ref(true)
const error = ref("")
const submitting = ref(false)
const actionMessage = ref("")
const token = computed(() => useCookie("accessToken").value || "")

const totalIssues = computed(() => {
    const i = analysis.value?.issues || {}
    return (i.security?.length || 0) + (i.performance?.length || 0) + (i.quality?.length || 0)
})

const formatDate = d => {
    try { return new Date(d).toLocaleString(undefined, { year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" }) }
    catch { return d }
}

const load = async () => {
    try {
        const r = await fetchAnalysis(route.params.id)
        analysis.value = r.analysis
    } catch (e) {
        error.value = e?.data?.message || e.message
    } finally {
        loading.value = false
    }
}

const onSubmitForReview = async () => {
    submitting.value = true
    error.value = ""
    try {
        const r = await submitForReview(route.params.id)
        actionMessage.value = r.message || "Submitted for review."
        if (analysis.value) analysis.value.verificationStatus = r.verificationStatus
    } catch (e) {
        error.value = e?.data?.message || e.message
    } finally {
        submitting.value = false
    }
}

onMounted(load)
</script>
