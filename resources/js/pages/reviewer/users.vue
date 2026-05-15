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
                    Create, edit, soft-delete, and restore users. Toggle the reviewer flag to grant audit-queue access.
                </p>
            </div>
            <div class="d-flex ga-2">
                <VBtn variant="text" prepend-icon="tabler-refresh" @click="load">Refresh</VBtn>
                <VBtn color="primary" prepend-icon="tabler-user-plus" @click="openCreate">New user</VBtn>
            </div>
        </div>

        <VAlert v-if="error" type="error" variant="tonal" density="compact" closable class="mb-4" @click:close="error = ''">
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
                    <VSwitch
                        v-model="showDeleted"
                        label="Show deleted"
                        density="compact"
                        color="warning"
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
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in users" :key="u.id" :class="{ 'row-trashed': u.deletedAt }">
                        <td>
                            <div class="d-flex align-center ga-3 py-2">
                                <VAvatar size="36" :color="u.deletedAt ? 'medium-emphasis' : 'primary'" class="font-weight-bold">
                                    {{ initials(u.name) }}
                                </VAvatar>
                                <div>
                                    <div class="font-weight-bold d-flex align-center ga-1 flex-wrap">
                                        {{ u.name }}
                                        <VChip v-if="u.id === selfId" size="x-small" color="primary" variant="flat">You</VChip>
                                        <VChip v-if="u.deletedAt" size="x-small" color="warning" variant="flat" prepend-icon="tabler-trash">Deleted</VChip>
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
                                :disabled="u.id === selfId || updatingId === u.id || !!u.deletedAt"
                                :title="u.deletedAt ? 'Restore the user first' : (u.id === selfId ? 'You can\'t demote yourself' : '')"
                                @update:model-value="(val) => onToggle(u, val)"
                            />
                        </td>
                        <td class="text-right">
                            <div class="d-flex justify-end ga-1">
                                <template v-if="u.deletedAt">
                                    <VBtn
                                        size="small"
                                        variant="tonal"
                                        color="success"
                                        prepend-icon="tabler-restore"
                                        :loading="actingId === u.id"
                                        @click="onRestore(u)"
                                    >
                                        Restore
                                    </VBtn>
                                </template>
                                <template v-else>
                                    <VBtn
                                        size="small"
                                        variant="text"
                                        icon="tabler-edit"
                                        :disabled="actingId === u.id"
                                        title="Edit"
                                        @click="openEdit(u)"
                                    />
                                    <VBtn
                                        size="small"
                                        variant="text"
                                        icon="tabler-trash"
                                        color="error"
                                        :disabled="u.id === selfId || actingId === u.id"
                                        :title="u.id === selfId ? 'You can\'t delete yourself' : 'Delete'"
                                        @click="askDelete(u)"
                                    />
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </VTable>
        </VCard>

        <!-- Create / Edit modal -->
        <VDialog v-model="formOpen" max-width="520" persistent>
            <VCard>
                <VCardItem>
                    <VCardTitle>{{ editingId ? "Edit user" : "Create user" }}</VCardTitle>
                    <VCardSubtitle v-if="!editingId">They can sign in immediately with the password you set.</VCardSubtitle>
                </VCardItem>
                <VCardText>
                    <VForm @submit.prevent="submitForm">
                        <VRow>
                            <VCol cols="12">
                                <VTextField
                                    v-model="form.name"
                                    label="Name"
                                    placeholder="Jane Doe"
                                    :error-messages="formErrors.name"
                                    variant="outlined"
                                    density="comfortable"
                                />
                            </VCol>
                            <VCol cols="12">
                                <VTextField
                                    v-model="form.email"
                                    label="Email"
                                    type="email"
                                    placeholder="jane@example.com"
                                    :error-messages="formErrors.email"
                                    variant="outlined"
                                    density="comfortable"
                                />
                            </VCol>
                            <VCol cols="12">
                                <VTextField
                                    v-model="form.password"
                                    :label="editingId ? 'New password (leave blank to keep current)' : 'Password'"
                                    :type="showPassword ? 'text' : 'password'"
                                    :placeholder="editingId ? '············' : 'Min 8 characters'"
                                    :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                                    :error-messages="formErrors.password"
                                    variant="outlined"
                                    density="comfortable"
                                    @click:append-inner="showPassword = !showPassword"
                                />
                            </VCol>
                            <VCol cols="6">
                                <VSwitch
                                    v-model="form.is_reviewer"
                                    label="Reviewer"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    :disabled="editingId === selfId"
                                    :title="editingId === selfId ? 'You can\'t demote yourself' : ''"
                                />
                            </VCol>
                            <VCol cols="6">
                                <VSwitch
                                    v-model="form.verified"
                                    label="Email verified"
                                    color="success"
                                    density="compact"
                                    hide-details
                                />
                            </VCol>
                        </VRow>
                    </VForm>
                </VCardText>
                <VCardActions class="px-4 pb-4">
                    <VSpacer />
                    <VBtn variant="text" :disabled="submitting" @click="closeForm">Cancel</VBtn>
                    <VBtn color="primary" :loading="submitting" @click="submitForm">
                        {{ editingId ? "Save changes" : "Create user" }}
                    </VBtn>
                </VCardActions>
            </VCard>
        </VDialog>

        <!-- Delete confirm -->
        <VDialog v-model="deleteOpen" max-width="440" persistent>
            <VCard>
                <VCardItem>
                    <VCardTitle>Delete this user?</VCardTitle>
                </VCardItem>
                <VCardText class="text-body-2">
                    <p class="mb-2">
                        <span class="font-weight-bold">{{ targetUser?.name }}</span>
                        <span class="text-medium-emphasis font-mono ml-1">({{ targetUser?.email }})</span>
                        will be soft-deleted.
                    </p>
                    <p class="text-medium-emphasis">
                        They won't be able to log in, but their analyses stay in the database. You can restore them from this page later.
                    </p>
                </VCardText>
                <VCardActions class="px-4 pb-4">
                    <VSpacer />
                    <VBtn variant="text" :disabled="submitting" @click="deleteOpen = false">Cancel</VBtn>
                    <VBtn color="error" :loading="submitting" @click="confirmDelete">Delete</VBtn>
                </VCardActions>
            </VCard>
        </VDialog>
    </div>
</template>

<script setup>
import {
    fetchAdminUsers,
    setUserReviewer,
    createUser,
    updateUser,
    deleteUser,
    restoreUser,
} from "@/utils/codeCheck"
import { useAuthStore } from "@/stores/auth"

const authStore = useAuthStore()
const selfId = computed(() => authStore.user?.id)

const users = ref([])
const loading = ref(true)
const error = ref("")
const actionMessage = ref("")
const search = ref("")
const reviewersOnly = ref(false)
const showDeleted = ref(false)
const updatingId = ref(null)
const actingId = ref(null)

let searchTimer = null

const load = async () => {
    loading.value = true
    error.value = ""
    try {
        const r = await fetchAdminUsers(search.value, reviewersOnly.value, {
            includeTrashed: showDeleted.value,
        })
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
    if (u.id === selfId.value || u.deletedAt) return
    updatingId.value = u.id
    error.value = ""
    try {
        const r = await setUserReviewer(u.id, value)
        Object.assign(u, r.user)
        actionMessage.value = `${u.email} → ${u.isReviewer ? "promoted to reviewer" : "removed from reviewers"}.`
    } catch (e) {
        error.value = e?.data?.message || e.message
    } finally {
        updatingId.value = null
    }
}

// ---- Create / Edit form ----

const formOpen = ref(false)
const editingId = ref(null)
const submitting = ref(false)
const showPassword = ref(false)
const form = ref({ name: "", email: "", password: "", is_reviewer: false, verified: true })
const formErrors = ref({})

const resetForm = () => {
    form.value = { name: "", email: "", password: "", is_reviewer: false, verified: true }
    formErrors.value = {}
    showPassword.value = false
}

const openCreate = () => {
    editingId.value = null
    resetForm()
    formOpen.value = true
}

const openEdit = (u) => {
    editingId.value = u.id
    formErrors.value = {}
    showPassword.value = false
    form.value = {
        name: u.name,
        email: u.email,
        password: "",
        is_reviewer: u.isReviewer,
        verified: u.verified,
    }
    formOpen.value = true
}

const closeForm = () => {
    if (submitting.value) return
    formOpen.value = false
}

const buildPayload = () => {
    const payload = {
        name: form.value.name,
        email: form.value.email,
        is_reviewer: form.value.is_reviewer,
        verified: form.value.verified,
    }
    if (form.value.password) payload.password = form.value.password
    return payload
}

const submitForm = async () => {
    submitting.value = true
    formErrors.value = {}
    error.value = ""
    try {
        if (editingId.value) {
            const r = await updateUser(editingId.value, buildPayload())
            const idx = users.value.findIndex(u => u.id === editingId.value)
            if (idx >= 0) users.value[idx] = r.user
            actionMessage.value = `${r.user.email} updated.`
        } else {
            const payload = buildPayload()
            if (!payload.password) {
                formErrors.value.password = ["Password is required"]
                submitting.value = false
                return
            }
            const r = await createUser(payload)
            users.value.unshift(r.user)
            actionMessage.value = `${r.user.email} created.`
        }
        formOpen.value = false
    } catch (e) {
        const data = e?.data || {}
        if (data.errors) formErrors.value = data.errors
        else error.value = data.message || e.message
    } finally {
        submitting.value = false
    }
}

// ---- Delete / Restore ----

const deleteOpen = ref(false)
const targetUser = ref(null)

const askDelete = (u) => {
    if (u.id === selfId.value) return
    targetUser.value = u
    deleteOpen.value = true
}

const confirmDelete = async () => {
    if (!targetUser.value) return
    submitting.value = true
    actingId.value = targetUser.value.id
    error.value = ""
    try {
        await deleteUser(targetUser.value.id)
        const deletedEmail = targetUser.value.email
        if (showDeleted.value) {
            // Mark in-place so the row stays visible as "Deleted"
            const idx = users.value.findIndex(u => u.id === targetUser.value.id)
            if (idx >= 0) users.value[idx] = { ...users.value[idx], deletedAt: new Date().toISOString() }
        } else {
            users.value = users.value.filter(u => u.id !== targetUser.value.id)
        }
        actionMessage.value = `${deletedEmail} deleted. Restore from 'Show deleted'.`
        deleteOpen.value = false
        targetUser.value = null
    } catch (e) {
        error.value = e?.data?.message || e.message
    } finally {
        submitting.value = false
        actingId.value = null
    }
}

const onRestore = async (u) => {
    actingId.value = u.id
    error.value = ""
    try {
        const r = await restoreUser(u.id)
        const idx = users.value.findIndex(x => x.id === u.id)
        if (idx >= 0) users.value[idx] = r.user
        actionMessage.value = `${r.user.email} restored.`
    } catch (e) {
        error.value = e?.data?.message || e.message
    } finally {
        actingId.value = null
    }
}

// ---- helpers ----

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

<style lang="scss" scoped>
.row-trashed {
    opacity: 0.6;
    background: rgba(var(--v-theme-warning), 0.04);
}
</style>
