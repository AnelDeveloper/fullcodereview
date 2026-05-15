<template>
    <VDialog :model-value="modelValue" max-width="520" persistent @update:model-value="onModelUpdate">
        <VCard>
            <VCardItem>
                <VCardTitle>
                    {{ isAudit ? "How was your audit?" : "Send us feedback" }}
                </VCardTitle>
                <VCardSubtitle>
                    <span v-if="isAudit">Your rating helps us tune the next one. Optional.</span>
                    <span v-else>Bug, idea, or anything else — it lands in our support inbox.</span>
                </VCardSubtitle>
            </VCardItem>

            <VCardText>
                <VAlert v-if="sent" type="success" variant="tonal" density="compact" class="mb-3">
                    Thanks — we'll read every word.
                </VAlert>
                <VAlert v-else-if="error" type="error" variant="tonal" density="compact" class="mb-3">
                    {{ error }}
                </VAlert>

                <template v-if="!sent">
                    <!-- Star rating (audit mode only) -->
                    <div v-if="isAudit" class="d-flex flex-column align-center mb-4">
                        <div class="d-flex ga-1 mb-1">
                            <VBtn
                                v-for="n in 5"
                                :key="n"
                                icon
                                variant="text"
                                density="comfortable"
                                @click="rating = n"
                            >
                                <VIcon
                                    :icon="n <= (rating || 0) ? 'tabler-star-filled' : 'tabler-star'"
                                    :color="n <= (rating || 0) ? 'warning' : 'medium-emphasis'"
                                    size="28"
                                />
                            </VBtn>
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ rating ? ratingLabel(rating) : "Tap a star" }}
                        </div>
                    </div>

                    <VTextarea
                        v-model="message"
                        :label="isAudit ? 'Comment (optional unless you tap Send)' : 'How can we help?'"
                        :placeholder="messagePlaceholder"
                        rows="5"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        counter="2000"
                        :maxlength="2000"
                        :error-messages="messageError"
                    />
                </template>
            </VCardText>

            <VCardActions class="px-4 pb-4">
                <VSpacer />
                <VBtn variant="text" :disabled="submitting" @click="close">
                    {{ sent ? "Close" : (isAudit ? "Not now" : "Cancel") }}
                </VBtn>
                <VBtn
                    v-if="!sent"
                    color="primary"
                    :loading="submitting"
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    Send
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>

<script setup>
import { submitFeedback } from "@/utils/codeCheck"

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    mode: { type: String, default: "support" }, // "support" | "audit"
    analysisId: { type: [Number, String, null], default: null },
})

const emit = defineEmits(["update:modelValue", "submitted"])

const isAudit = computed(() => props.mode === "audit")

const rating = ref(null)
const message = ref("")
const submitting = ref(false)
const sent = ref(false)
const error = ref("")
const messageError = ref("")

const messagePlaceholder = computed(() =>
    isAudit.value
        ? "What was useful? What was missing? (optional, but it's the whole reason we ask)"
        : "Describe the issue, idea, or question…",
)

const canSubmit = computed(() => {
    if (isAudit.value) return !!rating.value || message.value.trim().length >= 3
    return message.value.trim().length >= 3
})

const ratingLabel = (n) => {
    return ["Awful", "Meh", "OK", "Good", "Loved it"][n - 1] || ""
}

// Reset state when the dialog reopens, so the next user doesn't see the
// previous submission's "Thanks!" alert or stale fields.
watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            rating.value = null
            message.value = ""
            submitting.value = false
            sent.value = false
            error.value = ""
            messageError.value = ""
        }
    },
)

const submit = async () => {
    if (!canSubmit.value) {
        messageError.value = "Add a quick comment or pick a rating."
        return
    }
    submitting.value = true
    error.value = ""
    messageError.value = ""
    try {
        const payload = {
            type: props.mode,
            message: message.value.trim() || "(no comment)",
            rating: rating.value,
            analysisId: props.analysisId,
        }
        await submitFeedback(payload)
        sent.value = true
        emit("submitted", payload)
        // Auto-close after a beat so the user sees the success state.
        setTimeout(() => close(), 1400)
    } catch (e) {
        error.value = e?.data?.message || e.message || "Couldn't send. Try again."
    } finally {
        submitting.value = false
    }
}

const close = () => {
    emit("update:modelValue", false)
}

const onModelUpdate = (v) => {
    if (!v && !submitting.value) emit("update:modelValue", false)
}
</script>
