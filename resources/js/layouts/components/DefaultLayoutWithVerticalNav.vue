<template>
    <VerticalNavLayout :nav-items="navItems">
        <template #navbar="{ toggleVerticalOverlayNavActive }">
            <div class="d-flex h-100 align-center">
                <IconBtn
                    id="vertical-nav-toggle-btn"
                    class="ms-n2 d-lg-none"
                    @click="toggleVerticalOverlayNavActive(true)"
                >
                    <VIcon size="26" icon="tabler-menu-2" />
                </IconBtn>

                <VSpacer />

                <CreditsPill class="ml-15" />
                <VBtn
                    icon
                    variant="text"
                    class="ml-3"
                    title="Send feedback"
                    aria-label="Send feedback"
                    @click="feedbackOpen = true"
                >
                    <VIcon icon="tabler-message-circle" />
                </VBtn>
                <NavbarThemeSwitcher class="mr-5 ml-3" />
                <UserProfile />
            </div>
        </template>

        <slot />

        <FeedbackDialog v-model="feedbackOpen" mode="support" />

        <template #footer>
            <Footer />
        </template>
    </VerticalNavLayout>
</template>

<script setup>
import CreditsPill from "@/layouts/components/CreditsPill.vue"
import FeedbackDialog from "@/components/FeedbackDialog.vue"
import Footer from "@/layouts/components/Footer.vue"
import NavbarThemeSwitcher from "@/layouts/components/NavbarThemeSwitcher.vue"
import UserProfile from "@/layouts/components/UserProfile.vue"
import navItemsRaw from "@/navigation/vertical"
import { useAuthStore } from "@/stores/auth"
import { VerticalNavLayout } from "@layouts"
import { computed, onMounted, ref } from "vue"

const feedbackOpen = ref(false)

const authStore = useAuthStore()

// Filter out reviewer-only items for non-reviewers. Reactive on auth.user.
const navItems = computed(() =>
    navItemsRaw.filter(item => ! item.requiresReviewer || authStore.user?.isReviewer)
)

// Pull fresh user + credits whenever the layout mounts so the navbar pill is up to date
onMounted(() => {
    if (useCookie("accessToken").value) authStore.fetchMe()
})
</script>
