<template>
    <div class="auth-wrapper d-flex align-center justify-center pa-4">
        <AuthAnimatedBackground />

        <div class="theme-toggle-wrapper">
            <VBtn
                icon
                variant="text"
                size="small"
                @click="toggleTheme"
            >
                <VIcon :icon="isDark ? 'tabler-sun' : 'tabler-moon'" />
            </VBtn>
        </div>

        <div class="position-relative my-sm-16">
            <VCard
                class="auth-card"
                max-width="460"
                :class="$vuetify.display.smAndUp ? 'pa-6' : 'pa-4'"
            >
                <VCardItem class="d-flex flex-column align-center text-center pt-2">
                    <RouterLink to="/" class="brand-mark mb-3">
                        <img src="/logo.png" alt="Full Code Review" />
                    </RouterLink>
                    <h1 class="text-h4 font-weight-bold gradient-text mb-1">Welcome back</h1>
                    <p class="text-body-2 text-medium-emphasis">Sign in to run AI-powered code reviews on your repos.</p>
                </VCardItem>

                <VCardText>
                    <VForm @submit.prevent="login">
                        <VRow>
                            <VCol cols="12">
                                <AppTextField
                                    v-model="form.email"
                                    autofocus
                                    label="Email"
                                    type="email"
                                    placeholder="you@example.com"
                                    :error-messages="errors.email"
                                />
                            </VCol>

                            <VCol cols="12">
                                <AppTextField
                                    v-model="form.password"
                                    label="Password"
                                    placeholder="············"
                                    :type="isPasswordVisible ? 'text' : 'password'"
                                    :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                                    :error-messages="errors.password"
                                    @click:append-inner="isPasswordVisible = !isPasswordVisible"
                                />
                            </VCol>

                            <VCol cols="12">
                                <VBtn
                                    block
                                    type="submit"
                                    :loading="loading"
                                    size="large"
                                    rounded="lg"
                                    class="vibe-cta"
                                >
                                    Sign in
                                </VBtn>
                            </VCol>

                            <VCol cols="12" class="d-flex align-center ga-3">
                                <VDivider />
                                <span class="text-caption text-medium-emphasis">or</span>
                                <VDivider />
                            </VCol>

                            <VCol cols="12">
                                <VBtn
                                    block
                                    size="large"
                                    rounded="lg"
                                    variant="outlined"
                                    href="/api/auth/google/redirect"
                                >
                                    <template #prepend>
                                        <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/></svg>
                                    </template>
                                    Continue with Google
                                </VBtn>
                            </VCol>

                            <VCol cols="12" class="text-body-1 text-center">
                                <span class="d-inline-block">New here?</span>
                                <RouterLink class="text-primary ms-1 d-inline-block text-body-1" to="/register">
                                    Create an account
                                </RouterLink>
                            </VCol>
                        </VRow>
                    </VForm>
                </VCardText>
            </VCard>
        </div>
    </div>
</template>

<script setup>
import AuthAnimatedBackground from "./AuthAnimatedBackground.vue"
import { useAuthStore } from "@/stores/auth"
import { useTheme } from "vuetify"
import { useConfigStore } from "@core/stores/config"

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()
const vuetifyTheme = useTheme()
const configStore = useConfigStore()
const isDark = computed(() => vuetifyTheme.global.current.value.dark)
const toggleTheme = () => { configStore.theme = isDark.value ? "light" : "dark" }

const form = ref({ email: "", password: "" })
const errors = ref({})
const isPasswordVisible = ref(false)
const loading = ref(false)

onMounted(() => {
    if (route.query.error) {
        errors.value = { email: [decodeURIComponent(String(route.query.error))] }
    }
})

const validate = () => {
    errors.value = {}
    if (!form.value.email) errors.value.email = ["Email is required"]
    else if (!/^\S+@\S+\.\S+$/.test(form.value.email)) errors.value.email = ["Invalid email address"]
    if (!form.value.password) errors.value.password = ["Password is required"]
    return !Object.keys(errors.value).length
}

const login = async () => {
    if (!validate()) return
    loading.value = true
    try {
        await authStore.login(form.value)
        const redirect = route.query.redirect || "/"
        // Hard reload instead of router.push so the auth layout (and its
        // fixed-position animated background) fully unmounts. SPA nav left
        // the orbs lingering in the DOM, washing out the dashboard.
        window.location.assign(redirect)
    } catch (e) {
        const data = e?.data || {}
        if (data.code === "unverified_email") {
            const target = data.email || form.value.email
            router.push({ path: "/check-email", query: { email: target } })
            return
        }
        if (data.errors) errors.value = data.errors
        else errors.value = { email: [data.message || "Login failed"] }
    } finally {
        loading.value = false
    }
}
</script>

<style lang="scss">
@use "@core-scss/template/pages/page-auth.scss";

.auth-card {
    background: rgba(255, 255, 255, 0.92) !important;
    .v-theme--dark & { background: rgba(21, 16, 43, 0.88) !important; }
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(139, 92, 246, 0.18) !important;
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.06) inset,
        0 24px 60px -20px rgba(139, 92, 246, 0.4),
        0 8px 32px rgba(0, 0, 0, 0.4);
    border-radius: 20px !important;
}

// Compact error messages — no block bleed-through, clear visual intent.
.auth-card .v-input--error .v-messages__message {
    padding: 6px 10px;
    margin-top: 4px;
    background: rgba(220, 38, 38, 0.08);
    color: #DC2626;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    line-height: 1.3;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    opacity: 1;

    &::before {
        content: "!";
        display: inline-grid;
        place-items: center;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #DC2626;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        line-height: 1;
        flex-shrink: 0;
    }
}

.v-theme--dark .auth-card .v-input--error .v-messages__message {
    background: rgba(220, 38, 38, 0.18);
    color: #FCA5A5;

    &::before { background: #FCA5A5; color: #1B1340; }
}

.brand-mark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    filter: drop-shadow(0 12px 24px rgba(139, 92, 246, 0.4));

    img {
        width: 88px;
        height: 88px;
        object-fit: contain;
        display: block;
    }
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
    letter-spacing: 0.2px;
    box-shadow:
        0 12px 28px -10px rgba(139, 92, 246, 0.7),
        0 0 0 1px rgba(255, 255, 255, 0.1) inset !important;

    &:hover { filter: brightness(1.07); }
}

.theme-toggle-wrapper {
    position: fixed;
    top: 16px;
    right: 16px;
    z-index: 10;
}

// Force a visible toggle on the auth page regardless of Vuetify's defaults.
// The auth bg is dark by default → translucent white pill with a light icon.
.theme-toggle-wrapper .v-btn {
    background: rgba(255, 255, 255, 0.08) !important;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    color: rgba(255, 255, 255, 0.9) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
}

// Light theme flips the auth bg to pale lavender → dark icon for contrast.
.v-theme--light .theme-toggle-wrapper .v-btn {
    background: rgba(15, 10, 31, 0.06) !important;
    color: rgba(15, 10, 31, 0.78) !important;
    border-color: rgba(15, 10, 31, 0.12) !important;
}
</style>
