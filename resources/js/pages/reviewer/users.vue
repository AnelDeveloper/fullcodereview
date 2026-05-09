<template>
    <div>
        <div class="d-flex align-center justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <div class="d-flex align-center ga-2 mb-1">
                    <h1 class="text-h4 font-weight-bold">User management</h1>
                    <VChip color="primary" variant="flat" size="small" prepend-icon="tabler-users">
                        Reviewer-only
                    </VChip>
                </div>
                <p class="text-body-2 text-medium-emphasis">
                    All registered users. Toggle the reviewer flag to grant or revoke access to the audit queue.
                </p>
            </div>
            <VBtn variant="text" prepend-icon="tabler-refresh" @click="load">Refresh</VBtn>
        </div>

        <VAlert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">
            {{ error }}
        </VAlert>
        <VAlert v-if="actionMessage" type="success" variant="tonal" density="compact" closable class="mb-4" @click:close="actionMessage = ''">
            {{ actionMessage }}
        </VAlert>

        <VCard variant="outlined" class="mb-4">
            <VCardText class="py-3 px-4">
                <div class="d-flex flex-wrap align-center ga-3">
                    <VTextField
                        v-model="search"
                        placeholder="Search by name or email…"
                        prepend-inner-icon="tabler-search"
                        density="compact"
                        variant="outlined"
                        hide-details
                        clearable
                        style="min-width: 280px;"
                        @input="onSearchInput"
                    />
                    <VSwitch
                        v-model="reviewersOnly"
                        label="Reviewers only"
                        density="compact"
                        color="primary"
                        hide-details
                        @update:model-value="load"
                    />
                    <VSpacer />
                    <span class="text-caption text-medium-emphasis">
                        {{ users.length }} user{{ users.length === 1 ? '' : 's' }}
                    </span>
                </div>
            </VCardText>
        </VCard>

        <div v-if="loading" class="d-flex justify-center py-10">
            <VProgressCircular indeterminate color="primary" />
        </div>

        <VCard v-else-if="!users.length" variant="outlined">
            <VCardText class="text-center py-10 text-medium-emphasis">
                No users match your filters.
            </VCardText>
        </VCard>

        <VCard v-else variant="outlined">
            <VTable hover>
                <thead>
                    <tr>
                        <th class="text-left">User</th>
                        <th class="text-left">Email</th>
                        <th class="text-left">Joined</th>
                        <th class="text-center">Verified</th>
                        <th class="text-center">Reviewer</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in users" :key="u.id">
                        <td>
                            <div class="d-flex align-center ga-3 py-2">
                                <VAvatar size="36" color="primary" class="font-weight-bold">
                                    {{ initials(u.name) }}
                                </VAvatar>
                                <div>
                                    <div class="font-weight-bold">
                                        {{ u.name }}
                                        <VChip v-if="u.id === selfId" size="x-small" color="primary" variant="flat" class="ml-1">You</VChip>
                                    </div>
                                    <div class="text-caption text-medium-emphasis">ID #{{ u.id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="font-mono text-body-2">{{ u.email }}</td>
                        <td class="text-body-2 text-medium-emphasis">{{ formatDate(u.createdAt) }}</td>
                        <td class="text-center">
                            <VIcon v-if="u.verified" icon="tabler-circle-check" color="success" />
                            <VIcon v-else icon="tabler-circle-dashed" color="medium-emphasis" />
                        </td>
                        <td class="text-center">
                            <VSwitch
                                :model-value="u.isReviewer"
                                color="primary"
                                density="compact"
                                hide-details
                                inline
                                :loading="updatingId === u.id"
                                :disabled="u.id === selfId || updatingId === u.id"
                                :title="u.id === selfId ? 'You can\'t demote yourself' : ''"
                                @update:model-value="(val) => onToggle(u, val)"
                            />
                        </td>
                    </tr>
                </tbody>
            </VTable>
        </VCard>
    </div>
</template>

<script setup>
import { fetchAdminUsers, setUserReviewer } from "@/utils/codeCheck"
import { useAuthStore } from "@/stores/auth"

const authStore = useAuthStore()
const selfId = computed(() => authStore.user?.id)

const users = ref([])
const loading = ref(true)
const error = ref("")
const actionMessage = ref("")
const search = ref("")
const reviewersOnly = ref(false)
const updatingId = ref(null)

let searchTimer = null

const load = async () => {
    loading.value = true
    error.value = ""
    try {
        const r = await fetchAdminUsers(search.value, reviewersOnly.value)
        users.value = r.items || []
    } catch (e) {
        error.value = e?.data?.message || e.message
    } finally {
        loading.value = false
    }
}

const onSearchInput = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(load, 250)
}

const onToggle = async (u, value) => {
    if (u.id === selfId.value) return
    updatingId.value = u.id
    error.value = ""
    try {
        const r = await setUserReviewer(u.id, value)
        u.isReviewer = r.user.isReviewer
        actionMessage.value = `${u.email} → ${u.isReviewer ? "promoted to reviewer" : "removed from reviewers"}.`
    } catch (e) {
        error.value = e?.data?.message || e.message
        // No optimistic flip — switch shows the original value because we
        // bind via :model-value rather than v-model, so a failed PATCH leaves
        // the UI in the correct (pre-toggle) state automatically.
    } finally {
        updatingId.value = null
    }
}

const initials = (name) => {
    if (!name) return "?"
    return name.trim().split(/\s+/).slice(0, 2).map(p => p[0]?.toUpperCase()).join("")
}

const formatDate = (d) => {
    try { return new Date(d).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" }) }
    catch { return d }
}

onMounted(load)
</script>
