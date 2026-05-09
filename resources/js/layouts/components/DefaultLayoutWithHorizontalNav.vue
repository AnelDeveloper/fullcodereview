<template>
    <component :is="VerticalNavLayout" :nav-items="navItems">
        <slot />
    </component>
</template>

<script setup>
import { computed } from "vue"
import { VerticalNavLayout } from "@layouts"
import navItemsRaw from "@/navigation/vertical"
import { useAuthStore } from "@/stores/auth"

const authStore = useAuthStore()
const navItems = computed(() =>
    navItemsRaw.filter(item => ! item.requiresReviewer || authStore.user?.isReviewer)
)
</script>
