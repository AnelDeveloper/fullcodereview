<template>
    <component :is="VerticalNavLayout" :nav-items="navItems">
        <slot />
    </component>
</template>

<script setup>
import { VerticalNavLayout } from "@layouts"
import navItems from "@/navigation/vertical"
</script>
