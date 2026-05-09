<template>
    <div>
        <div class="d-flex align-center justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold">Profile</h1>
                <p class="text-body-2 text-medium-emphasis">Update your account details, password, and connections.</p>
            </div>
        </div>

        <VRow>
            <!-- Left: header card with avatar -->
            <VCol cols="12" md="4">
                <VCard variant="outlined">
                    <VCardText class="text-center pa-6">
                        <VAvatar size="96" color="primary" class="mb-4">
                            <span class="text-h3 font-weight-bold">{{ initials }}</span>
                        </VAvatar>
                        <h3 class="text-h6 font-weight-bold mb-1">{{ user?.name }}</h3>
                        <p class="text-body-2 text-medium-emphasis font-mono mb-3">{{ user?.email }}</p>

                        <div class="d-flex flex-wrap justify-center ga-2">
                            <VChip
                                size="small"
                                :color="user?.emailVerified ? 'success' : 'warning'"
                                variant="tonal"
                                :prepend-icon="user?.emailVerified ? 'tabler-circle-check' : 'tabler-alert-circle'"
                            >
                                {{ user?.emailVerified ? 'Email verified' : 'Email unverified' }}
                            </VChip>
                            <VChip
                                v-if="user?.isReviewer"
                                size="small"
                                color="primary"
                                variant="flat"
                                prepend-icon="tabler-shield-check"
                            >
                                Reviewer
                            </VChip>
                        </div>
                    </VCardText>
                </VCard>
            </VCol>

            <!-- Right: forms -->
            <VCol cols="12" md="8">
                <!-- Profile form -->
                <VCard variant="outlined" class="mb-4">
                    <VCardItem>
                        <VCardTitle>Account details</VCardTitle>
                        <VCardSubtitle>Changing your email requires re-verification.</VCardSubtitle>
                    </VCardItem>
                    <VCardText>
                        <VAlert v-if="profileMsg" type="success" variant="tonal" density="compact" closable class="mb-4" @click:close="profileMsg = ''">
                            {{ profileMsg }}
                        </VAlert>
                        <VAlert v-if="profileError" type="error" variant="tonal" density="compact" class="mb-4">
                            {{ profileError }}
                        </VAlert>
                        <VForm @submit.prevent="onSaveProfile">
                            <VRow>
                                <VCol cols="12" md="6">
                                    <AppTextField
                                        v-model="form.name"
                                        label="Full name"
                                        :error-messages="profileFieldErrors.name"
                                        prepend-inner-icon="tabler-user"
                                    />
                                </VCol>
                                <VCol cols="12" md="6">
                                    <AppTextField
                                        v-model="form.email"
                                        label="Email"
                                        type="email"
                                        :error-messages="profileFieldErrors.email"
                                        prepend-inner-icon="tabler-mail"
                                    />
                                </VCol>
                            </VRow>
                            <div class="d-flex justify-end mt-2">
                                <VBtn
                                    type="submit"
                                    color="primary"
                                    rounded="pill"
                                    :loading="savingProfile"
                                    :disabled="!profileDirty"
                                >
                                    Save changes
                                </VBtn>
                            </div>
                        </VForm>
                    </VCardText>
                </VCard>

                <!-- Password form -->
                <VCard variant="outlined" class="mb-4">
                    <VCardItem>
                        <VCardTitle>Change password</VCardTitle>
                        <VCardSubtitle>Other sessions will be signed out for security.</VCardSubtitle>
                    </VCardItem>
                    <VCardText>
                        <VAlert v-if="passwordMsg" type="success" variant="tonal" density="compact" closable class="mb-4" @click:close="passwordMsg = ''">
                            {{ passwordMsg }}
                        </VAlert>
                        <VAlert v-if="passwordError" type="error" variant="tonal" density="compact" class="mb-4">
                            {{ passwordError }}
                        </VAlert>
                        <VForm @submit.prevent="onChangePassword">
                            <VRow>
                                <VCol cols="12">
                                    <AppTextField
                                        v-model="pwForm.current"
                                        label="Current password"
                                        :type="showCurrent ? 'text' : 'password'"
                                        :error-messages="pwFieldErrors.current_password"
                                        prepend-inner-icon="tabler-lock"
                                        :append-inner-icon="showCurrent ? 'tabler-eye-off' : 'tabler-eye'"
                                        autocomplete="current-password"
                                        @click:append-inner="showCurrent = !showCurrent"
                                    />
                                </VCol>
                                <VCol cols="12" md="6">
                                    <AppTextField
                                        v-model="pwForm.password"
                                        label="New password"
                                        :type="showNew ? 'text' : 'password'"
                                        :error-messages="pwFieldErrors.password"
                                        prepend-inner-icon="tabler-key"
                                        :append-inner-icon="showNew ? 'tabler-eye-off' : 'tabler-eye'"
                                        autocomplete="new-password"
                                        hint="Minimum 8 characters."
                                        persistent-hint
                                        @click:append-inner="showNew = !showNew"
                                    />
                                </VCol>
                                <VCol cols="12" md="6">
                                    <AppTextField
                                        v-model="pwForm.password_confirmation"
                                        label="Confirm new password"
                                        :type="showNew ? 'text' : 'password'"
                                        :error-messages="pwFieldErrors.password_confirmation"
                                        prepend-inner-icon="tabler-key"
                                        autocomplete="new-password"
                                    />
                                </VCol>
                            </VRow>
                            <div class="d-flex justify-end mt-2">
                                <VBtn
                                    type="submit"
                                    color="primary"
                                    rounded="pill"
                                    :loading="savingPassword"
                                    :disabled="!pwDirty"
                                >
                                    Update password
                                </VBtn>
                            </div>
                        </VForm>
                    </VCardText>
                </VCard>

                <!-- GitHub connection -->
                <VCard variant="outlined">
                    <VCardItem>
                        <VCardTitle>GitHub connection</VCardTitle>
                        <VCardSubtitle>Required for scanning private repositories.</VCardSubtitle>
                    </VCardItem>
                    <VCardText>
                        <div v-if="user?.githubLogin" class="d-flex align-center justify-space-between flex-wrap ga-3">
                            <div class="d-flex align-center ga-3">
                                <VAvatar size="40" :image="user.githubAvatarUrl" />
                                <div>
                                    <div class="text-caption text-medium-emphasis">Connected as</div>
                                    <div class="text-body-1 font-weight-bold">@{{ user.githubLogin }}</div>
                                </div>
                            </div>
                            <VBtn
                                color="error"
                                variant="outlined"
                                rounded="pill"
                                :loading="disconnectingGithub"
                                @click="onDisconnectGithub"
                            >
                                Disconnect
                            </VBtn>
                        </div>
                        <div v-else class="d-flex align-center justify-space-between flex-wrap ga-3">
                            <div class="text-body-2 text-medium-emphasis">
                                Not connected. Connect to scan your private GitHub repos.
                            </div>
                            <VBtn
                                color="primary"
                                rounded="pill"
                                prepend-icon="tabler-brand-github"
                                @click="onConnectGithub"
                            >
                                Connect GitHub
                            </VBtn>
                        </div>
                        <p class="text-caption text-medium-emphasis mt-3 mb-0">
                            To fully revoke access on GitHub's side, visit <a href="https://github.com/settings/applications" target="_blank" rel="noopener">github.com/settings/applications</a>.
                        </p>
                    </VCardText>
                </VCard>
            </VCol>
        </VRow>
    </div>
</template>

<script setup>
import { useAuthStore } from "@/stores/auth"
import {
    updateProfile,
    changePassword,
    githubLoginUrl,
    disconnectGithub,
} from "@/utils/codeCheck"

const authStore = useAuthStore()
const user = computed(() => authStore.user)

const initials = computed(() => {
    if (!user.value?.name) return "?"
    return user.value.name.trim().split(/\s+/).slice(0, 2).map(p => p[0]?.toUpperCase()).join("")
})

// --- Profile form ----------------------------------------------------
const form = reactive({ name: "", email: "" })
const initialProfile = reactive({ name: "", email: "" })
const savingProfile = ref(false)
const profileMsg = ref("")
const profileError = ref("")
const profileFieldErrors = ref({})

const profileDirty = computed(() =>
    form.name !== initialProfile.name || form.email !== initialProfile.email
)

const seedProfile = () => {
    form.name = user.value?.name ?? ""
    form.email = user.value?.email ?? ""
    initialProfile.name = form.name
    initialProfile.email = form.email
}

const onSaveProfile = async () => {
    profileError.value = ""
    profileMsg.value = ""
    profileFieldErrors.value = {}
    savingProfile.value = true
    try {
        const r = await updateProfile(form.name, form.email)
        profileMsg.value = r.message
        // Refresh authStore so the navbar/avatar reflect changes
        await authStore.fetchMe()
        seedProfile()
    } catch (e) {
        profileError.value = e?.data?.message || e.message
        profileFieldErrors.value = e?.data?.errors || {}
    } finally {
        savingProfile.value = false
    }
}

// --- Password form --------------------------------------------------
const pwForm = reactive({ current: "", password: "", password_confirmation: "" })
const showCurrent = ref(false)
const showNew = ref(false)
const savingPassword = ref(false)
const passwordMsg = ref("")
const passwordError = ref("")
const pwFieldErrors = ref({})

const pwDirty = computed(() =>
    pwForm.current && pwForm.password && pwForm.password_confirmation
)

const onChangePassword = async () => {
    passwordError.value = ""
    passwordMsg.value = ""
    pwFieldErrors.value = {}
    savingPassword.value = true
    try {
        const r = await changePassword(pwForm.current, pwForm.password, pwForm.password_confirmation)
        passwordMsg.value = r.message
        // Server rotated our api_token; pick up the new one so we don't get logged out
        if (r.token) authStore.setToken(r.token)
        pwForm.current = ""
        pwForm.password = ""
        pwForm.password_confirmation = ""
    } catch (e) {
        passwordError.value = e?.data?.message || e.message
        pwFieldErrors.value = e?.data?.errors || {}
    } finally {
        savingPassword.value = false
    }
}

// --- GitHub --------------------------------------------------------
const disconnectingGithub = ref(false)

const onConnectGithub = () => {
    window.location.href = githubLoginUrl()
}

const onDisconnectGithub = async () => {
    if (! confirm("Disconnect GitHub from Full Code Review?")) return
    disconnectingGithub.value = true
    try {
        await disconnectGithub()
        await authStore.fetchMe()
    } catch (e) {
        profileError.value = e?.data?.message || e.message
    } finally {
        disconnectingGithub.value = false
    }
}

watch(user, seedProfile, { immediate: true })
</script>
