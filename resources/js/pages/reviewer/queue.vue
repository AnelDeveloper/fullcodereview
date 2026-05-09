<template>
    <div>
        <div class="d-flex align-center justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <div class="d-flex align-center ga-2 mb-1">
                    <h1 class="text-h4 font-weight-bold">Reviewer queue</h1>
                    <VChip color="primary" variant="flat" size="small" prepend-icon="tabler-shield-check">
                        Senior engineer
                    </VChip>
                </div>
                <p class="text-body-2 text-medium-emphasis">
                    Audits awaiting human verification.
                </p>
            </div>
            <VBtn variant="text" prepend-icon="tabler-refresh" @click="load">
                Refresh
            </VBtn>
        </div>

        <VAlert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">
            {{ error }}
        </VAlert>
        <VAlert v-if="actionMessage" type="success" variant="tonal" density="compact" closable class="mb-4" @click:close="actionMessage = ''">
            {{ actionMessage }}
        </VAlert>

        <div v-if="loading" class="d-flex justify-center py-10">
            <VProgressCircular indeterminate color="primary" />
        </div>

        <VCard v-else-if="!items.length" variant="outlined">
            <VCardText class="text-center py-10">
                <VIcon icon="tabler-checks" size="56" color="success" class="mb-3" />
                <h3 class="text-h6 mb-1">Queue empty</h3>
                <p class="text-body-2 text-medium-emphasis">
                    No audits are waiting for human review.
                </p>
            </VCardText>
        </VCard>

        <VRow v-else>
            <VCol v-for="item in items" :key="item.id" cols="12">
                <VCard variant="outlined">
                    <VCardText class="pa-5">
                        <div class="d-flex flex-wrap justify-space-between ga-4 mb-4">
                            <div>
                                <div class="d-flex align-center ga-2 text-body-2 text-medium-emphasis mb-1">
                                    <VIcon icon="tabler-brand-github" size="16" />
                                    <span class="font-mono">{{ item.repoFullName }}</span>
                                </div>
                                <div class="d-flex align-center ga-3 mb-1">
                                    <span class="text-h5 font-weight-bold">Readiness {{ item.readinessScore ?? '—' }}/100</span>
                                    <VChip
                                        :color="readinessChipColor(item.readinessStatus)"
                                        variant="tonal"
                                        size="small"
                                        class="font-weight-bold"
                                    >
                                        {{ readinessLabel(item.readinessStatus) }}
                                    </VChip>
                                </div>
                                <div class="text-caption text-medium-emphasis">
                                    Submitted by {{ item.requesterEmail || 'unknown' }}
                                    · {{ formatDate(item.submittedAt) }}
                                </div>
                            </div>

                            <div class="d-flex flex-wrap ga-2">
                                <VChip
                                    v-if="(item.criticalBlockerCount || 0) > 0"
                                    color="error" variant="flat" size="small"
                                    prepend-icon="tabler-alert-octagon"
                                >
                                    {{ item.criticalBlockerCount }} critical
                                </VChip>
                                <VChip
                                    v-if="(item.highBlockerCount || 0) > 0"
                                    color="warning" variant="tonal" size="small"
                                    prepend-icon="tabler-alert-triangle"
                                >
                                    {{ item.highBlockerCount }} high
                                </VChip>
                                <VChip variant="tonal" size="small">
                                    {{ item.totalIssues }} issues total
                                </VChip>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap ga-2">
                            <VBtn
                                :to="`/analyses/${item.id}`"
                                variant="outlined"
                                rounded="pill"
                                prepend-icon="tabler-eye"
                            >
                                Open audit
                            </VBtn>
                            <VBtn
                                color="primary"
                                rounded="pill"
                                prepend-icon="tabler-shield-check"
                                @click="openApprove(item)"
                            >
                                Approve
                            </VBtn>
                        </div>
                    </VCardText>
                </VCard>
            </VCol>
        </VRow>

        <!-- Approve dialog -->
        <VDialog v-model="dialogOpen" max-width="600">
            <VCard>
                <VCardTitle class="d-flex align-center ga-2 pa-4">
                    <VIcon icon="tabler-shield-check" color="primary" />
                    Approve audit
                </VCardTitle>
                <VCardText class="px-4">
                    <div v-if="selected" class="text-body-2 text-medium-emphasis mb-4">
                        <span class="font-mono">{{ selected.repoFullName }}</span>
                        · readiness {{ selected.readinessScore }}/100
                    </div>
                    <VTextarea
                        v-model="reviewerNotes"
                        label="Reviewer notes (visible to the customer)"
                        placeholder="e.g. All 5 critical findings verified. SQL connection pattern is the most urgent fix."
                        rows="4"
                        variant="outlined"
                        class="mb-3"
                    />
                    <VTextarea
                        v-model="internalComments"
                        label="Internal comments (not visible to the customer)"
                        placeholder="Optional — for our team only."
                        rows="2"
                        variant="outlined"
                    />
                </VCardText>
                <VCardActions class="px-4 pb-4">
                    <VSpacer />
                    <VBtn variant="text" @click="dialogOpen = false">Cancel</VBtn>
                    <VBtn color="primary" rounded="pill" :loading="approving" @click="onApprove">
                        Approve &amp; verify
                    </VBtn>
                </VCardActions>
            </VCard>
        </VDialog>
    </div>
</template>

<script setup>
import { fetchReviewerQueue, approveAnalysis } from "@/utils/codeCheck"

const items = ref([])
const loading = ref(true)
const error = ref("")
const actionMessage = ref("")

const dialogOpen = ref(false)
const selected = ref(null)
const reviewerNotes = ref("")
const internalComments = ref("")
const approving = ref(false)

const load = async () => {
    loading.value = true
    error.value = ""
    try {
        const r = await fetchReviewerQueue()
        items.value = r.items || []
    } catch (e) {
        error.value = e?.data?.message || e.message
    } finally {
        loading.value = false
    }
}

const openApprove = (item) => {
    selected.value = item
    reviewerNotes.value = ""
    internalComments.value = ""
    dialogOpen.value = true
}

const onApprove = async () => {
    if (! selected.value) return
    approving.value = true
    try {
        const r = await approveAnalysis(selected.value.id, reviewerNotes.value, internalComments.value)
        actionMessage.value = `Verified ${selected.value.repoFullName}.`
        dialogOpen.value = false
        items.value = items.value.filter(i => i.id !== selected.value.id)
    } catch (e) {
        error.value = e?.data?.message || e.message
    } finally {
        approving.value = false
    }
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
    try { return new Date(d).toLocaleString(undefined, { year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" }) }
    catch { return d }
}

onMounted(load)
</script>
