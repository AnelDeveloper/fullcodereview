<template>
    <div>
        <!-- Header -->
        <div v-if="step !== 'analyzing'" class="d-flex align-center justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Run a code review</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Pick scopes from your inventory + a repo. Each scope you select consumes one slot.
                </p>
            </div>

            <VBtn color="primary" rounded="pill" prepend-icon="tabler-plus" @click="buyDialog = true">
                Buy scopes
            </VBtn>
        </div>

        <!-- Banners -->
        <VAlert v-if="returnBanner === 'success'" type="success" variant="tonal" closable class="mb-4" @click:close="returnBanner = null">
            Payment received — scopes added to your inventory. Pick what to review below.
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

        <template v-else>
            <!-- Empty inventory -->
            <VCard v-if="totalSections === 0" variant="outlined">
                <VCardText class="empty-state text-center py-12">
                    <div class="big-icon mb-4">
                        <VIcon icon="tabler-stack-2" size="40" color="primary" />
                    </div>
                    <h3 class="text-h6 font-weight-bold mb-2">No scopes in your inventory</h3>
                    <p class="text-body-2 text-medium-emphasis mb-5" style="max-width:480px;margin:0 auto;">
                        Buy your first scope to get started. Each scope you buy stays in your inventory until you use it on a review.
                    </p>
                    <VBtn color="primary" size="large" rounded="pill" prepend-icon="tabler-plus" @click="buyDialog = true">
                        Buy review scopes
                    </VBtn>
                </VCardText>
            </VCard>

            <VRow v-else>
                <!-- LEFT: Scope picker -->
                <VCol cols="12" md="5">
                    <VCard variant="outlined" class="h-100">
                        <VCardText class="pa-6">
                            <div class="d-flex align-center justify-space-between mb-3">
                                <h3 class="text-h6 font-weight-bold">Pick scopes</h3>
                                <span class="text-caption text-medium-emphasis">{{ pickedCount }} of {{ totalSections }} selected</span>
                            </div>
                            <p class="text-caption text-medium-emphasis mb-4">
                                Each one you pick consumes a slot from your inventory.
                            </p>

                            <div class="scope-list">
                                <button
                                    v-for="row in scopeRows"
                                    :key="row.key"
                                    type="button"
                                    :disabled="row.count === 0"
                                    :class="['scope-row', picked.includes(row.key) ? 'scope-row--picked' : '', row.count === 0 ? 'scope-row--disabled' : '']"
                                    @click="togglePick(row.key)"
                                >
                                    <div class="scope-row__icon" :style="{ background: `rgba(var(--v-theme-${row.color}), 0.12)`, color: `rgb(var(--v-theme-${row.color}))` }">
                                        <VIcon :icon="row.icon" size="20" />
                                    </div>
                                    <div class="scope-row__label">
                                        <div class="text-body-2 font-weight-semibold">{{ row.label }}</div>
                                        <div class="text-caption text-medium-emphasis">{{ row.tagline }}</div>
                                    </div>
                                    <div class="scope-row__count">
                                        <span v-if="row.count > 0" class="count-pill">× {{ row.count }}</span>
                                        <span v-else class="count-zero">none</span>
                                    </div>
                                    <div class="scope-row__check">
                                        <VIcon v-if="picked.includes(row.key)" icon="tabler-check" color="primary" size="20" />
                                    </div>
                                </button>
                            </div>
                        </VCardText>
                    </VCard>
                </VCol>

                <!-- RIGHT: Repo picker -->
                <VCol cols="12" md="7">
                    <VCard variant="outlined" class="h-100">
                        <VCardText class="pa-6">
                            <div class="d-flex align-center justify-space-between flex-wrap ga-3 mb-4">
                                <h3 class="text-h6 font-weight-bold">Pick a repository</h3>
                                <div class="seg">
                                    <button type="button" :class="['seg__btn', repoSource === 'connected' ? 'seg__btn--active' : '']" @click="repoSource = 'connected'">
                                        <VIcon icon="tabler-brand-github" size="14" class="me-1" />
                                        My GitHub
                                    </button>
                                    <button type="button" :class="['seg__btn', repoSource === 'public' ? 'seg__btn--active' : '']" @click="repoSource = 'public'">
                                        <VIcon icon="tabler-link" size="14" class="me-1" />
                                        Public URL
                                    </button>
                                </div>
                            </div>

                            <div v-if="repoSource === 'connected'">
                                <div v-if="!githubConnected" class="connect-cta">
                                    <VIcon icon="tabler-brand-github" size="36" />
                                    <div class="flex-1-1">
                                        <div class="text-body-1 font-weight-semibold mb-1">Connect your GitHub account</div>
                                        <p class="text-caption text-medium-emphasis mb-0">Read-only access. Token is removed when the analysis finishes.</p>
                                    </div>
                                    <VBtn color="primary" rounded="pill" prepend-icon="tabler-plug-connected" @click="handleConnectGithub">Connect</VBtn>
                                </div>

                                <div v-else>
                                    <VCard variant="outlined" class="d-flex align-center justify-space-between pa-3 mb-3 connected-bar">
                                        <div class="d-flex align-center ga-3">
                                            <VAvatar size="32" :image="githubAvatar" />
                                            <div>
                                                <div class="text-caption text-medium-emphasis">Connected as</div>
                                                <div class="text-body-2 font-weight-semibold">@{{ githubLogin }}</div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-center ga-2">
                                            <VIcon icon="tabler-circle-check" color="success" />
                                            <VBtn
                                                size="small"
                                                variant="text"
                                                color="error"
                                                :loading="disconnecting"
                                                @click="handleDisconnectGithub"
                                            >
                                                Disconnect
                                            </VBtn>
                                        </div>
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
                                                    <template v-if="selectedRepo?.fullName === r.fullName" #append>
                                                        <VIcon icon="tabler-circle-check" color="primary" />
                                                    </template>
                                                </VListItem>
                                            </VList>
                                        </VCard>
                                    </template>
                                </div>
                            </div>

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
                        </VCardText>
                    </VCard>
                </VCol>
            </VRow>
        </template>

        <!-- BUY DIALOG -->
        <VDialog v-model="buyDialog" max-width="1100" scrollable>
            <VCard>
                <VCardTitle class="d-flex align-center justify-space-between pa-6 pb-2">
                    <div>
                        <div class="text-h5 font-weight-bold">Buy review scopes</div>
                        <div class="text-body-2 text-medium-emphasis">Each category you buy adds one slot to your inventory.</div>
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
    syncLemonOrders,
    disconnectGithub,
} from "@/utils/codeCheck"
import CategoryConfigurator from "@/components/CategoryConfigurator.vue"
import { useAuthStore } from "@/stores/auth"

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const SCOPE_META = {
    security: { label: "Security", icon: "tabler-shield", color: "error", tagline: "Auth, injection, secrets" },
    database: { label: "Database", icon: "tabler-database", color: "warning", tagline: "N+1, indexes, migrations" },
    backend: { label: "Backend", icon: "tabler-server", color: "info", tagline: "APIs, headers, RBAC" },
    frontend: { label: "Frontend", icon: "tabler-code", color: "primary", tagline: "XSS, perf, state" },
}

const step = ref("home")
const returnBanner = ref(null)
const buyDialog = ref(false)

const picked = ref([])
const repoSource = ref("connected")
const repoUrl = ref("")
const repoFilter = ref("")
const repos = ref([])
const loadingRepos = ref(false)
const reposError = ref("")
const githubConnected = ref(false)
const githubLogin = ref(null)
const githubAvatar = ref(null)
const disconnecting = ref(false)
const selectedRepo = ref(null)
const oauthError = ref("")

const analysisError = ref("")
const progress = ref(0)
const progressLabel = ref("")

const totalSections = computed(() => authStore.sectionsTotal)
const pickedCount = computed(() => picked.value.length)

const scopeRows = computed(() =>
    Object.keys(SCOPE_META).map(k => ({
        key: k,
        ...SCOPE_META[k],
        count: authStore.sections[k] || 0,
    }))
)

const togglePick = (key) => {
    const i = picked.value.indexOf(key)
    if (i >= 0) picked.value.splice(i, 1)
    else picked.value.push(key)
}

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

const repoChosen = computed(() => {
    if (repoSource.value === "connected") return !!selectedRepo.value
    return /^https?:\/\/(www\.)?github\.com\/[^/]+\/[^/]+/i.test(repoUrl.value.trim())
})

const canRun = computed(() => picked.value.length > 0 && repoChosen.value)

const runButtonLabel = computed(() => {
    if (picked.value.length === 0) return "Pick at least one scope"
    if (!repoChosen.value) return repoSource.value === "connected" ? "Pick a repository" : "Paste a GitHub URL"
    return `Run review · ${picked.value.length} ${picked.value.length === 1 ? "scope" : "scopes"}`
})

const onOpenBuy = () => { buyDialog.value = true }

onMounted(async () => {
    window.addEventListener("codereview:open-buy", onOpenBuy)

    const lemonSuccess = route.query.lemon_success
    const ghConnected = route.query.gh_connected
    const ghError = route.query.gh_error
    const buy = route.query.buy

    if (buy) { buyDialog.value = true; clearQuery(["buy"]) }
    if (ghError) { oauthError.value = decodeURIComponent(ghError); clearQuery(["gh_error"]) }
    if (ghConnected) { clearQuery(["gh_connected"]); await loadGithubRepos() }
    if (lemonSuccess) {
        // Try the LS webhook landing first; fall back to a direct sync
        // (fetches the user's recent LS orders by email and creates any
        // missing slots). This handles delayed/dropped webhooks.
        try { await syncLemonOrders() } catch { /* sync is best-effort */ }
        await authStore.refreshCredits()

        // If the sync raced ahead of the webhook, give the webhook one more chance
        if (authStore.sectionsTotal === 0) {
            for (let i = 0; i < 4; i++) {
                await new Promise(r => setTimeout(r, 1200))
                try { await syncLemonOrders() } catch { /* ignore */ }
                await authStore.refreshCredits()
                if (authStore.sectionsTotal > 0) break
            }
        }

        returnBanner.value = "success"
        clearQuery(["lemon_success"])

        // Default-pick all categories the user now has
        if (picked.value.length === 0) {
            picked.value = [...authStore.availableCategories]
        }
    }

    // Default-pick all available categories so the user gets a sensible default
    if (picked.value.length === 0) {
        picked.value = [...authStore.availableCategories]
    }

    if (authStore.user?.githubLogin) await loadGithubRepos({ silent: true })
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
        if (e?.data?.code === "not_connected") githubConnected.value = false
        else if (!silent) reposError.value = e?.data?.message || e.message
    } finally {
        loadingRepos.value = false
    }
}

const handleConnectGithub = () => {
    oauthError.value = ""
    window.location.href = githubLoginUrl()
}

const handleDisconnectGithub = async () => {
    if (! confirm("Disconnect GitHub from QodeShark? You'll need to reconnect to scan private repos.")) return
    disconnecting.value = true
    oauthError.value = ""
    try {
        await disconnectGithub()
        // Clear local state immediately
        githubConnected.value = false
        githubLogin.value = null
        githubAvatar.value = null
        repos.value = []
        selectedRepo.value = null
        // Refresh user object so authStore.user.githubLogin is null
        await authStore.fetchMe()
    } catch (e) {
        oauthError.value = e?.data?.message || e.message || "Could not disconnect."
    } finally {
        disconnecting.value = false
    }
}

const handleAnalyze = async () => {
    analysisError.value = ""
    let opts = { categories: [...picked.value] }
    if (repoSource.value === "connected") {
        if (!selectedRepo.value) { analysisError.value = "Pick a repository to analyze."; return }
        opts.repoFullName = selectedRepo.value.fullName
    } else {
        if (!/^https?:\/\/(www\.)?github\.com\/[^/]+\/[^/]+/i.test(repoUrl.value.trim())) {
            analysisError.value = "Expected URL like https://github.com/owner/repo"
            return
        }
        opts.repoUrl = repoUrl.value.trim()
    }

    step.value = "analyzing"
    progress.value = 0

    const intro = [
        ["Reserving scopes…", 8, 400],
        ["Fetching repository metadata…", 18, 700],
        ["Reading source files…", 32, 900],
        ["Mapping the stack…", 45, 800],
        ["Running selected checks…", 58, 900],
        ["Analyzing patterns…", 70, 800],
    ]
    const waitLabels = [
        "Reviewing with AI…",
        "Auditing access patterns…",
        "Checking for known footguns…",
        "Synthesizing findings…",
        "Cross-checking severity…",
        "Almost done…",
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
    if (r.sectionsRemaining) authStore.setSections(r.sectionsRemaining)

    await new Promise(res => setTimeout(res, 400))
    router.push(`/analyses/${r.analysis.id}`)
}
</script>

<style lang="scss" scoped>
.empty-state {
    .big-icon {
        width: 72px; height: 72px;
        border-radius: 16px;
        margin: 0 auto;
        display: grid; place-items: center;
        background: rgba(124, 58, 237, 0.1);
        border: 1px solid rgba(124, 58, 237, 0.25);
    }
}

.scope-list { display: flex; flex-direction: column; gap: 8px; }

.scope-row {
    appearance: none;
    background: transparent;
    border: 1px solid rgba(150, 150, 160, 0.18);
    border-radius: 12px;
    padding: 12px 14px;
    width: 100%;
    text-align: left;
    cursor: pointer;
    display: grid;
    grid-template-columns: auto 1fr auto auto;
    gap: 12px;
    align-items: center;
    color: rgb(var(--v-theme-on-surface));
    transition: all .18s ease;

    &:hover:not(:disabled) { border-color: rgb(var(--v-theme-primary)); }

    &--picked {
        border-color: rgb(var(--v-theme-primary)) !important;
        background: rgba(124, 58, 237, 0.06);
        box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
    }

    &--disabled { opacity: 0.5; cursor: not-allowed; }

    &__icon {
        width: 38px; height: 38px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
    }

    &__count { min-width: 60px; text-align: right; }
    &__check { width: 20px; }
}

.count-pill {
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    border-radius: 999px;
    background: rgba(139, 92, 246, 0.15);
    color: rgb(var(--v-theme-primary));
    font-size: 12px;
    font-weight: 700;
}

.count-zero {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.5;
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
        padding: 8px 14px;
        border-radius: 999px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        transition: all .2s ease;

        &:hover { opacity: 0.9; }

        &--active {
            opacity: 1;
            background: #7C3AED;
            color: #fff;
        }
    }
}

.connect-cta {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px;
    border-radius: 14px;
    border: 1px dashed rgba(124, 58, 237, 0.3);
    background: rgba(124, 58, 237, 0.04);

    > .v-icon { flex-shrink: 0; }
}

.connected-bar { background: rgba(16, 185, 129, 0.05) !important; }

.repos-card {
    overflow-y: auto;
    max-height: 340px;
}

.vibe-cta {
    background: #7C3AED !important;
    color: #fff !important;
    font-weight: 600 !important;
    &:hover { background: #6D28D9 !important; }
    &:disabled { background: rgba(150, 150, 160, 0.2) !important; color: rgba(150, 150, 160, 0.5) !important; }
}
</style>
