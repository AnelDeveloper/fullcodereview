<template>
    <div>
        <!-- Header -->
        <div class="d-flex align-center justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Run a code audit</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Pick credits from your inventory + a repo. Each credit you select consumes one slot.
                </p>
            </div>

            <VBtn color="primary" rounded="pill" prepend-icon="tabler-plus" @click="buyDialog = true">
                Buy credits
            </VBtn>
        </div>

        <!-- Banners -->
        <VAlert v-if="returnBanner === 'success'" type="success" variant="tonal" closable class="mb-4" @click:close="returnBanner = null">
            Payment received — credits added to your inventory. Pick what to audit below.
        </VAlert>
        <VAlert v-if="oauthError" type="error" variant="tonal" closable class="mb-4" @click:close="oauthError = ''">
            GitHub connection failed: {{ oauthError }}
        </VAlert>

        <!-- Active audit takeover: no second run while one is in flight -->
        <VCard v-if="analysisRun.isActive" variant="outlined" class="text-center">
            <VCardText class="pa-10">
                <div class="d-flex justify-center mb-6">
                    <VProgressCircular indeterminate size="56" width="4" color="primary" />
                </div>
                <h3 class="text-h5 font-weight-bold mb-2">An audit is already running</h3>
                <p v-if="analysisRun.repoFullName" class="text-body-2 text-medium-emphasis font-mono mb-4 text-truncate">
                    {{ analysisRun.repoFullName }}
                </p>
                <p class="text-body-2 text-medium-emphasis mb-6" style="max-width:480px;margin:0 auto;">
                    Only one audit can run at a time. You can wait here, view its progress on the
                    report page, or carry on with other work — we'll notify you when it's done.
                </p>
                <div class="d-flex justify-center flex-wrap ga-3">
                    <VBtn
                        v-if="analysisRun.id"
                        color="primary"
                        rounded="pill"
                        prepend-icon="tabler-eye"
                        :to="`/analyses/${analysisRun.id}`"
                    >
                        View current audit
                    </VBtn>
                    <VBtn
                        color="error"
                        variant="outlined"
                        rounded="pill"
                        prepend-icon="tabler-x"
                        :loading="cancelling"
                        @click="onCancelAudit"
                    >
                        Cancel audit
                    </VBtn>
                </div>
            </VCardText>
        </VCard>

        <template v-else>
        <!-- Empty inventory -->
            <VCard v-if="totalSections === 0" variant="outlined">
                <VCardText class="empty-state text-center py-12">
                    <div class="big-icon mb-4">
                        <VIcon icon="tabler-credit-card" size="40" color="primary" />
                    </div>
                    <h3 class="text-h6 font-weight-bold mb-2">No credits in your inventory</h3>
                    <p class="text-body-2 text-medium-emphasis mb-5" style="max-width:480px;margin:0 auto;">
                        Buy your first credit to get started. Each credit you buy stays in your inventory until you use it on an audit.
                    </p>
                    <VBtn color="primary" size="large" rounded="pill" prepend-icon="tabler-plus" @click="buyDialog = true">
                        Buy audit credits
                    </VBtn>
                </VCardText>
            </VCard>

            <VRow v-else>
                <!-- LEFT: Scope picker -->
                <VCol cols="12" md="5">
                    <VCard variant="outlined" class="h-100">
                        <VCardText class="pa-6">
                            <div class="d-flex align-center justify-space-between mb-3">
                                <h3 class="text-h6 font-weight-bold">Pick credits</h3>
                                <span class="text-caption text-medium-emphasis">{{ pickedCount }} of {{ scopeRows.length }} selected</span>
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
                        <div class="text-h5 font-weight-bold">Buy audit credits</div>
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
    fetchUserRepos,
    githubLoginUrl,
    syncStripeOrders,
    disconnectGithub,
} from "@/utils/codeCheck"
import CategoryConfigurator from "@/components/CategoryConfigurator.vue"
import { useAuthStore } from "@/stores/auth"
import { useAnalysisRunStore } from "@/stores/analysisRun"

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const analysisRun = useAnalysisRunStore()

const SCOPE_META = {
    security: { label: "Security", icon: "tabler-shield", color: "error", tagline: "Auth, injection, secrets" },
    database: { label: "Database", icon: "tabler-database", color: "warning", tagline: "N+1, indexes, migrations" },
    backend: { label: "Backend", icon: "tabler-server", color: "info", tagline: "APIs, headers, RBAC" },
    frontend: { label: "Frontend", icon: "tabler-code", color: "primary", tagline: "XSS, perf, state" },
}

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
const cancelling = ref(false)

const onCancelAudit = async () => {
    if (!confirm("Cancel this audit? Your credits will be refunded.")) return
    cancelling.value = true
    try { await analysisRun.cancel() }
    catch (e) { analysisError.value = e?.data?.message || e?.message || "Could not cancel." }
    finally { cancelling.value = false }
}

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

const repoChosen = computed(() => {
    if (repoSource.value === "connected") return !!selectedRepo.value
    return /^https?:\/\/(www\.)?github\.com\/[^/]+\/[^/]+/i.test(repoUrl.value.trim())
})

const canRun = computed(() => picked.value.length > 0 && repoChosen.value)

const runButtonLabel = computed(() => {
    if (picked.value.length === 0) return "Pick at least one credit"
    if (!repoChosen.value) return repoSource.value === "connected" ? "Pick a repository" : "Paste a GitHub URL"
    return `Run audit · ${picked.value.length} ${picked.value.length === 1 ? "credit" : "credits"}`
})

const onOpenBuy = () => { buyDialog.value = true }

onMounted(async () => {
    window.addEventListener("codereview:open-buy", onOpenBuy)

    const stripeSuccess = route.query.stripe_success
    const stripeSessionId = route.query.session_id
    const ghConnected = route.query.gh_connected
    const ghError = route.query.gh_error
    const buy = route.query.buy

    if (buy) { buyDialog.value = true; clearQuery(["buy"]) }
    if (ghError) { oauthError.value = decodeURIComponent(ghError); clearQuery(["gh_error"]) }
    if (ghConnected) { clearQuery(["gh_connected"]); await loadGithubRepos() }
    if (stripeSuccess) {
        // Sync the specific Stripe session we just returned from. The
        // webhook may already have fired and credited the slots; sync is
        // idempotent and handles the "webhook delayed/dropped" case too.
        try { await syncStripeOrders(stripeSessionId) } catch { /* sync is best-effort */ }
        await authStore.refreshCredits()

        // If the webhook still hasn't landed, retry the same session a
        // few times — Stripe's `paid` flag may take a moment to flip.
        if (authStore.sectionsTotal === 0) {
            for (let i = 0; i < 4; i++) {
                await new Promise(r => setTimeout(r, 1200))
                try { await syncStripeOrders(stripeSessionId) } catch { /* ignore */ }
                await authStore.refreshCredits()
                if (authStore.sectionsTotal > 0) break
            }
        }

        returnBanner.value = "success"
        clearQuery(["stripe_success", "session_id"])

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

    try {
        const analysis = await analysisRun.start(opts)
        // Land on the analysis detail page; it handles the pending/running
        // states and watches the store. The global banner follows the user
        // wherever they navigate.
        router.push(`/analyses/${analysis.id}`)
    } catch (e) {
        analysisError.value = e?.data?.message || e?.message || "Could not start audit."
    }
}
</script>

<style lang="scss" scoped>
.empty-state {
    .big-icon {
        width: 72px; height: 72px;
        border-radius: 16px;
        margin: 0 auto;
        display: grid; place-items: center;
        background: rgba(var(--v-theme-on-surface), 0.06);
        border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    }
}

.scope-list { display: flex; flex-direction: column; gap: 8px; }

.scope-row {
    appearance: none;
    background: transparent;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
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

    &:hover:not(:disabled) { border-color: rgba(var(--v-theme-on-surface), 0.4); }

    &--picked {
        border-color: rgb(var(--v-theme-on-surface)) !important;
        background: rgba(var(--v-theme-on-surface), 0.04);
        box-shadow: 0 0 0 1px rgba(var(--v-theme-on-surface), 0.5);
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
    background: rgba(var(--v-theme-on-surface), 0.1);
    color: rgb(var(--v-theme-on-surface));
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
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgba(var(--v-theme-on-surface), 0.03);

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
            background: rgb(var(--v-theme-on-surface));
            color: rgb(var(--v-theme-surface));
        }
    }
}

.connect-cta {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px;
    border-radius: 14px;
    border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgba(var(--v-theme-on-surface), 0.03);

    > .v-icon { flex-shrink: 0; }
}

.connected-bar { background: rgba(16, 185, 129, 0.05) !important; }

.repos-card {
    overflow-y: auto;
    max-height: 340px;
}

// White-on-black in dark, black-on-white in light — matches landing page btn-vibe.
.vibe-cta {
    background: rgb(var(--v-theme-on-surface)) !important;
    color: rgb(var(--v-theme-surface)) !important;
    font-weight: 600 !important;
    &:hover { background: rgba(var(--v-theme-on-surface), 0.85) !important; }
    &:disabled {
        background: rgba(var(--v-theme-on-surface), 0.15) !important;
        color: rgba(var(--v-theme-on-surface), 0.4) !important;
    }
}
</style>
