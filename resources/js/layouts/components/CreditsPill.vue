<template>
    <div
        class="credits-pill d-inline-flex align-center ga-2 px-3 py-1.5 rounded-pill cursor-pointer"
        @click="onClick"
    >
        <VIcon icon="tabler-coins" size="18" />
        <span class="text-body-2 font-weight-semibold">{{ count }}</span>
        <span class="text-caption d-none d-sm-inline">{{ count === 1 ? "credit" : "credits" }}</span>
    </div>
</template>

<script setup>
import { useAuthStore } from "@/stores/auth"

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const count = computed(() => authStore.credits)

const onClick = () => {
    // If they're already on the homepage, jump straight to the buy step
    if (route.path === "/") {
        const ev = new CustomEvent("codereview:open-buy")
        window.dispatchEvent(ev)
    } else {
        router.push("/?buy=1")
    }
}
</script>

<style lang="scss" scoped>
.credits-pill {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(236, 72, 153, 0.15));
    border: 1px solid rgba(139, 92, 246, 0.3);
    color: rgb(var(--v-theme-on-surface));
    transition: all .2s ease;
    user-select: none;

    &:hover {
        border-color: rgb(var(--v-theme-primary));
        box-shadow: 0 6px 20px -8px rgba(139, 92, 246, 0.5);
        transform: translateY(-1px);
    }
}
</style>
