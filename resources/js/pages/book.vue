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
            <!-- Cal.com auto-resizing inline embed — height adjusts to content,
                 so the booking widget never gets its own scrollbar (which is
                 what caused the 'Overlay my calendar' banner to collide with
                 the 12h/24h toggle). -->
            <div ref="calMount" class="cal-mount" />
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
const vuetifyTheme = useTheme()

onMounted(() => {
    if (!calMount.value) return

    // Official Cal.com inline-embed bootstrap. The IIFE installs a stub on
    // window.Cal that (a) queues calls and (b) injects embed.js the first
    // time it's invoked; embed.js then drains the queue and renders the
    // iframe into our mount node.
    /* eslint-disable */
    ;(function (C, A, L) {
        let p = function (a, ar) { a.q.push(ar) }
        let d = C.document
        C.Cal = C.Cal || function () {
            let cal = C.Cal
            let ar = arguments
            if (!cal.loaded) {
                cal.ns = {}
                cal.q = cal.q || []
                d.head.appendChild(d.createElement("script")).src = A
                cal.loaded = true
            }
            if (ar[0] === L) {
                const api = function () { p(api, arguments) }
                const namespace = ar[1]
                api.q = api.q || []
                if (typeof namespace === "string") {
                    cal.ns[namespace] = cal.ns[namespace] || api
                    p(cal.ns[namespace], ar)
                    p(cal, ["initNamespace", namespace])
                } else {
                    p(cal, ar)
                }
                return
            }
            p(cal, ar)
        }
    })(window, "https://app.cal.com/embed/embed.js", "init")
    /* eslint-enable */

    window.Cal("init", NAMESPACE, { origin: "https://cal.com" })

    window.Cal.ns[NAMESPACE]("inline", {
        elementOrSelector: calMount.value,
        config: { layout: "month_view" },
        calLink: CAL_LINK,
    })

    window.Cal.ns[NAMESPACE]("ui", {
        hideEventTypeDetails: false,
        layout: "month_view",
        theme: vuetifyTheme.global.current.value.dark ? "dark" : "light",
    })
})

onUnmounted(() => {
    if (calMount.value) calMount.value.innerHTML = ""
})
</script>

<style lang="scss" scoped>
.cal-card {
    border-radius: 16px;
    overflow: hidden;
    padding: 8px;
}

.cal-mount {
    min-height: 640px;

    :deep(iframe) {
        width: 100% !important;
        border: 0;
        display: block;
    }
}
</style>
