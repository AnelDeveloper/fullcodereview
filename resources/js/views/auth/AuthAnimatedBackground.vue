<template>
    <div class="auth-bg" />
</template>

<style lang="scss" scoped>
.auth-bg {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    background: #1a1a1a;
}

:global(.v-theme--dark) .auth-bg {
    background: #000000;
}
</style>
