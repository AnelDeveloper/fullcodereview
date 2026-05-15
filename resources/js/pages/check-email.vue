<route lang="json">
{
  "meta": { "layout": "blank", "public": true }
}
</route>

<template>
    <div class="auth-wrapper d-flex align-center justify-center pa-4">
        <AuthAnimatedBackground />

        <div class="position-relative my-sm-16">
            <VCard
                class="auth-card"
                max-width="480"
                :class="$vuetify.display.smAndUp ? 'pa-6' : 'pa-4'"
            >
                <VCardItem class="d-flex flex-column align-center text-center pt-2">
                    <div class="mail-icon mb-4">
                        <VIcon icon="tabler-mail-check" size="40" color="primary" />
                    </div>
                    <h1 class="text-h4 font-weight-bold gradient-text mb-2">Check your email</h1>
                    <p class="text-body-2 text-medium-emphasis">
                        We sent a confirmation link to
                    </p>
                    <p class="text-body-1 font-weight-semibold text-primary mt-1 font-mono text-truncate" style="max-width:100%">
                        {{ email }}
                    </p>
                </VCardItem>

                <VCardText class="text-center">
                    <VAlert
                        v-if="resendStatus === 'sent'"
                        type="success"
                        variant="tonal"
                        density="compact"
                        class="mb-4"
                    >
                        Sent! Check your inbox again.
                    </VAlert>
                    <VAlert
                        v-else-if="resendStatus === 'error'"
                        type="error"
                        variant="tonal"
                        density="compact"
                        class="mb-4"
                    >
                        Couldn't resend — try again in a minute.
                    </VAlert>

                    <p class="text-body-2 text-medium-emphasis mb-6">
                        Click the <strong>Confirm email address</strong> button in that email to activate your account.
                        The link is valid for 24 hours.
                    </p>

                    <div class="d-flex flex-column ga-2">
                        <VBtn
                            block
                            color="primary"
                            rounded="lg"
                            class="vibe-cta"
                            size="large"
                            :loading="resending"
                            @click="resend"
                        >
                            Resend verification email
                        </VBtn>

                        <VBtn
                            block
                            variant="outlined"
                            size="default"
                            :to="{ path: '/login', query: forwardQuery }"
                        >
                            Back to sign in
                        </VBtn>
                    </div>

                    <p class="text-caption text-medium-emphasis mt-6">
                        Wrong email?
                        <RouterLink :to="{ path: '/register', query: forwardQuery }" class="text-primary">Register again</RouterLink>
                    </p>
                </VCardText>
            </VCard>
        </div>
    </div>
</template>

<script setup>
import AuthAnimatedBackground from "@/views/auth/AuthAnimatedBackground.vue"
import { $api } from "@/utils/api"
import { useAuthStore } from "@/stores/auth"

const route = useRoute()
const authStore = useAuthStore()

// Email comes from the redirect (?email=...) or from the auth store
const email = computed(() => route.query.email || authStore.user?.email || "")

// Carry the post-auth redirect target (if any) through the back-to-login /
// register-again links so a new user who started from /book lands back there
// after they verify and sign in.
const forwardQuery = computed(() => (route.query.redirect ? { redirect: route.query.redirect } : {}))

const resending = ref(false)
const resendStatus = ref(null)

const resend = async () => {
    if (!email.value) {
        resendStatus.value = "error"
        return
    }
    resending.value = true
    resendStatus.value = null
    try {
        await $api("/auth/email/resend", { method: "POST", body: { email: email.value } })
        resendStatus.value = "sent"
    } catch {
        resendStatus.value = "error"
    } finally {
        resending.value = false
        setTimeout(() => (resendStatus.value = null), 5000)
    }
}
</script>

<style lang="scss" scoped>
.auth-card {
    background: rgb(var(--v-theme-surface));
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 20px;
}

.mail-icon {
    width: 72px;
    height: 72px;
    border-radius: 16px;
    margin: 0 auto;
    display: grid;
    place-items: center;
    background: rgba(var(--v-theme-on-surface), 0.05);
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
