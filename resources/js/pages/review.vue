<template>
    <div>
        <!-- Header -->
        <div v-if="step !== 'analyzing'" class="d-flex align-center justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Run a code review</h1>
                <p class="text-body-2 text-medium-emphasis">Pick a repo, hit run. Each review uses one credit.</p>
            </div>

            <div class="balance-pill" :class="{ 'balance-pill--zero': authStore.credits === 0 }">
                <VIcon icon="tabler-coins" size="18" />
                <div class="balance-pill__inner">
                    <div class="text-body-2 font-weight-bold">{{ authStore.credits }} credit{{ authStore.credits === 1 ? "" : "s" }}</div>
                    <div class="text-caption text-medium-emphasis">available</div>
                </div>
                <VBtn
                    size="small"
                    rounded="pill"
                    :color="authStore.credits === 0 ? 'primary' : undefined"
                    :variant="authStore.credits === 0 ? 'flat' : 'tonal'"
                    @click="buyDialog = true"
                >
                    {{ authStore.credits === 0 ? "Buy" : "+" }}
                </VBtn>
            </div>
        </div>

        <!-- Stripe / GitHub return banners -->
        <VAlert v-if="returnBanner === 'success'" type="success" variant="tonal" closable class="mb-4" @click:close="returnBanner = null">
            Payment received — credits added. Pick a repo below.
        </VAlert>
        <VAlert v-if="returnBanner === 'canceled'" type="warning" variant="tonal" closable class="mb-4" @click:close="returnBanner = null">
            Checkout canceled. No charge was made.
        </VAlert>
        <VAlert v-if="oauthError" type="error" variant="tonal" closable class="mb-4" @click:close="oauthError = ''">
            GitHub connection failed: {{ oauthError }}
        </VAlert>

        <!-- ANALYZING -->
        <div v-if="step === 'analyzing'" class="mx-auto" style="max-width:560px">
            <VCard variant="outlined" class="text-center">
                <VCardText class="pa-10">
                    <div class="d-flex justify-center mb-6">
                        <VProgressCircular indeterminate size="64" width="4" color="primary" />
                    </div>
                    <h3 class="text-h5 font-weight-bold mb-2">Analyzing your code</h3>
                    <p class="text-body-2 text-medium-emphasis font-mono mb-6 text-truncate">{{ analyzingTarget }}</p>
                    <VProgressLinear :model-value="progress" color="primary" rounded class="mb-2" />
                    <div class="d-flex justify-space-between text-caption text-medium-emphasis">
                        <span>{{ progressLabel }}</span>
                        <span class="font-weight-semibold">{{ Math.round(progress) }}%</span>
                    </div>
                </VCardText>
            </VCard>
        </div>

        <!-- REPO PICKER -->
        <VCard v-else variant="outlined">
            <VCardText class="pa-6">
                <!-- 0-credits empty state -->
                <div v-if="authStore.credits === 0" class="empty-credits text-center py-10">
                    <div class="big-icon mb-4">
                        <VIcon icon="tabler-coins" size="40" color="primary" />
                    </div>
                    <h3 class="text-h6 font-weight-bold mb-2">No credits yet</h3>
                    <p class="text-body-2 text-medium-emphasis mb-5" style="max-width:440px;margin:0 auto;">
                        Buy your first review to get started — pick the categories you care about
                        (Security, Database, Backend, Frontend) and prices stack with bundle discounts up to 20% off.
                    </p>
                    <VBtn color="primary" size="large" rounded="pill" prepend-icon="tabler-plus" @click="buyDialog = true">
                        Buy review credits
                    </VBtn>
                </div>

                <template v-else>
                    <div class="d-flex align-center justify-space-between flex-wrap ga-3 mb-4">
                        <div>
                            <h3 class="text-h6 font-weight-bold">Pick a repository</h3>
                            <p class="text-caption text-medium-emphasis">Connect your GitHub or paste a public URL</p>
                        </div>

                        <div class="seg">
                            <button
                                type="button"
                                :class="['seg__btn', repoSource === 'connected' ? 'seg__btn--active' : '']"
                                @click="repoSource = 'connected'"
                            >
                                <VIcon icon="tabler-brand-github" size="14" class="me-1" />
                                My GitHub
                            </button>
                            <button
                                type="button"
                                :class="['seg__btn', repoSource === 'public' ? 'seg__btn--active' : '']"
                                @click="repoSource = 'public'"
                            >
                                <VIcon icon="tabler-link" size="14" class="me-1" />
                                Public URL
                            </button>
                        </div>
                    </div>

                    <!-- Connected GitHub -->
                    <div v-if="repoSource === 'connected'">
                        <div v-if="!githubConnected" class="connect-cta">
                            <VIcon icon="tabler-brand-github" size="36" />
                            <div class="flex-1-1">
                                <div class="text-body-1 font-weight-semibold mb-1">Connect your GitHub account</div>
                                <p class="text-caption text-medium-emphasis mb-0">
                                    Read-only access to your private &amp; public repos.
                                    The token is removed as soon as the analysis finishes.
                                </p>
                            </div>
                            <VBtn color="primary" rounded="pill" prepend-icon="tabler-plug-connected" @click="handleConnectGithub">
                                Connect
                            </VBtn>
                        </div>

                        <div v-else>
                            <VCard variant="outlined" class="d-flex align-center justify-space-between pa-3 mb-4 connected-bar">
                                <div class="d-flex align-center ga-3">
                                    <VAvatar size="36" :image="githubAvatar" />
                                    <div>
                                        <div class="text-caption text-medium-emphasis">Connected as</div>
                                        <div class="text-body-2 font-weight-semibold">@{{ githubLogin }}</div>
                                    </div>
                                </div>
                                <VIcon icon="tabler-circle-check" color="success" />
                            </VCard>

                            <div v-if="loadingRepos" class="d-flex align-center ga-2 text-body-2 text-medium-emphasis py-4">
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
                                <VCard variant="outlined" class="repos-card">
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
                            hint="Public repos only. Switch to “My GitHub” for private repos."
                            persistent-hint
                        />
                    </div>

                    <VAlert v-if="analysisError" type="error" variant="tonal" density="compact" class="mt-4">{{ analysisError }}</VAlert>

                    <VBtn
                        block size="large" rounded="pill" color="primary"
                        class="mt-5 vibe-cta" prepend-icon="tabler-sparkles"
                        :disabled="!canRun"
                        @click="handleAnalyze"
                    >
                        {{ runButtonLabel }}
                    </VBtn>
                </template>
            </VCardText>
        </VCard>

        <!-- BUY DIALOG -->
        <VDialog v-model="buyDialog" max-width="1100" scrollable>
            <VCard>
                <VCardTitle class="d-flex align-center justify-space-between pa-6 pb-2">
                    <div>
                        <div class="text-h5 font-weight-bold">Buy a code review</div>
                        <div class="text-body-2 text-medium-emphasis">Pick what you want reviewed — minimum $20.</div>
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
    runCodeCheck,
    fetchUserRepos,
    githubLoginUrl,
} from "@/utils/codeCheck"
import CategoryConfigurator from "@/components/CategoryConfigurator.vue"
import { useAuthStore } from "@/stores/auth"

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

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

const filteredRepos = computed(() => {
    const q = repoFilter.value.toLowerCase().trim()
    if (!q) return repos.value
    return repos.value.filter(r =>
        r.fullName.toLowerCase().includes(q) ||
        (r.description || "").toLowerCase().includes(q),
    )
})

const analyzingTarget = computed(() =>
    repoSource.value === "connected" && selectedRepo.value
        ? selectedRepo.value.fullName
        : repoUrl.value
)

const canRun = computed(() => {
    if (authStore.credits === 0) return false
    if (repoSource.value === "connected") return !!selectedRepo.value
    return /^https?:\/\/(www\.)?github\.com\/[^/]+\/[^/]+/i.test(repoUrl.value.trim())
})

const runButtonLabel = computed(() => {
    if (authStore.credits === 0) return "Buy credits to run a review"
    if (!canRun.value) return repoSource.value === "connected" ? "Pick a repository" : "Paste a GitHub URL"
    return "Run code review"
})

const onOpenBuy = () => { buyDialog.value = true }

onMounted(async () => {
    window.addEventListener("codereview:open-buy", onOpenBuy)

    const lemonSuccess = route.query.lemon_success
    const ghConnected = route.query.gh_connected
    const ghError = route.query.gh_error
    const buy = route.query.buy

    if (buy) {
        buyDialog.value = true
        clearQuery(["buy"])
    }
    if (ghError) {
        oauthError.value = decodeURIComponent(ghError)
        clearQuery(["gh_error"])
    }
    if (ghConnected) {
        clearQuery(["gh_connected"])
        await loadGithubRepos()
    }
    if (lemonSuccess) {
        // Webhook may take a few seconds to land — poll credits 3x
        for (let i = 0; i < 3; i++) {
            await authStore.refreshCredits()
            if (authStore.credits > 0) break
            await new Promise(r => setTimeout(r, 1500))
        }
        returnBanner.value = "success"
        clearQuery(["lemon_success"])
    }

    if (authStore.user?.githubLogin) {
        await loadGithubRepos({ silent: true })
    }
})

onUnmounted(() => {
    window.removeEventListener("codereview:open-buy", onOpenBuy)
})

const clearQuery = (keys) => {
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
    if (typeof r.creditsRemaining === "number") authStore.setCredits(r.creditsRemaining)

    await new Promise(res => setTimeout(res, 400))
    router.push(`/analyses/${r.analysis.id}`)
}
</script>

<style lang="scss" scoped>
.balance-pill {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 8px 8px 8px 14px;
    border-radius: 999px;
    border: 1px solid rgba(139, 92, 246, 0.3);
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.08), rgba(236, 72, 153, 0.06));

    &--zero {
        border-color: rgba(245, 158, 11, 0.4);
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.08), transparent);
    }

    &__inner { line-height: 1.1; }
}

.empty-credits {
    .big-icon {
        width: 80px; height: 80px;
        border-radius: 22px;
        margin: 0 auto;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(236, 72, 153, 0.15));
        border: 1px solid rgba(139, 92, 246, 0.3);
    }
}

.seg {
    display: inline-flex;
    padding: 4px;
    border-radius: 999px;
    border: 1px solid rgba(150, 150, 160, 0.18);
    background: rgba(150, 150, 160, 0.04);

    &__btn {
        appearance: none;
        background: transparent;
        border: none;
        color: rgb(var(--v-theme-on-surface));
        opacity: 0.6;
        font-size: 13px;
        font-weight: 600;
        padding: 8px 16px;
        border-radius: 999px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        transition: all .2s ease;

        &:hover { opacity: 0.9; }

        &--active {
            opacity: 1;
            background: linear-gradient(135deg, #8B5CF6 0%, #EC4899 100%);
            color: #fff;
            box-shadow: 0 6px 16px -6px rgba(139, 92, 246, 0.6);
        }
    }
}

.connect-cta {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    border-radius: 14px;
    border: 1px dashed rgba(139, 92, 246, 0.3);
    background: rgba(139, 92, 246, 0.04);

    > .v-icon { flex-shrink: 0; }
}

.connected-bar {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), transparent) !important;
}

.repos-card {
    overflow-y: auto;
    max-height: 360px;
}

.vibe-cta {
    background: linear-gradient(135deg, #8B5CF6 0%, #EC4899 100%) !important;
    color: #fff !important;
    font-weight: 600 !important;
    box-shadow: 0 12px 28px -10px rgba(139, 92, 246, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.1) inset !important;
    &:hover { filter: brightness(1.07); }
    &:disabled { background: rgba(150, 150, 160, 0.2) !important; color: rgba(150, 150, 160, 0.5) !important; box-shadow: none !important; }
}
</style>
