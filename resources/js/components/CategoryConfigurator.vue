<template>
    <div>
        <div v-if="loading" class="d-flex justify-center py-10">
            <VProgressCircular indeterminate color="primary" />
        </div>

        <VAlert v-else-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>

        <template v-else>
            <!-- Tier ribbon -->
            <div class="tier-ribbon mb-6">
                <div
                    v-for="(tier, i) in tierBadges"
                    :key="i"
                    class="tier-pill"
                    :class="{
                        'tier-pill--active': tier.active,
                        'tier-pill--current': tier.current,
                    }"
                >
                    <div class="tier-pill__label">
                        <span v-if="tier.star" class="me-1">⭐</span>{{ tier.label }}
                    </div>
                    <div class="tier-pill__hint">{{ tier.hint }}</div>
                </div>
            </div>

            <VRow>
                <VCol cols="12" lg="8">
                    <VRow>
                        <VCol
                            v-for="cat in catalog.categories"
                            :key="cat.key"
                            cols="12" md="6"
                        >
                            <VCard
                                variant="outlined"
                                class="h-100 cursor-pointer category-card"
                                :class="{ 'category-card--active': isSelected(cat.key) }"
                                @click="toggle(cat.key)"
                            >
                                <VCardText class="pa-5">
                                    <div class="d-flex align-start justify-space-between mb-3 ga-3">
                                        <div class="d-flex align-center ga-3">
                                            <div class="category-icon">
                                                <VIcon :icon="iconFor(cat.key)" size="22" color="primary" />
                                            </div>
                                        </div>
                                        <div
                                            class="check-box"
                                            :class="{ 'check-box--on': isSelected(cat.key) }"
                                        >
                                            <VIcon v-if="isSelected(cat.key)" icon="tabler-check" size="14" color="white" />
                                        </div>
                                    </div>

                                    <div class="d-flex align-baseline justify-space-between mb-1 ga-2">
                                        <h3 class="text-subtitle-1 font-weight-bold">{{ cat.name }}</h3>
                                        <span class="text-h6 font-weight-bold text-primary">+${{ cat.priceCents / 100 }}</span>
                                    </div>

                                    <p class="text-body-2 text-medium-emphasis mb-3">{{ cat.tagline }}</p>

                                    <ul class="checklist">
                                        <li v-for="(item, i) in cat.includes" :key="i" class="d-flex align-start ga-2 mb-1">
                                            <span class="dot" />
                                            <span class="text-caption text-medium-emphasis">{{ item }}</span>
                                        </li>
                                    </ul>
                                </VCardText>
                            </VCard>
                        </VCol>
                    </VRow>
                </VCol>

                <VCol cols="12" lg="4">
                    <VCard variant="outlined" class="summary-card">
                        <VCardText class="pa-6">
                            <div class="d-flex align-center justify-space-between mb-2">
                                <div class="text-caption font-weight-semibold text-uppercase text-primary">Your review</div>
                                <VChip
                                    v-if="discountPct > 0"
                                    size="x-small"
                                    color="primary"
                                    variant="flat"
                                    class="font-weight-bold gradient-chip"
                                >Save {{ discountPct }}%</VChip>
                            </div>

                            <div class="d-flex align-baseline ga-2 mb-1">
                                <span class="text-h3 font-weight-bold">${{ totalAfter }}</span>
                                <span
                                    v-if="discountPct > 0"
                                    class="text-h6 text-medium-emphasis text-decoration-line-through"
                                >${{ subtotal }}</span>
                            </div>
                            <p class="text-body-2 text-medium-emphasis mb-1">{{ summary }}</p>
                            <p
                                v-if="upsellHint"
                                class="text-caption font-weight-bold text-primary mb-4"
                            >
                                💡 {{ upsellHint }}
                            </p>
                            <div v-else class="mb-4" />

                            <VDivider class="mb-4" />

                            <div v-if="selected.length === 0" class="text-body-2 text-medium-emphasis mb-4">
                                Minimum order is ${{ minTotalDollars }}.
                            </div>

                            <div v-else class="mb-4">
                                <div
                                    v-for="cat in selectedCategories"
                                    :key="cat.key"
                                    class="d-flex justify-space-between text-body-2 mb-1"
                                >
                                    <span>{{ cat.name }}</span>
                                    <span class="font-weight-medium">${{ cat.priceCents / 100 }}</span>
                                </div>
                                <div
                                    v-if="discountPct > 0"
                                    class="d-flex justify-space-between text-body-2 mt-2 pt-2 discount-line"
                                >
                                    <span class="text-primary font-weight-semibold">Bundle discount ({{ discountPct }}%)</span>
                                    <span class="text-primary font-weight-semibold">−${{ savings }}</span>
                                </div>
                            </div>

                            <VAlert v-if="checkoutError" type="error" variant="tonal" density="compact" class="mb-4">
                                {{ checkoutError }}
                            </VAlert>

                            <VBtn
                                v-if="selected.length < catalog.categories.length"
                                block
                                variant="outlined"
                                color="primary"
                                rounded="pill"
                                size="default"
                                class="mb-3"
                                @click="selectAll"
                            >
                                ✨ Select all {{ catalog.categories.length }} — save 20%
                            </VBtn>

                            <VBtn
                                block
                                size="large"
                                rounded="pill"
                                color="primary"
                                :loading="purchasing"
                                :disabled="!canCheckout"
                                @click="handleCheckout"
                            >
                                <VIcon icon="tabler-lock" class="me-2" size="18" />
                                {{ buttonLabel }}
                            </VBtn>

                            <p class="text-caption text-center text-medium-emphasis mt-3">
                                Secure checkout via Stripe · One-time payment
                            </p>
                        </VCardText>
                    </VCard>
                </VCol>
            </VRow>
        </template>
    </div>
</template>

<script setup>
import { fetchCatalog, startCheckout } from "@/utils/codeCheck"

const props = defineProps({
    initialCategories: { type: Array, default: () => [] },
})

const catalog = ref({ categories: [], minTotalCents: 2000, bundleDiscountPct: {} })
const loading = ref(true)
const error = ref("")

const selected = ref([])
const purchasing = ref(false)
const checkoutError = ref("")

const ICONS = {
    security: "tabler-shield",
    database: "tabler-database",
    backend: "tabler-server",
    frontend: "tabler-code",
}
const iconFor = key => ICONS[key] || "tabler-circle"

const isSelected = key => selected.value.includes(key)
const toggle = key => {
    const i = selected.value.indexOf(key)
    if (i === -1) selected.value.push(key)
    else selected.value.splice(i, 1)
}
const selectAll = () => { selected.value = catalog.value.categories.map(c => c.key) }

const selectedCategories = computed(() =>
    catalog.value.categories.filter(c => selected.value.includes(c.key))
)

const subtotalCents = computed(() =>
    selectedCategories.value.reduce((sum, c) => sum + c.priceCents, 0)
)
const subtotal = computed(() => Math.round(subtotalCents.value / 100))

const discountPct = computed(() => catalog.value.bundleDiscountPct?.[selected.value.length] || 0)
const savings = computed(() => Math.round((subtotal.value * discountPct.value) / 100))
const totalAfter = computed(() => subtotal.value - savings.value)

const minTotalDollars = computed(() => (catalog.value.minTotalCents / 100).toFixed(0))

const summary = computed(() => {
    const n = selected.value.length
    if (n === 0) return "Pick at least one category to continue."
    return `${n} ${n === 1 ? "category" : "categories"} selected`
})

const upsellHint = computed(() => {
    const n = selected.value.length
    const total = catalog.value.categories.length
    if (n === 0 || n === total) return null
    if (n === 1) return "Add 1 more → unlock 10% off"
    if (n === 2) return "Add 1 more → bump to 15% off"
    if (n === 3) return "Add 1 more → maximum 20% off"
    return null
})

const canCheckout = computed(() =>
    selected.value.length > 0
    && subtotalCents.value >= catalog.value.minTotalCents
)

const buttonLabel = computed(() => {
    if (selected.value.length === 0) return "Select categories"
    if (subtotalCents.value < catalog.value.minTotalCents) return `Minimum $${minTotalDollars.value}`
    return `Get my review · $${totalAfter.value}`
})

const tierBadges = computed(() => {
    const total = catalog.value.categories.length
    const n = selected.value.length
    const arr = []
    for (let i = 1; i <= total; i++) {
        const pct = catalog.value.bundleDiscountPct?.[i] || 0
        arr.push({
            label: i === 1 ? "1 category" : `Save ${pct}%`,
            hint: i === 1 ? "Pick one" : `${i} categories`,
            star: i === total,
            active: n >= i,
            current: n === i,
        })
    }
    return arr
})

const handleCheckout = async () => {
    checkoutError.value = ""
    purchasing.value = true
    try {
        const r = await startCheckout(selected.value)
        window.location.href = r.url
    } catch (e) {
        checkoutError.value = e?.data?.message || e.message || "Could not start checkout."
        purchasing.value = false
    }
}

onMounted(async () => {
    try {
        const r = await fetchCatalog()
        catalog.value = r
        const valid = new Set(r.categories.map(c => c.key))
        const seed = (props.initialCategories || []).filter(k => valid.has(k))
        selected.value = seed.length ? seed : ["security"]
    } catch (e) {
        error.value = e?.data?.message || e.message
    } finally {
        loading.value = false
    }
})
</script>

<style lang="scss" scoped>
.tier-ribbon {
    display: flex;
    gap: 8px;
    padding: 8px;
    border-radius: 16px;
    background: rgba(139, 92, 246, 0.04);
    border: 1px solid rgba(139, 92, 246, 0.18);
    backdrop-filter: blur(10px);
}

.tier-pill {
    flex: 1;
    text-align: center;
    padding: 10px 12px;
    border-radius: 10px;
    transition: all .25s ease;
    color: rgb(var(--v-theme-on-surface));
    opacity: .55;

    &--active { opacity: 1; background: rgba(139, 92, 246, 0.1); }
    &--current {
        opacity: 1;
        background: linear-gradient(135deg, #8B5CF6 0%, #EC4899 100%);
        color: #fff;
        box-shadow: 0 8px 24px -8px rgba(139, 92, 246, .6);
        transform: scale(1.02);
    }

    &__label {
        font-size: 13px;
        font-weight: 800;
        letter-spacing: .2px;
    }
    &__hint {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .8px;
        margin-top: 2px;
        opacity: .8;
    }
}

.category-card {
    transition: border-color .2s, transform .2s;
    &:hover { transform: translateY(-2px); }
    &.category-card--active {
        border-color: rgb(var(--v-theme-primary)) !important;
        box-shadow: 0 0 0 1px rgb(var(--v-theme-primary)), 0 12px 32px -16px rgba(139, 92, 246, .5);
    }
}

.category-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, rgba(139, 92, 246, .15), rgba(236, 72, 153, .15));
    border: 1px solid rgba(139, 92, 246, .25);
}

.check-box {
    width: 24px; height: 24px;
    border-radius: 6px;
    border: 2px solid rgba(150, 150, 160, 0.4);
    display: grid;
    place-items: center;
    transition: all .2s ease;

    &--on {
        background: rgb(var(--v-theme-primary));
        border-color: rgb(var(--v-theme-primary));
    }
}

.checklist {
    list-style: none;
    padding: 0;
    margin: 0;
}

.dot {
    margin-top: 6px;
    width: 4px; height: 4px;
    border-radius: 50%;
    background: rgb(var(--v-theme-primary));
    flex-shrink: 0;
}

.summary-card {
    position: sticky;
    top: 24px;
}

.gradient-chip {
    background: linear-gradient(135deg, #8B5CF6 0%, #EC4899 100%) !important;
    color: #fff !important;
}

.discount-line {
    border-top: 1px dashed rgba(139, 92, 246, .35);
}
</style>
