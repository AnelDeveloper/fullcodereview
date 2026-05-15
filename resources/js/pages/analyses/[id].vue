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

        <VCard v-else-if="isRunning" variant="outlined" class="text-center">
            <VCardText class="pa-10">
                <div class="d-flex justify-center mb-6">
                    <VProgressCircular indeterminate size="64" width="4" color="primary" />
                </div>
                <h3 class="text-h5 font-weight-bold mb-2">Auditing your code…</h3>
                <p v-if="analysis?.repoName" class="text-body-2 text-medium-emphasis font-mono mb-4 text-truncate">
                    {{ analysis.repoName }}
                </p>
                <p class="text-body-2 text-medium-emphasis" style="max-width:480px;margin:0 auto;">
                    This usually takes a minute or two. You can navigate to other pages —
                    we'll keep going in the background and let you know when it's ready.
                </p>
            </VCardText>
        </VCard>

        <VCard v-else-if="isFailed" variant="outlined">
            <VCardText class="pa-10 text-center">
                <VIcon icon="tabler-alert-triangle" size="48" color="error" class="mb-4" />
                <h3 class="text-h5 font-weight-bold mb-2">Audit failed</h3>
                <p class="text-body-2 text-medium-emphasis mb-4">
                    {{ analysis?.errorMessage || "Something went wrong while auditing this repo." }}
                </p>
                <p class="text-caption text-medium-emphasis">Your credits have been refunded.</p>
                <VBtn class="mt-4" color="primary" rounded="pill" to="/review">Run a new audit</VBtn>
            </VCardText>
        </VCard>

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
                                <h2 class="text-h4 font-weight-bold">Code Audit Results</h2>
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
                                Request human audit
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

            <!-- Manual feedback CTA — always available even after the auto-prompt has fired -->
            <div v-if="analysis.status === 'completed'" class="d-flex justify-center mt-8 mb-2">
                <VBtn variant="tonal" color="primary" prepend-icon="tabler-message-circle" @click="feedbackOpen = true">
                    Give us feedback on this audit
                </VBtn>
            </div>
        </template>

        <FeedbackDialog v-model="feedbackOpen" mode="audit" :analysis-id="analysis?.id || null" />
    </div>
</template>

<script setup>
import ScoreRing from "@/components/ScoreRing.vue"
import IssueSection from "@/components/IssueSection.vue"
import ReadinessScoreCard from "@/components/ReadinessScoreCard.vue"
import ExecutiveSummaryBlock from "@/components/ExecutiveSummaryBlock.vue"
import VerificationBadge from "@/components/VerificationBadge.vue"
import TrustBanner from "@/components/TrustBanner.vue"
import FeedbackDialog from "@/components/FeedbackDialog.vue"
import { fetchAnalysis, submitForReview } from "@/utils/codeCheck"
import { useAnalysisRunStore } from "@/stores/analysisRun"
import { onBeforeUnmount, watch } from "vue"

const route = useRoute()
const analysisRun = useAnalysisRunStore()
const analysis = ref(null)
const loading = ref(true)
const error = ref("")
const submitting = ref(false)
const actionMessage = ref("")
const token = computed(() => useCookie("accessToken").value || "")

const isRunning = computed(() =>
    analysis.value && (analysis.value.status === "pending" || analysis.value.status === "running"),
)
const isFailed = computed(() => analysis.value?.status === "failed")

const totalIssues = computed(() => {
    const i = analysis.value?.issues || {}
    return (i.security?.length || 0) + (i.performance?.length || 0) + (i.quality?.length || 0)
})

const formatDate = d => {
    try { return new Date(d).toLocaleString(undefined, { year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" }) }
    catch { return d }
}

let pollTimer = null

const load = async () => {
    try {
        const r = await fetchAnalysis(route.params.id)
        analysis.value = r.analysis
        // If the row is still being processed, set up a poll loop so the
        // page flips to results the moment the queue worker finishes.
        if (r.analysis?.status === "pending" || r.analysis?.status === "running") {
            schedulePoll()
        } else {
            // If the global banner is still tracking this analysis, clear it —
            // the user is now looking at the report directly.
            if (analysisRun.id === r.analysis?.id) analysisRun.clear()
        }
    } catch (e) {
        error.value = e?.data?.message || e.message
    } finally {
        loading.value = false
    }
}

const schedulePoll = () => {
    if (pollTimer) return
    pollTimer = window.setInterval(async () => {
        try {
            const r = await fetchAnalysis(route.params.id)
            analysis.value = r.analysis
            if (r.analysis?.status === "completed" || r.analysis?.status === "failed") {
                window.clearInterval(pollTimer)
                pollTimer = null
                if (analysisRun.id === r.analysis?.id) analysisRun.clear()
            }
        } catch { /* keep polling */ }
    }, 4000)
}

onBeforeUnmount(() => {
    if (pollTimer) { window.clearInterval(pollTimer); pollTimer = null }
})

// Auto-prompt for feedback once per (user, analysis) when an audit finishes.
// The localStorage flag is per analysis ID so we don't re-prompt on revisit
// or after a soft reload. Manual "Give feedback" button below remains
// available even after the prompt is dismissed.
const feedbackOpen = ref(false)

const feedbackStorageKey = (id) => `audit-feedback-prompted:${id}`

watch(
    () => analysis.value?.status,
    (status, prev) => {
        if (status !== "completed") return
        const id = analysis.value?.id
        if (!id) return
        try {
            if (localStorage.getItem(feedbackStorageKey(id))) return
            // Defer slightly so the report renders before the dialog overlays it.
            setTimeout(() => {
                feedbackOpen.value = true
                localStorage.setItem(feedbackStorageKey(id), "1")
            }, 1200)
        } catch { /* private mode etc. — just skip */ }
    },
    { immediate: true },
)

const onSubmitForReview = async () => {
    submitting.value = true
    error.value = ""
    try {
        const r = await submitForReview(route.params.id)
        actionMessage.value = r.message || "Submitted for audit."
        if (analysis.value) analysis.value.verificationStatus = r.verificationStatus
    } catch (e) {
        error.value = e?.data?.message || e.message
    } finally {
        submitting.value = false
    }
}

onMounted(load)
</script>
