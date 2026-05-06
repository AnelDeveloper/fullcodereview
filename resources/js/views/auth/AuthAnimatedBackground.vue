<template>
    <div class="vibe-bg">
        <div class="grid-overlay" />
        <div class="orb orb-violet" />
        <div class="orb orb-pink" />
        <div class="orb orb-cyan" />
    </div>
</template>

<style lang="scss" scoped>
.vibe-bg {
    position: fixed;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
    z-index: 0;
    background:
        radial-gradient(ellipse at top left, rgba(139, 92, 246, 0.15), transparent 60%),
        radial-gradient(ellipse at bottom right, rgba(236, 72, 153, 0.12), transparent 60%),
        linear-gradient(180deg, #0B0817 0%, #110A24 100%);
}

:global(.v-theme--light) .vibe-bg {
    background:
        radial-gradient(ellipse at top left, rgba(139, 92, 246, 0.22), transparent 60%),
        radial-gradient(ellipse at bottom right, rgba(236, 72, 153, 0.16), transparent 60%),
        linear-gradient(180deg, #FAFAFF 0%, #F0EBFF 100%);
}

.grid-overlay {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(139, 92, 246, 0.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(139, 92, 246, 0.06) 1px, transparent 1px);
    background-size: 64px 64px;
    mask-image: radial-gradient(ellipse at center, black 30%, transparent 80%);
    -webkit-mask-image: radial-gradient(ellipse at center, black 30%, transparent 80%);
}

.orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    animation: float 18s ease-in-out infinite;
    opacity: 0.55;
}

.orb-violet {
    width: 480px; height: 480px;
    background: #8B5CF6;
    top: -120px;
    left: -120px;
}

.orb-pink {
    width: 540px; height: 540px;
    background: #EC4899;
    bottom: -160px;
    right: -160px;
    animation-delay: -6s;
}

.orb-cyan {
    width: 360px; height: 360px;
    background: #06B6D4;
    top: 40%;
    left: 60%;
    animation-delay: -12s;
    opacity: 0.35;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33%      { transform: translate(40px, -30px) scale(1.05); }
    66%      { transform: translate(-30px, 40px) scale(0.95); }
}
</style>
