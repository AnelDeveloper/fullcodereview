<template>
    <div>
        <!-- HEADER (hidden when showing results) -->
        <div v-if="step !== 'results'" class="d-flex align-center justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Run a code review</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Pick a repo, hit run. Each review uses one credit.
                </p>
            </div>
        </div>

        <!-- Stripe return banners -->
        <VAlert v-if="returnBanner === 'success'" type="success" variant="tonal" closable class="mb-4" @click:close="returnBanner = null">
            Payment received — your credits are ready. Pick a repo below.
        </VAlert>
        <VAlert v-if="returnBanner === 'canceled'" type="warning" variant="tonal" closable class="mb-4" @click:close="returnBanner = null">
            Checkout canceled. No charge was made.
        </VAlert>
        <VAlert v-if="oauthError" type="error" variant="tonal" closable class="mb-4" @click:close="oauthError = ''">
            GitHub connection failed: {{ oauthError }}
        </VAlert>

        <!-- ANALYZING -->
        <div v-if="step === 'analyzing'" class="mx-auto text-center" style="max-width:560px">
            <VCard variant="outlined">
                <VCardText class="pa-10">
                    <div class="d-flex justify-center mb-6">
                        <VProgressCircular indeterminate size="64" width="4" color="primary" />
                    </div>
                    <h3 class="text-h5 font-weight-bold mb-2">Analyzing your code</h3>
                    <p class="text-body-2 text-medium-emphasis font-mono mb-6 text-truncate">
                        {{ analyzingTarget }}
                    </p>
                    <VProgressLinear :model-value="progress" color="primary" rounded class="mb-2" />
                    <div class="d-flex justify-space-between text-caption text-medium-emphasis">
                        <span>{{ progressLabel }}</span>
                        <span class="font-weight-semibold">{{ Math.round(progress) }}%</span>
                    </div>
                </VCardText>
            </VCard>
        </div>

        <!-- RESULTS -->
        <div v-else-if="step === 'results' && result">
            <VCard variant="outlined" class="mb-6">
                <VCardText class="pa-6">
                    <div class="d-flex justify-space-between align-start flex-wrap ga-4 mb-6">
                        <div>
                            <div class="d-flex align-center ga-2 text-body-2 text-medium-emphasis mb-1">
                                <VIcon icon="tabler-brand-github" size="16" />
                                <span class="font-mono">{{ result.repoName }}</span>
                            </div>
                            <h2 class="text-h4 font-weight-bold">Code Review Results</h2>
                            <p class="text-body-2 text-medium-emphasis mt-1">
                                {{ result.filesScanned }} files · {{ result.linesAnalyzed.toLocaleString() }} lines · {{ totalIssues }} issues found
                            </p>
                        </div>
                        <div class="d-flex flex-wrap ga-2">
                            <VBtn
                                color="primary" rounded="pill" prepend-icon="tabler-download"
                                :href="`/api/analyses/${result.id}/report.pdf?token=${token}`"
                            >
                                Download PDF
                            </VBtn>
                            <VBtn variant="outlined" rounded="pill" @click="resetAll">Start over</VBtn>
                        </div>
                    </div>

                    <VAlert variant="tonal" border="start" density="compact" class="mb-6">
                        <VIcon icon="tabler-send" color="primary" class="me-2" />
                        Full PDF report has been emailed to <strong>{{ authStore.user?.email }}</strong>.
                    </VAlert>

                    <VRow>
                        <VCol cols="6" md="3"><ScoreRing :score="result.overallScore" label="Overall" icon="tabler-trending-up" /></VCol>
                        <VCol cols="6" md="3"><ScoreRing :score="result.securityScore" label="Security" icon="tabler-shield" /></VCol>
                        <VCol cols="6" md="3"><ScoreRing :score="result.performanceScore" label="Performance" icon="tabler-bolt" /></VCol>
                        <VCol cols="6" md="3"><ScoreRing :score="result.qualityScore" label="Quality" icon="tabler-code" /></VCol>
                    </VRow>
                </VCardText>
            </VCard>

            <IssueSection title="Security" icon="tabler-shield" color="error" :issues="result.issues.security || []" />
            <IssueSection title="Performance" icon="tabler-bolt" color="warning" :issues="result.issues.performance || []" />
            <IssueSection title="Code Quality" icon="tabler-code" color="primary" :issues="result.issues.quality || []" />
        </div>

        <!-- DEFAULT: balance card + repo runner -->
        <div v-else>
            <VRow>
                <!-- Balance / buy CTA -->
                <VCol cols="12" md="4">
                    <VCard class="balance-card h-100">
                        <VCardText class="pa-6">
                            <div class="text-caption text-uppercase font-weight-semibold text-primary mb-2">
                                Code Review Credits
                            </div>
                            <div class="d-flex align-baseline ga-2 mb-2">
                                <span class="text-h2 font-weight-bold">{{ authStore.credits }}</span>
                                <span class="text-medium-emphasis">{{ authStore.credits === 1 ? "credit" : "credits" }} available</span>
                            </div>
                            <p v-if="authStore.credits === 0" class="text-body-2 text-medium-emphasis mb-5">
                                Buy your first review to get started — pick the categories you care about, prices add up.
                            </p>
                            <p v-else class="text-body-2 text-medium-emphasis mb-5">
                                Each review consumes one credit. Buy more whenever you need them.
                            </p>

                            <VBtn
                                block color="primary" rounded="pill" size="large"
                                prepend-icon="tabler-plus"
                                @click="buyDialog = true"
                            >
                                Buy review credits
                            </VBtn>
                        </VCardText>
                    </VCard>
                </VCol>

                <!-- Repo runner -->
                <VCol cols="12" md="8">
                    <VCard variant="outlined">
                        <VCardText class="pa-6">
                            <div class="d-flex align-center justify-space-between flex-wrap ga-3 mb-4">
                                <h3 class="text-h6 font-weight-bold">Pick a repository</h3>
                                <VBtnToggle
                                    v-model="repoSource" mandatory color="primary" rounded="pill"
                                    density="comfortable"
                                >
                                    <VBtn value="connected">My GitHub</VBtn>
                                    <VBtn value="public">Public URL</VBtn>
                                </VBtnToggle>
                            </div>

                            <!-- Connected GitHub -->
                            <div v-if="repoSource === 'connected'">
                                <div v-if="!githubConnected">
                                    <p class="text-body-2 text-medium-emphasis mb-3">
                                        Connect your GitHub account to scan your private and public repos. Read-only access.
                                    </p>
                                    <VBtn
                                        color="black" size="large" rounded="pill"
                                        prepend-icon="tabler-brand-github"
                                        @click="handleConnectGithub"
                                    >
                                        Connect GitHub
                                    </VBtn>
                                </div>

                                <div v-else>
                                    <VCard variant="outlined" class="d-flex align-center justify-space-between pa-3 mb-4">
                                        <div class="d-flex align-center ga-3">
                                            <VAvatar size="36" :image="githubAvatar" />
                                            <div>
                                                <div class="text-caption text-medium-emphasis">Connected as</div>
                                                <div class="text-body-2 font-weight-semibold">@{{ githubLogin }}</div>
                                            </div>
                                        </div>
                                        <VIcon icon="tabler-brand-github" />
                                    </VCard>

                                    <div v-if="loadingRepos" class="d-flex align-center ga-2 text-body-2 text-medium-emphasis">
                                        <VProgressCircular indeterminate size="16" width="2" />
                                        <span>Loading your repositories…</span>
                                    </div>
                                    <VAlert v-else-if="reposError" type="error" variant="tonal" density="compact">{{ reposError }}</VAlert>

                                    <template v-else-if="repos.length">
                                        <AppTextField
                                            v-model="repoFilter"
                                            :placeholder="`Search ${repos.length} repositories…`"
                                            prepend-inner-icon="tabler-search"
                                            density="compact"
                                            class="mb-2"
                                        />
                                        <VCard variant="outlined" class="overflow-y-auto" style="max-height:320px;">
                                            <VList density="compact">
                                                <VListItem
                                                    v-for="r in filteredRepos.slice(0, 50)"
                                                    :key="r.fullName"
                                                    :active="selectedRepo?.fullName === r.fullName"
                                                    @click="selectedRepo = r"
                                                >
                                                    <VListItemTitle class="font-mono text-body-2">
                                                        {{ r.fullName }}
                                                        <VChip v-if="r.private" size="x-small" color="warning" variant="flat" class="ms-2">private</VChip>
                                                    </VListItemTitle>
                                                    <VListItemSubtitle v-if="r.description">{{ r.description }}</VListItemSubtitle>
                                                    <VListItemSubtitle v-if="r.language" class="text-caption">{{ r.language }}</VListItemSubtitle>
                                                    <template v-if="selectedRepo?.fullName === r.fullName" #append>
                                                        <VIcon icon="tabler-circle-check" color="primary" />
                                                    </template>
                                                </VListItem>
                                            </VList>
                                        </VCard>
                                    </template>
                                </div>
                            </div>

                            <!-- Public URL -->
                            <div v-else>
                                <AppTextField
                                    v-model="repoUrl"
                                    placeholder="https://github.com/owner/repo"
                                    prepend-inner-icon="tabler-brand-github"
                                />
                                <p class="text-caption text-medium-emphasis mt-2">For public repos only. For private repos, switch to "My GitHub".</p>
                            </div>

                            <VAlert v-if="analysisError" type="error" variant="tonal" density="compact" class="mt-3">{{ analysisError }}</VAlert>

                            <VBtn
                                block size="large" rounded="pill" color="primary"
                                class="mt-5" prepend-icon="tabler-sparkles"
                                :disabled="!canRun || authStore.credits === 0"
                                @click="handleAnalyze"
                            >
                                {{ runButtonLabel }}
                            </VBtn>
                        </VCardText>
                    </VCard>
                </VCol>
            </VRow>
        </div>

        <!-- BUY DIALOG -->
        <VDialog v-model="buyDialog" max-width="1100" scrollable>
            <VCard>
                <VCardTitle class="d-flex align-center justify-space-between pa-6 pb-2">
                    <div>
                        <div class="text-h5 font-weight-bold">Buy a code review</div>
                        <div class="text-body-2 text-medium-emphasis">Pick what you want reviewed — minimum $15.</div>
                    </div>
                    <VBtn icon="tabler-x" variant="text" size="small" @click="buyDialog = false" />
                </VCardTitle>
                <VCardText class="pa-6 pt-2">
                    <CategoryConfigurator />
                </VCardText>
            </VCard>
        </VDialog>
    </div>
</template>

<script setup>
import {
    fetchCodeForSession,
    runCodeCheck,
    fetchUserRepos,
    githubLoginUrl,
} from "@/utils/codeCheck"
import ScoreRing from "@/components/ScoreRing.vue"
import IssueSection from "@/components/IssueSection.vue"
import CategoryConfigurator from "@/components/CategoryConfigurator.vue"
import { useAuthStore } from "@/stores/auth"

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const token = computed(() => useCookie("accessToken").value || "")

const step = ref("home")
const returnBanner = ref(null)
const buyDialog = ref(false)

// Repo selection
const repoSource = ref("connected")
const repoUrl = ref("")
const repoFilter = ref("")
const repos = ref([])
const loadingRepos = ref(false)
const reposError = ref("")
const githubConnected = ref(false)
const githubLogin = ref(null)
const githubAvatar = ref(null)
const selectedRepo = ref(null)
const oauthError = ref("")

// Analysis run state
const analysisError = ref("")
const progress = ref(0)
const progressLabel = ref("")
const result = ref(null)

const filteredRepos = computed(() => {
    const q = repoFilter.value.toLowerCase().trim()
    if (!q) return repos.value
    return repos.value.filter(r =>
        r.fullName.toLowerCase().includes(q) ||
        (r.description || "").toLowerCase().includes(q),
    )
})

const totalIssues = computed(() =>
    result.value
        ? (result.value.issues.security?.length || 0)
            + (result.value.issues.performance?.length || 0)
            + (result.value.issues.quality?.length || 0)
        : 0
)

const analyzingTarget = computed(() =>
    repoSource.value === "connected" && selectedRepo.value
        ? selectedRepo.value.fullName
        : repoUrl.value
)

const canRun = computed(() => {
    if (repoSource.value === "connected") return !!selectedRepo.value
    return /^https?:\/\/(www\.)?github\.com\/[^/]+\/[^/]+/i.test(repoUrl.value.trim())
})

const runButtonLabel = computed(() => {
    if (authStore.credits === 0) return "Buy credits to run a review"
    if (!canRun.value) return repoSource.value === "connected" ? "Pick a repository" : "Paste a GitHub URL"
    return "Run code review"
})

// Open buy dialog from CreditsPill click event
const onOpenBuy = () => { buyDialog.value = true }

onMounted(async () => {
    window.addEventListener("codereview:open-buy", onOpenBuy)

    const sessionId = route.query.session_id
    const canceled = route.query.canceled
    const ghConnected = route.query.gh_connected
    const ghError = route.query.gh_error
    const buy = route.query.buy

    if (buy) {
        buyDialog.value = true
        clearQuery(["buy"])
    }

    if (canceled) {
        returnBanner.value = "canceled"
        clearQuery(["canceled"])
    }

    if (ghError) {
        oauthError.value = decodeURIComponent(ghError)
        clearQuery(["gh_error"])
    }

    if (ghConnected) {
        clearQuery(["gh_connected"])
        await loadGithubRepos()
    }

    if (sessionId) {
        try {
            await fetchCodeForSession(sessionId)
        } catch { /* ignore */ }
        await authStore.refreshCredits()
        returnBanner.value = "success"
        clearQuery(["session_id"])
    }

    // Try to load github repos automatically if user already connected
    if (authStore.user?.github_login || authStore.credits >= 0) {
        await loadGithubRepos({ silent: true })
    }
})

onUnmounted(() => {
    window.removeEventListener("codereview:open-buy", onOpenBuy)
})

const clearQuery = keys => {
    const q = { ...route.query }
    for (const k of keys) delete q[k]
    router.replace({ query: q })
}

const loadGithubRepos = async ({ silent = false } = {}) => {
    loadingRepos.value = !silent
    reposError.value = ""
    try {
        const r = await fetchUserRepos()
        githubLogin.value = r.login
        githubAvatar.value = r.avatarUrl
        repos.value = r.repos
        githubConnected.value = true
    } catch (e) {
        if (e?.data?.code === "not_connected") {
            githubConnected.value = false
        } else if (!silent) {
            reposError.value = e?.data?.message || e.message
        }
    } finally {
        loadingRepos.value = false
    }
}

const handleConnectGithub = () => {
    oauthError.value = ""
    window.location.href = githubLoginUrl()
}

const handleAnalyze = async () => {
    analysisError.value = ""
    let opts
    if (repoSource.value === "connected") {
        if (!selectedRepo.value) { analysisError.value = "Pick a repository to analyze."; return }
        opts = { repoFullName: selectedRepo.value.fullName }
    } else {
        if (!/^https?:\/\/(www\.)?github\.com\/[^/]+\/[^/]+/i.test(repoUrl.value.trim())) {
            analysisError.value = "Expected URL like https://github.com/owner/repo"
            return
        }
        opts = { repoUrl: repoUrl.value.trim() }
    }

    step.value = "analyzing"
    progress.value = 0

    const intro = [
        ["Verifying credit…", 8, 400],
        ["Fetching repository metadata…", 18, 700],
        ["Reading source files…", 32, 900],
        ["Mapping the stack…", 45, 800],
        ["Running security scan…", 58, 900],
        ["Analyzing performance patterns…", 70, 800],
    ]
    const waitLabels = [
        "Reviewing code quality with AI…",
        "Auditing database access patterns…",
        "Checking for N+1 queries…",
        "Looking for auth and access-control issues…",
        "Synthesizing findings across the repo…",
        "Cross-checking severity rankings…",
        "Almost done — wrapping up…",
    ]

    const requestPromise = runCodeCheck(opts).catch(e => e)

    for (const [label, pct, delay] of intro) {
        progressLabel.value = label
        await new Promise(r => setTimeout(r, delay))
        progress.value = pct
    }

    let labelIndex = 0
    progressLabel.value = waitLabels[0]
    const ticker = window.setInterval(() => {
        progress.value = Math.min(95, progress.value + Math.max(0.3, (95 - progress.value) * 0.04))
        labelIndex = (labelIndex + 1) % waitLabels.length
        progressLabel.value = waitLabels[labelIndex]
    }, 2000)

    const r = await requestPromise
    window.clearInterval(ticker)

    if (r instanceof Error || (r && r.message && !r.analysis)) {
        analysisError.value = r?.data?.message || r?.message || "Analysis failed."
        step.value = "home"
        return
    }

    progressLabel.value = "Compiling report…"
    progress.value = 100
    await new Promise(r => setTimeout(r, 400))
    result.value = r.analysis
    if (typeof r.creditsRemaining === "number") authStore.setCredits(r.creditsRemaining)
    step.value = "results"
}

const resetAll = () => {
    step.value = "home"
    repoUrl.value = ""
    analysisError.value = ""
    progress.value = 0
    progressLabel.value = ""
    result.value = null
    selectedRepo.value = null
    repoFilter.value = ""
}
</script>

<style lang="scss" scoped>
.balance-card {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.08), rgba(236, 72, 153, 0.06));
    border: 1px solid rgba(139, 92, 246, 0.25);
}
</style>
