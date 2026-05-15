<template>
    <div v-if="store.id" class="run-banner-wrap">
        <VCard
            variant="flat"
            :class="['run-banner', store.isFailed ? 'run-banner--error' : '']"
            @click="onClick"
        >
            <div class="run-banner__icon">
                <VProgressCircular v-if="store.isActive" indeterminate size="20" width="2" color="primary" />
                <VIcon v-else-if="store.isDone" icon="tabler-circle-check" color="success" />
                <VIcon v-else icon="tabler-alert-triangle" color="error" />
            </div>

            <div class="run-banner__body">
                <div class="d-flex align-center ga-2">
                    <span class="text-body-2 font-weight-semibold">{{ headline }}</span>
                    <span v-if="store.repoFullName" class="text-caption text-medium-emphasis font-mono text-truncate">
                        {{ store.repoFullName }}
                    </span>
                </div>
                <VProgressLinear
                    v-if="store.isActive"
                    :model-value="store.progress"
                    color="primary"
                    rounded
                    height="3"
                    class="mt-1"
                />
                <div v-if="store.isActive" class="text-caption text-medium-emphasis mt-1">{{ store.label }}</div>
                <div v-else-if="store.isFailed" class="text-caption text-error mt-1">{{ store.errorMessage || "Audit failed." }}</div>
                <div v-else-if="store.isDone" class="text-caption text-medium-emphasis mt-1">Click to view your report.</div>
            </div>

            <div class="run-banner__actions">
                <VBtn
                    v-if="store.isDone"
                    color="primary"
                    rounded="pill"
                    size="small"
                    @click.stop="onView"
                >
                    View report
                </VBtn>
                <VBtn
                    v-if="store.isActive"
                    color="error"
                    variant="text"
                    size="small"
                    :loading="cancelling"
                    @click.stop="onCancel"
                >
                    Cancel
                </VBtn>
                <IconBtn v-if="!store.isActive" size="small" @click.stop="onDismiss">
                    <VIcon size="18" icon="tabler-x" />
                </IconBtn>
            </div>
        </VCard>
    </div>
</template>

<script setup>
import { useAnalysisRunStore } from "@/stores/analysisRun"
import { onMounted } from "vue"

const store = useAnalysisRunStore()
const router = useRouter()
const route = useRoute()
const cancelling = ref(false)

const headline = computed(() => {
    if (store.isActive) return "Auditing your code…"
    if (store.isDone) return "Audit ready"
    if (store.isFailed) return "Audit failed"
    return ""
})

const onClick = () => {
    if (store.isDone || store.isFailed) onView()
}

const onView = () => {
    const id = store.id
    if (!id) return
    // Clearing on view so a user who lands on the report doesn't keep seeing
    // a "ready" banner on every page they visit afterward.
    store.clear()
    router.push(`/analyses/${id}`)
}

const onDismiss = () => {
    // X is only rendered for finished/failed runs (cancel button replaces it
    // while active), so this is a pure UI hide — no backend call needed.
    store.clear()
}

const onCancel = async () => {
    if (!confirm("Cancel this audit? Your credits will be refunded.")) return
    cancelling.value = true
    try { await store.cancel() }
    catch (e) { alert(e?.data?.message || e?.message || "Could not cancel.") }
    finally { cancelling.value = false }
}

onMounted(() => {
    store.resumeIfActive()

    window.addEventListener("analysisRun:finished", (e) => {
        // If the user is already viewing the finished analysis, clear the banner
        // automatically so it doesn't sit there permanently.
        const id = e?.detail?.id
        if (id && route.path === `/analyses/${id}`) store.clear()
    })
})
</script>

<style lang="scss" scoped>
.run-banner-wrap {
    position: fixed;
    bottom: 16px;
    right: 16px;
    z-index: 1500;
    max-width: 420px;
    width: calc(100vw - 32px);
}

.run-banner {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 12px;
    align-items: center;
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgb(var(--v-theme-surface));
    box-shadow: 0 10px 32px rgba(0, 0, 0, 0.18);
    cursor: pointer;
    transition: transform .15s ease;

    &:hover { transform: translateY(-1px); }
    &--error { border-color: rgb(var(--v-theme-error)); }

    &__icon {
        width: 28px;
        height: 28px;
        display: grid;
        place-items: center;
    }

    &__body { min-width: 0; }
    &__body .font-mono { max-width: 200px; }

    &__actions {
        display: flex;
        align-items: center;
        gap: 4px;
    }
}
</style>
