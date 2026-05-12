<template>
    <VMenu offset="8" :close-on-content-click="false">
        <template #activator="{ props: activator }">
            <div v-bind="activator" class="credits-pill d-inline-flex align-center ga-2 px-3 py-1.5 rounded-pill cursor-pointer">
                <VIcon icon="tabler-stack-2" size="18" />
                <span class="text-body-2 font-weight-semibold">{{ total }}</span>
                <span class="text-caption d-none d-sm-inline">{{ total === 1 ? "scope" : "scopes" }}</span>
            </div>
        </template>

        <VCard min-width="260" variant="elevated">
            <VCardText class="pa-4">
                <div class="text-caption text-medium-emphasis font-weight-semibold text-uppercase mb-2 tracking-wide">
                    Available scopes
                </div>

                <div v-if="total === 0" class="text-center py-4">
                    <p class="text-body-2 text-medium-emphasis mb-3">No scopes yet.</p>
                    <VBtn color="primary" rounded="pill" size="small" @click="goBuy">Buy your first scope</VBtn>
                </div>

                <template v-else>
                    <div class="section-row" v-for="row in rows" :key="row.key">
                        <VIcon :icon="row.icon" :color="row.color" size="18" />
                        <span class="text-body-2 flex-1-1">{{ row.label }}</span>
                        <span class="text-body-2 font-weight-bold" :style="{ color: row.count > 0 ? `rgb(var(--v-theme-${row.color}))` : undefined, opacity: row.count > 0 ? 1 : 0.4 }">
                            × {{ row.count }}
                        </span>
                    </div>

                    <VDivider class="my-3" />
                    <VBtn block color="primary" rounded="pill" size="small" @click="goBuy">Buy more scopes</VBtn>
                </template>
            </VCardText>
        </VCard>
    </VMenu>
</template>

<script setup>
import { useAuthStore } from "@/stores/auth"

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const total = computed(() => authStore.sectionsTotal)

const ROW_META = {
    security: { label: "Security", icon: "tabler-shield", color: "error" },
    database: { label: "Database", icon: "tabler-database", color: "warning" },
    backend: { label: "Backend", icon: "tabler-server", color: "info" },
    frontend: { label: "Frontend", icon: "tabler-code", color: "primary" },
}

const rows = computed(() =>
    Object.keys(ROW_META).map(k => ({
        key: k,
        ...ROW_META[k],
        count: authStore.sections[k] || 0,
    }))
)

const goBuy = () => {
    if (route.path === "/review") {
        window.dispatchEvent(new CustomEvent("codereview:open-buy"))
    } else {
        router.push("/review?buy=1")
    }
}
</script>

<style lang="scss" scoped>
.credits-pill {
    background: rgba(var(--v-theme-on-surface), 0.06);
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    color: rgb(var(--v-theme-on-surface));
    transition: border-color .2s ease;
    user-select: none;
    &:hover {
        border-color: rgba(var(--v-theme-on-surface), 0.4);
    }
}

.section-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 4px;
}

.tracking-wide { letter-spacing: 1px; }
</style>
