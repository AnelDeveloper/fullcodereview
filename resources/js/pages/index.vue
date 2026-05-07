<template>
    <div>
        <!-- Greeting -->
        <div class="d-flex align-center justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">
                    Welcome back<span v-if="firstName">, {{ firstName }}</span> 👋
                </h1>
                <p class="text-body-2 text-medium-emphasis">
                    Your code, reviewed by AI. Pick what to fix next.
                </p>
            </div>
            <div class="d-flex ga-2">
                <VBtn
                    color="primary"
                    rounded="pill"
                    size="large"
                    prepend-icon="tabler-sparkles"
                    to="/review"
                >
                    New Review
                </VBtn>
            </div>
        </div>

        <VAlert v-if="returnBanner === 'verified'" type="success" variant="tonal" closable class="mb-4" @click:close="returnBanner = null">
            Email confirmed — thanks!
        </VAlert>
        <VAlert v-if="returnBanner === 'verified-already'" type="info" variant="tonal" closable class="mb-4" @click:close="returnBanner = null">
            Your email was already confirmed.
        </VAlert>

        <div v-if="loading" class="d-flex justify-center py-10">
            <VProgressCircular indeterminate color="primary" />
        </div>

        <template v-else>
            <!-- Stat tiles -->
            <VRow class="mb-2">
                <VCol cols="12" sm="6" md="3">
                    <StatCard icon="tabler-stack-2" accent="primary" :value="stats.sectionsTotal || 0" label="Review scopes">
                        <template #footer>
                            <VBtn v-if="(stats.sectionsTotal || 0) === 0" size="small" variant="tonal" color="primary" to="/review">Buy scopes</VBtn>
                            <span v-else class="text-caption text-medium-emphasis">{{ sectionsBreakdown }}</span>
                        </template>
                    </StatCard>
                </VCol>
                <VCol cols="12" sm="6" md="3">
                    <StatCard icon="tabler-shield-check" accent="success" :value="stats.totalReviews" label="Reviews completed">
                        <template #footer>
                            <span class="text-caption text-medium-emphasis">{{ stats.monthReviews }} this month</span>
                        </template>
                    </StatCard>
                </VCol>
                <VCol cols="12" sm="6" md="3">
                    <StatCard icon="tabler-trending-up" accent="info" :value="stats.avgScore !== null ? stats.avgScore : '—'" label="Average overall score">
                        <template #footer>
                            <span class="text-caption text-medium-emphasis">Across all reviews</span>
                        </template>
                    </StatCard>
                </VCol>
                <VCol cols="12" sm="6" md="3">
                    <StatCard icon="tabler-bug" accent="warning" :value="stats.totalIssues" label="Issues surfaced">
                        <template #footer>
                            <span class="text-caption text-medium-emphasis">Critical → low</span>
                        </template>
                    </StatCard>
                </VCol>
            </VRow>

            <!-- Recent reviews + quick actions -->
            <VRow class="mt-2">
                <VCol cols="12" md="8">
                    <VCard variant="outlined">
                        <VCardText class="pa-6">
                            <div class="d-flex align-center justify-space-between mb-4">
                                <div>
                                    <h3 class="text-h6 font-weight-bold">Recent reviews</h3>
                                    <p class="text-caption text-medium-emphasis">Your last {{ recent.length || 0 }} runs</p>
                                </div>
                                <VBtn v-if="recent.length" variant="text" size="small" append-icon="tabler-arrow-right" to="/history">View all</VBtn>
                            </div>

                            <div v-if="!recent.length" class="empty-state text-center py-10">
                                <VIcon icon="tabler-shield-search" size="48" color="primary" class="mb-2" />
                                <h4 class="text-h6 mt-2">No reviews yet</h4>
                                <p class="text-body-2 text-medium-emphasis mb-4">Run your first review to see it here.</p>
                                <VBtn color="primary" rounded="pill" prepend-icon="tabler-sparkles" to="/review">
                                    Start your first review
                                </VBtn>
                            </div>

                            <div v-else class="recent-list">
                                <div
                                    v-for="r in recent"
                                    :key="r.id"
                                    class="recent-row"
                                    @click="$router.push(`/analyses/${r.id}`)"
                                >
                                    <div class="recent-row__score" :style="{ background: scoreBg(r.overallScore), color: scoreFg(r.overallScore) }">
                                        {{ r.overallScore }}
                                    </div>
                                    <div class="recent-row__info">
                                        <div class="d-flex align-center ga-2 mb-1">
                                            <VIcon icon="tabler-brand-github" size="14" />
                                            <span class="font-mono text-body-2 text-truncate">{{ r.repoFullName }}</span>
                                        </div>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ r.totalIssues }} issue{{ r.totalIssues === 1 ? '' : 's' }}
                                            · {{ formatRelative(r.createdAt) }}
                                        </div>
                                    </div>
                                    <VIcon icon="tabler-chevron-right" class="recent-row__chev" />
                                </div>
                            </div>
                        </VCardText>
                    </VCard>
                </VCol>

                <VCol cols="12" md="4">
                    <VCard variant="outlined" class="mb-4">
                        <VCardText class="pa-6">
                            <div class="d-flex align-center ga-3 mb-4">
                                <div class="quick-icon">
                                    <VIcon icon="tabler-rocket" size="22" color="primary" />
                                </div>
                                <h3 class="text-subtitle-1 font-weight-bold">Quick actions</h3>
                            </div>
                            <div class="d-flex flex-column ga-2">
                                <VBtn block color="primary" rounded="pill" prepend-icon="tabler-sparkles" to="/review">Run a new review</VBtn>
                                <VBtn block variant="outlined" rounded="pill" prepend-icon="tabler-coins" to="/review?buy=1">Buy more credits</VBtn>
                                <VBtn block variant="text" rounded="pill" prepend-icon="tabler-history" to="/history">View all history</VBtn>
                            </div>
                        </VCardText>
                    </VCard>

                    <VCard variant="outlined" class="coverage-card">
                        <VCardText class="pa-6">
                            <h3 class="text-subtitle-1 font-weight-bold mb-3">What we review</h3>
                            <div class="d-flex flex-column ga-2">
                                <div class="coverage-item"><VIcon icon="tabler-shield" color="error" size="18" /><span class="text-body-2">Security — auth, injection, secrets</span></div>
                                <div class="coverage-item"><VIcon icon="tabler-database" color="warning" size="18" /><span class="text-body-2">Database — N+1, indexes, migrations</span></div>
                                <div class="coverage-item"><VIcon icon="tabler-server" color="info" size="18" /><span class="text-body-2">Backend — APIs, roles, headers</span></div>
                                <div class="coverage-item"><VIcon icon="tabler-code" color="primary" size="18" /><span class="text-body-2">Frontend — XSS, perf, state, a11y</span></div>
                            </div>
                        </VCardText>
                    </VCard>
                </VCol>
            </VRow>
        </template>
    </div>
</template>

<script setup>
import { fetchDashboard } from "@/utils/codeCheck"
import { useAuthStore } from "@/stores/auth"
import StatCard from "@/components/StatCard.vue"

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const loading = ref(true)
const stats = ref({ sections: {}, sectionsTotal: 0, totalReviews: 0, monthReviews: 0, avgScore: null, totalIssues: 0 })

const sectionsBreakdown = computed(() => {
    const s = stats.value.sections || {}
    const parts = []
    if (s.security) parts.push(`${s.security}× sec`)
    if (s.database) parts.push(`${s.database}× db`)
    if (s.backend) parts.push(`${s.backend}× be`)
    if (s.frontend) parts.push(`${s.frontend}× fe`)
    return parts.join(" · ") || "—"
})
const recent = ref([])
const returnBanner = ref(null)

const firstName = computed(() => (authStore.user?.name || "").split(/\s+/)[0])

const scoreBg = (s) => {
    if (s >= 85) return "rgba(16, 185, 129, 0.15)"
    if (s >= 70) return "rgba(245, 158, 11, 0.15)"
    if (s >= 50) return "rgba(249, 115, 22, 0.15)"
    return "rgba(239, 68, 68, 0.15)"
}
const scoreFg = (s) => {
    if (s >= 85) return "#10B981"
    if (s >= 70) return "#F59E0B"
    if (s >= 50) return "#F97316"
    return "#EF4444"
}

const formatRelative = (iso) => {
    const d = new Date(iso)
    const mins = Math.round((Date.now() - d.getTime()) / 60000)
    if (mins < 1) return "just now"
    if (mins < 60) return `${mins} min${mins === 1 ? "" : "s"} ago`
    const hours = Math.round(mins / 60)
    if (hours < 24) return `${hours} hour${hours === 1 ? "" : "s"} ago`
    const days = Math.round(hours / 24)
    if (days < 30) return `${days} day${days === 1 ? "" : "s"} ago`
    return d.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" })
}

const clearQuery = (keys) => {
    const q = { ...route.query }
    for (const k of keys) delete q[k]
    router.replace({ query: q })
}

onMounted(async () => {
    if (route.query.verified === "1") {
        returnBanner.value = "verified"
        clearQuery(["verified"])
        await authStore.fetchMe()
    } else if (route.query.verified === "already") {
        returnBanner.value = "verified-already"
        clearQuery(["verified"])
    }

    try {
        const r = await fetchDashboard()
        stats.value = r.stats
        recent.value = r.recent
        authStore.setSections(r.stats.sections)
    } catch { /* ignore */ }
    finally { loading.value = false }
})
</script>

<style lang="scss" scoped>
.empty-state {
    background: rgba(139, 92, 246, 0.04);
    border: 1px dashed rgba(139, 92, 246, 0.25);
    border-radius: 14px;
    padding: 32px 16px;
}

.recent-list { display: flex; flex-direction: column; gap: 8px; }

.recent-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 16px;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid rgba(150, 150, 160, 0.1);
    cursor: pointer;
    transition: all .18s ease;

    &:hover { border-color: rgb(var(--v-theme-primary)); transform: translateX(4px); }

    &__score {
        width: 48px; height: 48px; border-radius: 12px;
        font-weight: 800; font-size: 16px;
        display: grid; place-items: center;
    }
    &__info { min-width: 0; }
    &__chev { opacity: 0.4; }
}

.quick-icon {
    width: 40px; height: 40px; border-radius: 10px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(236, 72, 153, 0.15));
    border: 1px solid rgba(139, 92, 246, 0.25);
    display: grid; place-items: center; flex-shrink: 0;
}

.coverage-card { background: linear-gradient(180deg, rgba(139, 92, 246, 0.04), transparent); }

.coverage-item {
    display: flex; align-items: center; gap: 12px;
    padding: 8px 12px; border-radius: 10px;
    transition: background .18s ease;
    &:hover { background: rgba(139, 92, 246, 0.06); }
}
</style>
