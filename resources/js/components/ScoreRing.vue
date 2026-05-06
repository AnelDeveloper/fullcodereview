<template>
    <div class="d-flex flex-column align-center">
        <div class="position-relative" style="width:128px;height:128px">
            <svg viewBox="0 0 120 120" style="width:100%;height:100%;transform:rotate(-90deg)">
                <circle cx="60" cy="60" r="48" stroke="rgba(150,150,160,0.25)" stroke-width="8" fill="none" />
                <circle
                    cx="60" cy="60" r="48"
                    :stroke="ringColor"
                    stroke-width="8" fill="none"
                    stroke-linecap="round"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="offset"
                    style="transition: stroke-dashoffset 1s ease-out"
                />
            </svg>
            <div class="position-absolute d-flex flex-column align-center justify-center" style="inset:0">
                <span class="text-h5 font-weight-bold" :style="{ color: ringColor }">{{ score }}</span>
                <span class="text-caption text-medium-emphasis">/ 100</span>
            </div>
        </div>
        <div class="d-flex align-center mt-3 ga-2">
            <VIcon :icon="icon" size="18" color="primary" />
            <span class="text-body-2 font-weight-medium">{{ label }}</span>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    score: { type: Number, required: true },
    label: { type: String, required: true },
    icon: { type: String, required: true },
})

const radius = 48
const circumference = 2 * Math.PI * radius
const offset = computed(() => circumference - (props.score / 100) * circumference)

const ringColor = computed(() => {
    if (props.score >= 85) return "rgb(76,175,80)"
    if (props.score >= 70) return "rgb(255,193,7)"
    if (props.score >= 50) return "rgb(255,152,0)"
    return "rgb(244,67,54)"
})
</script>
