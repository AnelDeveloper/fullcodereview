<template>
    <IconBtn @click="toggleTheme">
        <VIcon :icon="icon" size="22" />
    </IconBtn>
</template>

<script setup>
import { useTheme } from "vuetify"
import { useConfigStore } from "@core/stores/config"

const vuetifyTheme = useTheme()
const configStore = useConfigStore()

const icon = computed(() =>
    vuetifyTheme.global.current.value.dark ? "tabler-sun" : "tabler-moon"
)

const toggleTheme = () => {
    const newTheme = vuetifyTheme.global.current.value.dark ? "light" : "dark"
    // Flip Vuetify synchronously so CSS vars switch in the same frame as
    // any reactive components watching configStore.theme. Cookie write
    // (via the watcher in initConfigStore) still happens, just doesn't
    // gate the visual switch.
    vuetifyTheme.global.name.value = newTheme
    configStore.theme = newTheme
}
</script>
