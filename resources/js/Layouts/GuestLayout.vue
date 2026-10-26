<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { Link } from '@inertiajs/vue3'

const root = ref(null)

// Fireflies: fixed values, so the scene looks the same on every load
const fireflies = Array.from({ length: 22 }, (_, i) => ({
    left: (i * 37 + 11) % 100,
    top: 48 + ((i * 53) % 44),
    size: 3 + (i % 4),
    delay: (i % 9) * 0.9,
    dur: 7 + (i % 6) * 2,
}))

// Mouse parallax (smoothed, and it stops itself when the mouse is still)
let raf = 0
let tx = 0, ty = 0, cx = 0, cy = 0

const tick = () => {
    cx += (tx - cx) * 0.06
    cy += (ty - cy) * 0.06
    root.value?.style.setProperty('--mx', cx.toFixed(4))
    root.value?.style.setProperty('--my', cy.toFixed(4))
    raf = (Math.abs(tx - cx) > 0.001 || Math.abs(ty - cy) > 0.001)
        ? requestAnimationFrame(tick)
        : 0
}

const onMove = (e) => {
    tx = (e.clientX / window.innerWidth - 0.5) * 2
    ty = (e.clientY / window.innerHeight - 0.5) * 2
    if (!raf) raf = requestAnimationFrame(tick)
}

onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
    window.addEventListener('mousemove', onMove)
})

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', onMove)
    cancelAnimationFrame(raf)
})
</script>

<template>
    <div class="guest-layout" ref="root">

        <!-- Shared shapes and gradients (used by the layers below) -->
        <svg width="0" height="0" style="position:absolute" aria-hidden="true">
            <defs>
                <linearGradient id="cm-sky" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#020b07"/>
                    <stop offset="55%" stop-color="#0a2216"/>
                    <stop offset="100%" stop-color="#17493a"/>
                </linearGradient>
                <radialGradient id="cm-moon-glow" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#d1fae5" stop-opacity="0.45"/>
                    <stop offset="100%" stop-color="#d1fae5" stop-opacity="0"/>
                </radialGradient>
                <linearGradient id="cm-ray" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#d1fae5" stop-opacity="0.32"/>
                    <stop offset="100%" stop-color="#d1fae5" stop-opacity="0"/>
                </linearGradient>
                <radialGradient id="cm-lamp" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#fcd34d" stop-opacity="0.55"/>
                    <stop offset="100%" stop-color="#f59e0b" stop-opacity="0"/>
                </radialGradient>
                <path id="cm-cypress" d="M0 -380 C34 -290 40 -170 32 0 L-32 0 C-40 -170 -34 -290 0 -380 Z"/>
                <path id="cm-stone" d="M-22 0 V-58 A22 22 0 0 1 22 -58 V0 Z"/>
                <g id="cm-cross">
                    <rect x="-5" y="-92" width="10" height="92"/>
                    <rect x="-25" y="-70" width="50" height="10"/>
                </g>
            </defs>
        </svg>

        <!-- ═════════ SCENE (slow camera push) ═════════ -->
        <div class="scene" aria-hidden="true">

            <!-- Layer 1: sky -->
            <svg class="layer" style="--d: 0.3" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice">
                <rect width="1440" height="900" fill="url(#cm-sky)"/>

                <g fill="#d1fae5">
                    <circle class="star" cx="120" cy="90" r="1.5" opacity="0.7"/>
                    <circle class="star star-b" cx="300" cy="170" r="1.2" opacity="0.5"/>
                    <circle class="star" cx="470" cy="70" r="1.6" opacity="0.7"/>
                    <circle class="star star-b" cx="640" cy="140" r="1.2" opacity="0.5"/>
                    <circle class="star" cx="820" cy="60" r="1.4" opacity="0.6"/>
                    <circle class="star star-b" cx="930" cy="200" r="1.2" opacity="0.5"/>
                    <circle class="star" cx="1030" cy="90" r="1.5" opacity="0.7"/>
                    <circle class="star star-b" cx="1340" cy="260" r="1.3" opacity="0.5"/>
                    <circle class="star" cx="1390" cy="80" r="1.4" opacity="0.6"/>
                    <circle class="star star-b" cx="60" cy="300" r="1.2" opacity="0.4"/>
                    <circle class="star" cx="720" cy="250" r="1.1" opacity="0.4"/>
                </g>

                <!-- Moon glow + rays -->
                <circle class="moon-glow" cx="1180" cy="170" r="190" fill="url(#cm-moon-glow)"/>
                <g class="rays">
                    <polygon points="1180,170 760,900 900,900" fill="url(#cm-ray)"/>
                    <polygon points="1180,170 960,900 1060,900" fill="url(#cm-ray)"/>
                    <polygon points="1180,170 1180,900 1290,900" fill="url(#cm-ray)"/>
                    <polygon points="1180,170 1330,900 1460,900" fill="url(#cm-ray)"/>
                </g>

                <!-- Moon -->
                <circle cx="1180" cy="170" r="52" fill="#e8f5ec"/>
                <circle cx="1163" cy="155" r="9" fill="#c6e3cf" opacity="0.7"/>
                <circle cx="1200" cy="188" r="12" fill="#c6e3cf" opacity="0.55"/>
                <circle cx="1196" cy="147" r="5" fill="#c6e3cf" opacity="0.6"/>

                <!-- Birds crossing the moon -->
                <g class="birds" fill="none" stroke="#031009" stroke-width="2" stroke-linecap="round">
                    <path d="M0 0 q9 -9 18 0 q9 -9 18 0" transform="translate(1560 150)"/>
                    <path d="M0 0 q7 -7 14 0 q7 -7 14 0" transform="translate(1600 178)"/>
                    <path d="M0 0 q6 -6 12 0 q6 -6 12 0" transform="translate(1640 160)"/>
                </g>
            </svg>

            <!-- Layer 2: far hills, trees, stones -->
            <svg class="layer" style="--d: 0.6" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice">
                <path d="M0 640 C200 590 380 620 560 600 C760 580 940 630 1140 600 C1280 580 1380 600 1440 610 L1440 900 L0 900 Z" fill="#0f2f20"/>
                <use href="#cm-cypress" transform="translate(300 650) scale(0.55)" fill="#082015"/>
                <use href="#cm-cypress" transform="translate(1110 645) scale(0.6)" fill="#082015"/>
                <g fill="#0a2417" stroke="#2f7a52" stroke-opacity="0.3" stroke-width="1" opacity="0.85">
                    <use href="#cm-stone" transform="translate(400 690) scale(0.6)"/>
                    <use href="#cm-cross" transform="translate(470 692) scale(0.6)"/>
                    <use href="#cm-stone" transform="translate(540 688) scale(0.55)"/>
                    <use href="#cm-stone" transform="translate(900 690) scale(0.6)"/>
                    <use href="#cm-cross" transform="translate(970 688) scale(0.6)"/>
                    <use href="#cm-stone" transform="translate(1040 692) scale(0.55)"/>
                </g>
            </svg>

            <div class="fog fog-far"></div>

            <!-- Layer 3: mid hills and big trees -->
            <svg class="layer" style="--d: 1" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice">
                <path d="M0 700 C180 665 360 690 540 675 C760 655 980 700 1200 680 C1320 668 1400 680 1440 685 L1440 900 L0 900 Z" fill="#0b2417"/>
                <use href="#cm-cypress" transform="translate(130 740) scale(1.15)" fill="#051409"/>
                <use href="#cm-cypress" transform="translate(235 735) scale(0.8)" fill="#061a0e"/>
                <use href="#cm-cypress" transform="translate(1310 740) scale(1.2)" fill="#051409"/>
                <use href="#cm-cypress" transform="translate(1205 735) scale(0.75)" fill="#061a0e"/>
            </svg>

            <div class="fog fog-near"></div>

            <!-- Layer 4: foreground ground, stones, lanterns -->
            <svg class="layer" style="--d: 1.7" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice">
                <path d="M0 780 C240 750 480 775 720 765 C960 755 1200 780 1440 760 L1440 900 L0 900 Z" fill="#071a10"/>
                <g fill="#0b2014" stroke="#3a8d60" stroke-opacity="0.35" stroke-width="1.2">
                    <use href="#cm-stone" transform="translate(300 815) scale(1.15)"/>
                    <use href="#cm-cross" transform="translate(430 822) scale(1.1)"/>
                    <use href="#cm-stone" transform="translate(560 818) scale(0.95)"/>
                    <use href="#cm-stone" transform="translate(880 818) scale(0.95)"/>
                    <use href="#cm-cross" transform="translate(1010 822) scale(1.2)"/>
                    <use href="#cm-stone" transform="translate(1140 815) scale(1.25)"/>
                </g>

                <!-- Lanterns -->
                <g transform="translate(190 0)">
                    <rect x="-2" y="800" width="4" height="52" fill="#0b2014"/>
                    <rect x="-9" y="786" width="18" height="18" rx="3" fill="#0b2014" stroke="#3a8d60" stroke-opacity="0.35"/>
                    <circle class="flame" cx="0" cy="795" r="90" fill="url(#cm-lamp)"/>
                    <circle class="flame" cx="0" cy="795" r="3.5" fill="#fde68a"/>
                </g>
                <g transform="translate(1262 0)">
                    <rect x="-2" y="805" width="4" height="47" fill="#0b2014"/>
                    <rect x="-9" y="791" width="18" height="18" rx="3" fill="#0b2014" stroke="#3a8d60" stroke-opacity="0.35"/>
                    <circle class="flame flame-b" cx="0" cy="800" r="90" fill="url(#cm-lamp)"/>
                    <circle class="flame flame-b" cx="0" cy="800" r="3.5" fill="#fde68a"/>
                </g>

                <rect y="860" width="1440" height="40" fill="#04110a"/>
            </svg>

            <!-- Fireflies -->
            <div class="fireflies">
                <span
                    v-for="(f, i) in fireflies"
                    :key="i"
                    class="firefly"
                    :style="{
                        left: f.left + '%',
                        top: f.top + '%',
                        width: f.size + 'px',
                        height: f.size + 'px',
                        animationDelay: f.delay + 's',
                        animationDuration: f.dur + 's',
                    }"
                ></span>
            </div>
        </div>

        <!-- ═════════ FILM LOOK ═════════ -->
        <div class="vignette" aria-hidden="true"></div>
        <div class="grain" aria-hidden="true"></div>
        <div class="bar bar-top" aria-hidden="true"></div>
        <div class="bar bar-bottom" aria-hidden="true"></div>
        <div class="fade-in" aria-hidden="true"></div>

        <!-- ═════════ CONTENT ═════════ -->
        <div class="guest-eyebrow">
            <span class="eyebrow-line"></span>
            <span>In loving memory</span>
            <span class="eyebrow-line"></span>
        </div>

        <Link href="/" class="guest-brand">
            <span class="guest-brand-icon">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#bbf7d0" stroke-width="1.8" stroke-linecap="round">
                    <path d="M12 3v18M7 8h10"/>
                </svg>
            </span>
            <span class="guest-brand-text">Cemetery Records System</span>
        </Link>

        <div class="guest-card">
            <slot />
        </div>

        <p class="guest-footer">Honoring every name, preserving every memory</p>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap');

.guest-layout {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #020b07;
    position: relative;
    overflow: hidden;
    font-family: 'Outfit', sans-serif;
    padding: 1.5rem;
}

/* ── Scene and depth layers ── */
.scene {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    transform-origin: 50% 62%;
    animation: push 45s ease-in-out infinite alternate;
}
.layer {
    position: absolute;
    inset: -5%;
    width: 110%;
    height: 110%;
    display: block;
    will-change: transform;
    transform: translate3d(
        calc(var(--mx, 0) * var(--d) * -14px),
        calc(var(--my, 0) * var(--d) * -8px),
        0
    );
}

/* ── Fog ── */
.fog {
    position: absolute;
    left: -25%;
    width: 150%;
    background: radial-gradient(ellipse at center, rgba(209, 250, 229, 0.17), rgba(209, 250, 229, 0) 66%);
}
.fog-far  { bottom: 26%; height: 26%; animation: drift 30s ease-in-out infinite alternate; }
.fog-near { bottom: 4%;  height: 30%; animation: drift 22s ease-in-out infinite alternate-reverse; }

/* ── Fireflies ── */
.fireflies { position: absolute; inset: 0; }
.firefly {
    position: absolute;
    border-radius: 50%;
    background: #d9f99d;
    box-shadow: 0 0 10px 3px rgba(190, 242, 100, 0.55);
    opacity: 0;
    animation: float 10s ease-in-out infinite;
}

/* ── SVG animations ── */
.star { animation: twinkle 4s ease-in-out infinite; }
.star-b { animation-duration: 6s; animation-delay: 1.5s; }
.moon-glow { animation: moonPulse 9s ease-in-out infinite; }
.rays { mix-blend-mode: screen; animation: rayPulse 10s ease-in-out infinite; }
.birds { animation: fly 38s linear 5s infinite; }
.flame { animation: flicker 2.6s ease-in-out infinite; }
.flame-b { animation-duration: 3.3s; animation-delay: 0.7s; }

/* ── Film look ── */
.vignette {
    position: absolute;
    inset: 0;
    z-index: 3;
    pointer-events: none;
    background: radial-gradient(ellipse at center, rgba(0, 0, 0, 0) 42%, rgba(0, 0, 0, 0.72) 100%);
}
.grain {
    position: absolute;
    inset: -50%;
    width: 200%;
    height: 200%;
    z-index: 4;
    pointer-events: none;
    opacity: 0.1;
    mix-blend-mode: overlay;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='220' height='220'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");
    animation: grain 0.8s steps(6) infinite;
}
.bar {
    position: absolute;
    left: 0;
    right: 0;
    height: 6vh;
    background: #000;
    z-index: 5;
    pointer-events: none;
}
.bar-top    { top: 0;    animation: barOpenTop 2s cubic-bezier(0.7, 0, 0.2, 1) both; }
.bar-bottom { bottom: 0; animation: barOpenBottom 2s cubic-bezier(0.7, 0, 0.2, 1) both; }
.fade-in {
    position: absolute;
    inset: 0;
    z-index: 6;
    background: #000;
    opacity: 0;
    pointer-events: none;
    animation: fadeIn 1.8s ease-out both;
}

/* ── Content ── */
.guest-eyebrow {
    position: relative;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 10px;
    font-size: 0.68rem;
    font-weight: 500;
    letter-spacing: 0.42em;
    text-transform: uppercase;
    color: rgba(187, 247, 208, 0.6);
    animation: rise 1.2s cubic-bezier(0.16, 1, 0.3, 1) 0.9s both;
}
.eyebrow-line {
    width: 44px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(187, 247, 208, 0.5), transparent);
}

.guest-brand {
    position: relative;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
    text-decoration: none;
    animation: rise 1.2s cubic-bezier(0.16, 1, 0.3, 1) 1.05s both;
}
.guest-brand-icon {
    width: 40px;
    height: 40px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(187, 247, 208, 0.3);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
}
.guest-brand-text {
    font-size: 1.1rem;
    font-weight: 600;
    color: #ecfdf5;
    letter-spacing: 0.04em;
    text-shadow: 0 2px 18px rgba(0, 0, 0, 0.6);
}

.guest-card {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 440px;
    padding: 2rem 2.25rem;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    box-shadow:
        0 30px 80px rgba(0, 0, 0, 0.6),
        0 0 90px rgba(34, 197, 94, 0.14);
    animation: cardReveal 1.3s cubic-bezier(0.16, 1, 0.3, 1) 1.25s both;
}
.guest-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 12%;
    right: 12%;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(34, 163, 90, 0.8), transparent);
}

.guest-footer {
    position: relative;
    z-index: 10;
    margin-top: 24px;
    font-size: 0.75rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(187, 247, 208, 0.45);
    text-align: center;
    animation: rise 1.2s cubic-bezier(0.16, 1, 0.3, 1) 1.6s both;
}

/* ── Keyframes ── */
@keyframes push       { from { transform: scale(1); } to { transform: scale(1.07); } }
@keyframes drift      { from { transform: translateX(-60px); } to { transform: translateX(60px); } }
@keyframes twinkle    { 0%, 100% { opacity: 0.2; } 50% { opacity: 0.95; } }
@keyframes moonPulse  { 0%, 100% { opacity: 0.8; } 50% { opacity: 1; } }
@keyframes rayPulse   { 0%, 100% { opacity: 0.55; } 50% { opacity: 1; } }
@keyframes fly        { 0% { transform: translateX(0); } 70%, 100% { transform: translateX(-1950px); } }
@keyframes flicker    { 0%, 100% { opacity: 0.85; } 20% { opacity: 0.6; } 40% { opacity: 1; } 60% { opacity: 0.7; } 80% { opacity: 0.95; } }
@keyframes float {
    0%   { opacity: 0;    transform: translate(0, 0); }
    15%  { opacity: 0.95; }
    50%  { opacity: 0.45; transform: translate(26px, -34px); }
    85%  { opacity: 0.9; }
    100% { opacity: 0;    transform: translate(-18px, -70px); }
}
@keyframes grain {
    0%   { transform: translate(0, 0); }
    20%  { transform: translate(-6%, 4%); }
    40%  { transform: translate(5%, -7%); }
    60%  { transform: translate(-4%, -3%); }
    80%  { transform: translate(7%, 5%); }
    100% { transform: translate(0, 0); }
}
@keyframes barOpenTop    { from { height: 50vh; } to { height: 6vh; } }
@keyframes barOpenBottom { from { height: 50vh; } to { height: 6vh; } }
@keyframes fadeIn        { from { opacity: 1; } to { opacity: 0; } }
@keyframes rise          { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
@keyframes cardReveal    { from { opacity: 0; transform: translateY(30px) scale(0.96); } to { opacity: 1; transform: translateY(0) scale(1); } }

/* ── Accessibility and small screens ── */
@media (prefers-reduced-motion: reduce) {
    .scene, .fog, .firefly, .star, .moon-glow, .rays, .birds, .flame,
    .grain, .bar, .fade-in, .guest-eyebrow, .guest-brand, .guest-card, .guest-footer {
        animation: none !important;
    }
    .firefly { opacity: 0.6; }
}
@media (max-width: 480px) {
    .guest-card { padding: 1.5rem; border-radius: 18px; }
    .bar { height: 4vh; }
    .guest-eyebrow { letter-spacing: 0.28em; }
}
</style>
