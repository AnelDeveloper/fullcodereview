<template>
    <VLocaleProvider>
        <VApp :style="`--v-global-theme-primary: ${hexToRgb(global.current.value.colors.primary)}`">
            <RouterView />
        </VApp>
    </VLocaleProvider>
</template>

<script setup>
import initCore from "@core/initCore"
import { initConfigStore } from "@core/stores/config"
import { hexToRgb } from "@core/utils/colorConverter"
import { useTheme } from "vuetify"

const { global } = useTheme()

initCore()
initConfigStore()
</script>
