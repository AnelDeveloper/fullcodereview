<template>
    <div class="auth-bg" />
</template>

<style lang="scss" scoped>
.auth-bg {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    background: #0B0817;
}

:global(.v-theme--light) .auth-bg {
    background: #FAFAFA;
}
</style>
