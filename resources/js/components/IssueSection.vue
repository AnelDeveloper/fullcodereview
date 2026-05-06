<template>
    <div class="mb-6">
        <div class="d-flex align-center ga-3 mb-4">
            <div class="pa-3 rounded-lg" :style="bgStyle">
                <VIcon :icon="icon" :color="color" size="22" />
            </div>
            <div>
                <h3 class="text-h6 font-weight-bold">{{ title }}</h3>
                <div class="text-caption text-medium-emphasis">
                    {{ issues.length }} issue{{ issues.length === 1 ? "" : "s" }} found
                </div>
            </div>
        </div>

        <div v-if="issues.length === 0" class="text-body-2 text-success d-flex align-center ga-2 ms-2">
            <VIcon icon="tabler-circle-check" />
            <span>No issues detected — nice work.</span>
        </div>

        <IssueCard v-for="issue in issues" :key="issue.id" :issue="issue" />
    </div>
</template>

<script setup>
import IssueCard from "@/components/IssueCard.vue"

const props = defineProps({
    title: { type: String, required: true },
    icon: { type: String, required: true },
    color: { type: String, default: "primary" },
    issues: { type: Array, required: true },
})

const bgStyle = computed(() => ({
    background: `rgba(var(--v-theme-${props.color}), 0.1)`,
    border: `1px solid rgba(var(--v-theme-${props.color}), 0.3)`,
}))
</script>
