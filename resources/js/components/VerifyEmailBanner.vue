<template>
    <VAlert
        v-if="show"
        type="warning"
        variant="tonal"
        density="comfortable"
        class="mb-4"
    >
        <template #prepend>
            <VIcon icon="tabler-mail-exclamation" />
        </template>
        <div class="d-flex align-center justify-space-between flex-wrap ga-3">
            <div>
                <strong>Confirm your email</strong> — we sent a confirmation link to
                <span class="font-mono">{{ user.email }}</span>. Click it to receive your code-review reports by email.
            </div>
            <VBtn
                size="small"
                variant="outlined"
                color="warning"
                :loading="sending"
                @click="resend"
            >
                {{ sentMsg || "Resend email" }}
            </VBtn>
        </div>
    </VAlert>
</template>

<script setup>
import { $api } from "@/utils/api"
import { useAuthStore } from "@/stores/auth"

const authStore = useAuthStore()
const sending = ref(false)
const sentMsg = ref("")

const user = computed(() => authStore.user || {})
const show = computed(() => authStore.user && authStore.user.emailVerified === false)

const resend = async () => {
    sending.value = true
    sentMsg.value = ""
    try {
        await $api("/auth/email/resend", { method: "POST" })
        sentMsg.value = "Sent ✓"
        setTimeout(() => (sentMsg.value = ""), 3000)
    } catch {
        sentMsg.value = "Failed"
        setTimeout(() => (sentMsg.value = ""), 3000)
    } finally {
        sending.value = false
    }
}
</script>
