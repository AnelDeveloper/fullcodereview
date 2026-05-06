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

                <CreditsPill class="me-3" />
                <NavbarThemeSwitcher class="me-2" />
                <UserProfile />
            </div>
        </template>

        <slot />

        <template #footer>
            <Footer />
        </template>
    </VerticalNavLayout>
</template>

<script setup>
import { onMounted } from "vue"
import { VerticalNavLayout } from "@layouts"
import navItems from "@/navigation/vertical"
import NavbarThemeSwitcher from "@/layouts/components/NavbarThemeSwitcher.vue"
import UserProfile from "@/layouts/components/UserProfile.vue"
import Footer from "@/layouts/components/Footer.vue"
import CreditsPill from "@/layouts/components/CreditsPill.vue"
import { useAuthStore } from "@/stores/auth"

const authStore = useAuthStore()

// Pull fresh user + credits whenever the layout mounts so the navbar pill is up to date
onMounted(() => {
    if (useCookie("accessToken").value) authStore.fetchMe()
})
</script>
