<template>
    <div>
        <div class="d-flex align-center justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold">Book a review call</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Pick a 30-minute slot with Anel — we'll walk through your audit, prioritize fixes, and sign off on production readiness.
                </p>
            </div>
            <VBtn variant="text" prepend-icon="tabler-arrow-left" to="/">Back to dashboard</VBtn>
        </div>

        <VCard variant="outlined" class="cal-card">
            <div v-if="loading" class="d-flex flex-column align-center justify-center pa-10">
                <VProgressCircular indeterminate color="primary" class="mb-3" />
                <p class="text-body-2 text-medium-emphasis">Loading calendar…</p>
            </div>
            <!-- Cal.com auto-resizing inline embed — height adjusts to content,
                 so the booking widget never gets its own scrollbar (which is
                 what was causing the 'Overlay my calendar' banner to collide
                 with the 12h/24h toggle). -->
            <div ref="calMount" :style="{ minHeight: loading ? '0' : '640px' }" />
        </VCard>

        <p class="text-caption text-medium-emphasis text-center mt-4">
            Trouble loading?
            <a :href="fallbackUrl" target="_blank" rel="noopener noreferrer" class="text-primary">Open the booking page in a new tab</a>.
        </p>
    </div>
</template>

<script setup>
import { useTheme } from "vuetify"

const CAL_LINK = "anel-kujovic-azkffj/30min"
const NAMESPACE = "book-call"
const fallbackUrl = `https://cal.com/${CAL_LINK}`

const calMount = ref(null)
const loading = ref(true)
const vuetifyTheme = useTheme()

let scriptEl = null
let mounted = false

const loadCalScript = () => new Promise((resolve) => {
    if (window.Cal) return resolve()
    scriptEl = document.createElement("script")
    scriptEl.src = "https://app.cal.com/embed/embed.js"
    scriptEl.async = true
    scriptEl.onload = resolve
    document.head.appendChild(scriptEl)
})

const initCal = () => {
    if (mounted || !calMount.value) return
    mounted = true

    // Cal.com bootstrap snippet (from their inline-embed docs), wrapped so we
    // can call it from Vue. Uses a namespace so multiple cal instances on the
    // page wouldn't collide.
    const C = window
    C.Cal = C.Cal || function () {
        const cal = C.Cal
        const ar = arguments
        if (!cal.loaded) {
            cal.ns = {}
            cal.q = cal.q || []
            cal.loaded = true
        }
        if (ar[0] === "init") {
            const api = function () { (api.q = api.q || []).push(arguments) }
            const namespace = ar[1]
            if (typeof namespace === "string") {
                cal.ns[namespace] = cal.ns[namespace] || api
                cal.ns[namespace].q = cal.ns[namespace].q || []
                cal.ns[namespace].q.push(ar)
                cal.q.push(["initNamespace", namespace])
            } else {
                cal.q.push(ar)
            }
            return
        }
        cal.q.push(ar)
    }

    window.Cal("init", NAMESPACE, { origin: "https://cal.com" })

    window.Cal.ns[NAMESPACE]("inline", {
        elementOrSelector: calMount.value,
        calLink: CAL_LINK,
        config: { layout: "month_view" },
    })

    window.Cal.ns[NAMESPACE]("ui", {
        hideEventTypeDetails: false,
        layout: "month_view",
        theme: vuetifyTheme.global.current.value.dark ? "dark" : "light",
    })

    // Cal.com's iframe is injected synchronously into our mount node, but the
    // calendar inside loads async. Give it a beat before clearing the spinner.
    setTimeout(() => { loading.value = false }, 800)
}

onMounted(async () => {
    await loadCalScript()
    initCal()
})

onUnmounted(() => {
    // Leave the script tag in place (cheap, may be reused) but clear our mount
    // so the next visit re-initializes against a fresh node.
    if (calMount.value) calMount.value.innerHTML = ""
})
</script>

<style lang="scss" scoped>
.cal-card {
    border-radius: 16px;
    overflow: hidden;
    padding: 8px;

    :deep(iframe) {
        width: 100% !important;
        border: 0;
        display: block;
    }
}
</style>
