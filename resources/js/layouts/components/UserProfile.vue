<template>
    <VBtn
        v-if="!user"
        variant="tonal"
        size="small"
        to="/login"
    >
        Sign in
    </VBtn>

    <div v-else>
        <VBadge
            dot
            location="bottom right"
            offset-x="3"
            offset-y="3"
            color="success"
            bordered
        >
            <VAvatar
                size="38"
                color="primary"
                variant="tonal"
                class="cursor-pointer"
            >
                <span class="text-h6">{{ initials }}</span>
                <VMenu activator="parent" width="240" location="bottom end" offset="14px">
                    <VList>
                        <VListItem>
                            <VListItemTitle class="font-weight-semibold">{{ user.name }}</VListItemTitle>
                            <VListItemSubtitle class="text-disabled">{{ user.email }}</VListItemSubtitle>
                        </VListItem>
                        <VDivider class="my-2" />
                        <VListItem to="/history">
                            <template #prepend>
                                <VIcon icon="tabler-history" size="22" class="me-2" />
                            </template>
                            <VListItemTitle>History</VListItemTitle>
                        </VListItem>
                        <VListItem @click="logout">
                            <template #prepend>
                                <VIcon icon="tabler-logout" size="22" class="me-2" />
                            </template>
                            <VListItemTitle>Logout</VListItemTitle>
                        </VListItem>
                    </VList>
                </VMenu>
            </VAvatar>
        </VBadge>
    </div>
</template>

<script setup>
import { useAuthStore } from "@/stores/auth"

const authStore = useAuthStore()
const router = useRouter()

const user = computed(() => authStore.user)

const initials = computed(() => {
    if (!user.value?.name) return "?"
    return user.value.name
        .split(" ")
        .map(p => p[0])
        .slice(0, 2)
        .join("")
        .toUpperCase()
})

const logout = async () => {
    await authStore.logout()
    router.push("/login")
}
</script>
