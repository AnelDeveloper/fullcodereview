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
                            to="/"
                        >
                            Continue to dashboard
                        </VBtn>

                        <VBtn
                            block
                            variant="text"
                            size="default"
                            :loading="resending"
                            @click="resend"
                        >
                            Didn't get it? Resend email
                        </VBtn>
                    </div>

                    <p class="text-caption text-medium-emphasis mt-6">
                        Wrong email?
                        <RouterLink to="/register" class="text-primary">Register again</RouterLink>
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

const resending = ref(false)
const resendStatus = ref(null)

const resend = async () => {
    resending.value = true
    resendStatus.value = null
    try {
        await $api("/auth/email/resend", { method: "POST" })
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
    background: rgba(21, 16, 43, 0.65) !important;
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(139, 92, 246, 0.18) !important;
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.06) inset,
        0 24px 60px -20px rgba(139, 92, 246, 0.4),
        0 8px 32px rgba(0, 0, 0, 0.4);
    border-radius: 20px !important;
}

:deep(.v-theme--light) .auth-card {
    background: rgba(255, 255, 255, 0.7) !important;
}

.mail-icon {
    width: 80px;
    height: 80px;
    border-radius: 24px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(236, 72, 153, 0.15));
    border: 1px solid rgba(139, 92, 246, 0.3);
    box-shadow: 0 12px 32px -10px rgba(139, 92, 246, 0.4);
}

.gradient-text {
    background: linear-gradient(90deg, #8B5CF6 0%, #EC4899 50%, #06B6D4 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
}

.vibe-cta {
    background: linear-gradient(135deg, #8B5CF6 0%, #EC4899 100%) !important;
    color: #fff !important;
    font-weight: 600 !important;
    box-shadow:
        0 12px 28px -10px rgba(139, 92, 246, 0.7),
        0 0 0 1px rgba(255, 255, 255, 0.1) inset !important;
    &:hover { filter: brightness(1.07); }
}
</style>
