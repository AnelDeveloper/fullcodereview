<route lang="json">
{
  "meta": { "layout": "blank", "public": true }
}
</route>

<template>
    <div class="d-flex align-center justify-center" style="min-height: 100vh;">
        <div class="text-center">
            <VProgressCircular indeterminate color="primary" size="44" class="mb-4" />
            <p class="text-body-1">Signing you in…</p>
        </div>
    </div>
</template>

<script setup>
import { useAuthStore } from "@/stores/auth"

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

onMounted(() => {
    const raw = route.query.data
    if (!raw) {
        router.replace("/login?error=" + encodeURIComponent("Missing sign-in payload"))
        return
    }

    let payload
    try {
        payload = JSON.parse(decodeURIComponent(String(raw)))
    } catch {
        router.replace("/login?error=" + encodeURIComponent("Invalid sign-in payload"))
        return
    }

    if (!payload?.token || !payload?.user) {
        router.replace("/login?error=" + encodeURIComponent("Sign-in payload incomplete"))
        return
    }

    authStore.setToken(payload.token)
    authStore.setUser(payload.user)
    router.replace("/")
})
</script>
