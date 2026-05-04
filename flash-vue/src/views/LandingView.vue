<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useLayoutStore } from '@/stores/layout'
import { useAuthStore } from '@/stores/auth'
import { useSeo } from '@/composables/useSeo'
import { getAuthenticatedHome } from '@/utils/auth'
import BrandLogo from '@/components/branding/BrandLogo.vue'

const { t } = useI18n()
const router = useRouter()
const layout = useLayoutStore()
const auth = useAuthStore()

useSeo({
  title: computed(() => t('seo.homeTitle')),
  description: computed(() => t('seo.homeDescription')),
  path: '/',
  jsonLd: {
    '@context': 'https://schema.org',
    '@type': 'WebApplication',
    name: 'Klek AI',
    url: 'https://klek.studio',
    applicationCategory: 'DesignApplication',
    operatingSystem: 'Web',
    description: 'AI-powered platform for generating stunning images, creative designs, and visual content.',
    offers: { '@type': 'Offer', price: '0', priceCurrency: 'USD' },
  },
})

const steps = computed(() => [
  { num: '1', icon: 'pi pi-pencil', title: t('landing.step1Title'), desc: t('landing.step1Desc') },
  { num: '2', icon: 'pi pi-cog', title: t('landing.step2Title'), desc: t('landing.step2Desc') },
  { num: '3', icon: 'pi pi-download', title: t('landing.step3Title'), desc: t('landing.step3Desc') },
])

const heroHighlights = computed(() => [
  t('landing.proof1Value'),
  t('landing.proof2Value'),
  t('landing.proof3Value'),
])

const primaryActionLabel = computed(() => auth.isAuthenticated ? t('landing.chat') : t('client.startNow'))
const primaryActionIcon = computed(() => auth.isAuthenticated ? 'pi pi-comments' : 'pi pi-arrow-right')

const landingImages = [
  '/landing-images/landing-01.jpg',
  '/landing-images/landing-02.jpg',
  '/landing-images/landing-03.jpg',
  '/landing-images/landing-04.jpg',
  '/landing-images/landing-05.jpg',
  '/landing-images/landing-06.jpg',
  '/landing-images/landing-07.jpg',
  '/landing-images/landing-08.jpg',
  '/landing-images/landing-09.jpg',
]

// Scroll reveal
let observer: IntersectionObserver | null = null

onMounted(() => {
  observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('revealed')
          observer?.unobserve(entry.target)
        }
      })
    },
    { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
  )
  document.querySelectorAll('.reveal').forEach((el) => observer?.observe(el))
})

onBeforeUnmount(() => {
  observer?.disconnect()
})

function goRegister() {
  if (auth.isAuthenticated) {
    router.push(getAuthenticatedHome(auth.user))
  } else {
    router.push({ name: 'register' })
  }
}

function goPricing() {
  router.push({ name: 'pricing' })
}

function goLogin() {
  router.push({ name: 'login' })
}

function goChat() {
  router.push({ name: 'chat' })
}

function goPrimary() {
  if (auth.isAuthenticated) {
    goChat()
  } else {
    goRegister()
  }
}
</script>

<template>
  <div class="landing-page" :class="{ 'landing-dark': layout.darkMode }">
    <!-- ═══ Navbar ═══ -->
    <nav class="landing-nav">
      <div class="nav-inner">
        <div class="nav-brand">
          <BrandLogo class="nav-logo" />
          <span class="nav-brand-text">Klek AI</span>
        </div>
        <div class="nav-links">
          <router-link to="/pricing" class="nav-link">{{ t('landing.viewPricing') }}</router-link>
        </div>
        <div class="nav-actions">
          <button class="nav-icon-btn" @click="layout.toggleDarkMode()" :title="t('client.toggleTheme')">
            <i :class="layout.darkMode ? 'pi pi-sun' : 'pi pi-moon'" />
          </button>
          <button class="nav-lang-btn" @click="layout.toggleLocale()">
            {{ layout.locale === 'ar' ? 'English' : 'العربية' }}
          </button>
          <template v-if="!auth.isAuthenticated">
            <button class="nav-login-btn" @click="goLogin">{{ t('landing.login') }}</button>
          </template>
          <button type="button" class="nav-cta" @click="goPrimary">
            <i :class="auth.isAuthenticated ? 'pi pi-comments' : 'pi pi-sparkles'" />
            <span>{{ primaryActionLabel }}</span>
          </button>
        </div>
      </div>
    </nav>

    <!-- ═══ Hero ═══ -->
    <section class="hero-section">
      <div class="hero-media-bg" aria-hidden="true">
        <div class="hero-media-grid">
          <img
            v-for="(image, index) in landingImages"
            :key="image"
            :src="image"
            :class="['hero-media-img', `hero-media-img-${index + 1}`]"
            alt=""
            decoding="async"
          >
        </div>
        <div class="hero-media-shade" />
      </div>
      <div class="hero-shell hero-shell-simple">
        <div class="hero-content hero-content-centered reveal">
          <h1 class="hero-title">
            {{ t('landing.heroTitle') }}
            <span class="hero-highlight">{{ t('landing.heroTitleHighlight') }}</span>
          </h1>
          <p class="hero-sub">{{ t('landing.heroSub') }}</p>
          <div class="hero-actions">
            <button type="button" class="hero-btn hero-btn-primary" @click="goPrimary">
              <i :class="primaryActionIcon" />
              <span>{{ primaryActionLabel }}</span>
            </button>
            <button type="button" class="hero-btn hero-btn-secondary" @click="goPricing">
              <i class="pi pi-tag" />
              <span>{{ t('landing.viewPricing') }}</span>
            </button>
          </div>

          <div class="hero-highlight-row">
            <span v-for="item in heroHighlights" :key="item" class="hero-highlight-pill">
              {{ item }}
            </span>
          </div>
        </div>
      </div>
    </section>

    <section class="simple-steps-section">
      <div class="section-inner">
        <div class="simple-steps-grid">
          <div v-for="(s, i) in steps" :key="i" class="step-card reveal" :style="{ '--reveal-delay': i * 0.1 + 's' }">
            <div class="step-num">{{ s.num }}</div>
            <div class="step-icon-wrap">
              <i :class="s.icon" />
            </div>
            <h3 class="step-title">{{ s.title }}</h3>
            <p class="step-desc">{{ s.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ Footer ═══ -->
    <footer class="landing-footer">
      <div class="footer-inner">
        <div class="footer-brand">
          <BrandLogo class="footer-logo" />
          <span>Klek AI</span>
        </div>
        <div class="footer-links">
          <router-link to="/privacy-policy" class="footer-legal-link">Privacy Policy</router-link>
          <span class="footer-sep">·</span>
          <a href="mailto:klek.studio@gmail.com" class="footer-legal-link">klek.studio@gmail.com</a>
        </div>
        <span class="footer-rights">{{ t('landing.footerRights') }}</span>
      </div>
    </footer>
  </div>
</template>

<style scoped>
/* ═══ Scroll reveal ═══ */
.reveal {
  opacity: 0;
  transform: translateY(24px);
  transition: opacity 0.6s ease, transform 0.6s ease;
  transition-delay: var(--reveal-delay, 0s);
}
.reveal.revealed {
  opacity: 1;
  transform: translateY(0);
}

/* ═══ Base ═══ */
.landing-page {
  --lp-bg: #f5f5f7;
  --lp-surface: #eeeef0;
  --lp-card: rgba(255, 255, 255, 0.75);
  --lp-card-border: rgba(0, 0, 0, 0.08);
  --lp-text: #1a1a2e;
  --lp-text-dim: #555568;
  --lp-text-muted: #8888a0;
  --lp-accent: #7c5ce0;
  --lp-accent-dim: rgba(124, 92, 224, 0.1);

  min-height: 100vh;
  background: var(--lp-bg);
  color: var(--lp-text);
  overflow-x: hidden;
  position: relative;
  isolation: isolate;
}

.landing-page.landing-dark {
  --lp-bg: #07070e;
  --lp-surface: #0b0b16;
  --lp-card: rgba(14, 14, 28, 0.65);
  --lp-card-border: rgba(255, 255, 255, 0.06);
  --lp-text: #dddde4;
  --lp-text-dim: #8b8b9e;
  --lp-text-muted: #55556a;
  --lp-accent: #9580ff;
  --lp-accent-dim: rgba(149, 128, 255, 0.12);
}

/* ═══ Ambient background ═══ */
.landing-page::before {
  content: '';
  position: absolute;
  inset: 0 0 auto;
  height: 860px;
  pointer-events: none;
  z-index: 0;
  background:
    radial-gradient(circle at 50% 8%, rgba(120, 90, 220, 0.1) 0%, transparent 34%),
    radial-gradient(circle at 88% 42%, rgba(60, 100, 200, 0.07) 0%, transparent 28%),
    radial-gradient(circle at 12% 64%, rgba(140, 80, 200, 0.06) 0%, transparent 24%);
  opacity: 0.9;
}

.landing-page.landing-dark::before {
  background:
    radial-gradient(circle at 50% 8%, rgba(120, 90, 220, 0.22) 0%, transparent 34%),
    radial-gradient(circle at 88% 42%, rgba(60, 100, 200, 0.14) 0%, transparent 28%),
    radial-gradient(circle at 12% 64%, rgba(140, 80, 200, 0.12) 0%, transparent 24%);
}

/* ═══ Navbar ═══ */
.landing-nav {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: rgba(245, 245, 247, 0.92);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.landing-page.landing-dark .landing-nav {
  background: rgba(7, 7, 14, 0.92);
  border-bottom-color: rgba(255, 255, 255, 0.05);
}
.nav-inner {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 24px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.nav-brand {
  display: flex;
  align-items: center;
  gap: 10px;
}
.nav-logo {
  width: 38px;
  height: 38px;
  object-fit: contain;
  filter: drop-shadow(0 0 8px rgba(149, 128, 255, 0.35));
}
.nav-brand-text {
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--lp-text);
}
.nav-links {
  display: flex;
  align-items: center;
  gap: 8px;
}
.nav-link {
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--lp-text-dim);
  padding: 6px 12px;
  border-radius: 8px;
  transition: color 0.2s, background 0.2s;
}
.nav-link:hover {
  color: var(--lp-text);
  background: rgba(0, 0, 0, 0.05);
}
.landing-page.landing-dark .nav-link:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.05);
}
.nav-icon-btn {
  width: 34px;
  height: 34px;
  border: none;
  background: none;
  color: var(--lp-text-dim);
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.88rem;
  transition: color 0.2s, background 0.2s;
}
.nav-icon-btn:hover {
  color: var(--lp-text);
  background: rgba(0, 0, 0, 0.05);
}

.landing-page.landing-dark .nav-icon-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.05);
}

.nav-lang-btn {
  border: none;
  background: none;
  color: var(--lp-text-dim);
  cursor: pointer;
  font-size: 0.78rem;
  font-weight: 600;
  padding: 6px 12px;
  border-radius: 8px;
  transition: color 0.2s, background 0.2s;
}
.nav-lang-btn:hover {
  color: var(--lp-text);
  background: rgba(0, 0, 0, 0.05);
}

.landing-page.landing-dark .nav-lang-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.05);
}

.nav-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.nav-login-btn {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--lp-text-dim);
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px 14px;
  border-radius: 8px;
  transition: color 0.2s, background 0.2s;
}
.nav-login-btn:hover {
  color: var(--lp-text);
  background: rgba(0, 0, 0, 0.05);
}

.landing-page.landing-dark .nav-login-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.05);
}
.nav-cta {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 34px;
  padding: 0 14px;
  border: 1px solid rgba(85, 245, 190, 0.15);
  border-radius: 9px;
  background: #55f5be;
  color: #072116;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: transform 0.2s, filter 0.2s;
}
.nav-cta:hover {
  filter: brightness(1.03);
  transform: translateY(-1px);
}

/* ═══ Hero ═══ */
.hero-section {
  position: relative;
  z-index: 1;
  min-height: 520px;
  padding: 148px 24px 86px;
  overflow: hidden;
  isolation: isolate;
  background: #08090f;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  align-items: center;
}
.hero-media-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  overflow: hidden;
  background: #090a10;
}
.hero-media-grid {
  position: absolute;
  inset: -28px -26px -42px;
  display: grid;
  grid-template-columns: 1.05fr 0.82fr 1fr 0.82fr 1.05fr;
  grid-template-rows: repeat(2, minmax(210px, 1fr));
  gap: 16px;
  transform: rotate(-4deg) scale(1.04);
  transform-origin: center;
  filter: blur(2px) saturate(1.08) brightness(0.88);
  opacity: 0.92;
}
.hero-media-img {
  width: 100%;
  height: 100%;
  min-height: 0;
  object-fit: cover;
  border-radius: 18px;
  box-shadow: 0 20px 55px rgba(0, 0, 0, 0.34);
}
.hero-media-img-1 { grid-column: 1; grid-row: 1; }
.hero-media-img-2 { grid-column: 1; grid-row: 2; }
.hero-media-img-3 { grid-column: 2; grid-row: 1 / span 2; }
.hero-media-img-4 { grid-column: 3; grid-row: 1; }
.hero-media-img-5 { grid-column: 3; grid-row: 2; }
.hero-media-img-6 { grid-column: 4; grid-row: 1 / span 2; }
.hero-media-img-7 { grid-column: 5; grid-row: 1; }
.hero-media-img-8 { grid-column: 5; grid-row: 2; }
.hero-media-img-9 { grid-column: 4 / span 2; grid-row: 2; opacity: 0.86; }
.hero-media-img-1,
.hero-media-img-4,
.hero-media-img-8 {
  transform: translateY(22px);
}
.hero-media-img-3,
.hero-media-img-6,
.hero-media-img-9 {
  transform: translateY(-14px);
}
.hero-media-shade {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse at center, rgba(6, 7, 12, 0.74) 0%, rgba(6, 7, 12, 0.54) 31%, rgba(6, 7, 12, 0.2) 56%, rgba(6, 7, 12, 0.28) 100%),
    linear-gradient(180deg, rgba(6, 7, 12, 0.48) 0%, rgba(6, 7, 12, 0.24) 46%, rgba(6, 7, 12, 0.62) 82%, var(--lp-bg) 100%);
}
.hero-shell {
  position: relative;
  z-index: 1;
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(340px, 0.92fr);
  gap: 28px;
  align-items: center;
}
.hero-shell-simple {
  max-width: 920px;
  grid-template-columns: 1fr;
}
.hero-content {
  position: relative;
  text-align: start;
  max-width: 680px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 18px;
}
.hero-content-centered {
  max-width: 760px;
  margin: 0 auto;
  text-align: center;
  align-items: center;
  padding: 34px 34px 30px;
  border-radius: 28px;
  background: radial-gradient(ellipse at center, rgba(6, 7, 12, 0.52) 0%, rgba(6, 7, 12, 0.32) 58%, rgba(6, 7, 12, 0) 76%);
}
.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 16px;
  border-radius: 100px;
  background: var(--lp-accent-dim);
  border: 1px solid rgba(149, 128, 255, 0.15);
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--lp-accent);
}
.hero-badge i { font-size: 0.68rem; }
.hero-title {
  font-size: 3rem;
  font-weight: 900;
  line-height: 1.1;
  letter-spacing: 0;
  margin: 0;
  color: #fff;
  text-shadow: 0 3px 24px rgba(0, 0, 0, 0.45);
}
.hero-highlight {
  background: linear-gradient(135deg, #ffffff 0%, #55f5be 42%, #b8a4ff 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.hero-sub {
  font-size: 1rem;
  color: rgba(255, 255, 255, 0.86);
  line-height: 1.7;
  margin: 0;
  max-width: 620px;
  text-shadow: 0 2px 18px rgba(0, 0, 0, 0.42);
}
.hero-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 8px;
}
.hero-btn,
.cta-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: box-shadow 0.25s, transform 0.25s, background 0.25s, border-color 0.25s, color 0.25s;
}
.hero-btn-primary {
  font-size: 0.88rem;
  font-weight: 700;
  border-radius: 10px;
  padding: 10px 28px;
  background: linear-gradient(135deg, #7c5ce0, #9580ff);
  border-color: rgba(149, 128, 255, 0.4);
  color: #fff;
  box-shadow: 0 4px 20px rgba(149, 128, 255, 0.2);
}
.hero-btn-primary:hover {
  box-shadow: 0 6px 28px rgba(149, 128, 255, 0.35);
  transform: translateY(-1px);
}
.hero-highlight-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
  margin-top: 8px;
}

.hero-highlight-pill {
  padding: 9px 14px;
  border-radius: 999px;
  background: rgba(8, 10, 18, 0.52);
  border: 1px solid rgba(255, 255, 255, 0.16);
  backdrop-filter: blur(12px);
  font-size: 0.76rem;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.92);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18);
}
.hero-proof-grid {
  width: 100%;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-top: 10px;
}

.hero-proof-card {
  padding: 14px 16px;
  border-radius: 16px;
  background: var(--lp-card);
  border: 1px solid var(--lp-card-border);
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.hero-proof-card strong {
  font-size: 0.92rem;
  color: var(--lp-text);
}

.hero-proof-card span {
  font-size: 0.74rem;
  line-height: 1.55;
  color: var(--lp-text-dim);
}

.hero-panel {
  padding: 24px;
  border-radius: 24px;
  background: var(--lp-card);
  border: 1px solid var(--lp-card-border);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
  backdrop-filter: blur(10px);
}

.hero-panel-kicker {
  display: inline-flex;
  padding: 6px 10px;
  border-radius: 999px;
  background: var(--lp-accent-dim);
  color: var(--lp-accent);
  font-size: 0.72rem;
  font-weight: 700;
}

.hero-panel-title {
  margin: 14px 0 0;
  font-size: 1.35rem;
  line-height: 1.25;
  color: var(--lp-text);
}

.hero-panel-sub {
  margin: 10px 0 0;
  font-size: 0.86rem;
  line-height: 1.7;
  color: var(--lp-text-dim);
}

.hero-panel-list {
  display: grid;
  gap: 12px;
  margin-top: 18px;
}

.hero-panel-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 0;
  border-top: 1px solid var(--lp-card-border);
}

.hero-panel-item:first-child {
  border-top: none;
  padding-top: 0;
}

.hero-panel-icon {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--lp-accent-dim);
  color: var(--lp-accent);
  flex-shrink: 0;
}

.hero-panel-copy {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.hero-panel-copy strong {
  font-size: 0.84rem;
  color: var(--lp-text);
}

.hero-panel-copy span {
  font-size: 0.76rem;
  line-height: 1.6;
  color: var(--lp-text-dim);
}

.hero-panel-prompt {
  margin-top: 18px;
  padding: 16px;
  border-radius: 16px;
  background: rgba(99, 102, 241, 0.08);
  border: 1px solid rgba(99, 102, 241, 0.14);
}

.landing-page.landing-dark .hero-panel-prompt {
  background: rgba(149, 128, 255, 0.08);
  border-color: rgba(149, 128, 255, 0.16);
}

.hero-panel-prompt span {
  display: block;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--lp-accent);
}

.hero-panel-prompt p {
  margin: 8px 0 0;
  font-size: 0.82rem;
  line-height: 1.7;
  color: var(--lp-text);
}

.hero-btn-secondary {
  font-size: 0.88rem;
  font-weight: 600;
  border-radius: 10px;
  padding: 10px 28px;
  background: rgba(255, 255, 255, 0.1);
  color: rgba(255, 255, 255, 0.86);
  border-color: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
}
.hero-btn-secondary:hover {
  background: rgba(255, 255, 255, 0.16);
  color: #fff;
  border-color: rgba(255, 255, 255, 0.28);
}

.landing-page.landing-dark .hero-btn-secondary {
  border-color: rgba(255, 255, 255, 0.1);
}

.landing-page.landing-dark .hero-btn-secondary:hover {
  background: rgba(255, 255, 255, 0.04);
  color: #fff;
  border-color: rgba(255, 255, 255, 0.18);
}

/* Preview card */
.hero-preview {
  margin-top: 48px;
  width: 100%;
  max-width: 520px;
  z-index: 1;
}
.preview-card {
  background: var(--lp-card);
  border: 1px solid var(--lp-card-border);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 8px 28px rgba(0, 0, 0, 0.2);
}
.preview-header {
  display: flex;
  gap: 6px;
  padding: 11px 15px;
  border-bottom: 1px solid var(--lp-card-border);
}
.preview-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
}
.preview-dot.red { background: #ef4444; }
.preview-dot.yellow { background: #f59e0b; }
.preview-dot.green { background: #10b981; }
.preview-body {
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.preview-prompt {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--lp-card-border);
  border-radius: 10px;
  font-size: 0.8rem;
  color: var(--lp-text-dim);
}
.preview-prompt i {
  color: var(--lp-accent);
  font-size: 0.85rem;
}
.preview-result {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}
.preview-img-placeholder {
  width: 90px;
  height: 90px;
  border-radius: 12px;
  background: linear-gradient(135deg, rgba(149, 128, 255, 0.1), rgba(120, 90, 220, 0.06));
  border: 1px solid var(--lp-card-border);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--lp-accent);
  font-size: 1.4rem;
  flex-shrink: 0;
}
.preview-generating {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 8px;
}
.gen-bar {
  height: 9px;
  border-radius: 5px;
  background: linear-gradient(90deg, rgba(255, 255, 255, 0.04), rgba(149, 128, 255, 0.12));
}
.gen-bar.short { width: 60%; }

/* ═══ Features ═══ */
.simple-steps-section {
  position: relative;
  z-index: 1;
  padding: 42px 24px 88px;
}

.simple-steps-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
}

.features-section {
  position: relative;
  z-index: 1;
  padding: 80px 24px;
}
.section-inner {
  max-width: 960px;
  margin: 0 auto;
}
.section-title {
  font-size: 1.8rem;
  font-weight: 800;
  text-align: center;
  letter-spacing: -0.02em;
  margin: 0 0 8px;
  color: var(--lp-text);
}
.section-sub {
  text-align: center;
  font-size: 0.88rem;
  color: var(--lp-text-dim);
  margin: 0 0 40px;
}
.features-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}
.feature-card {
  padding: 24px 22px;
  background: var(--lp-card);
  border: 1px solid var(--lp-card-border);
  border-radius: 14px;
  transition: transform 0.25s, border-color 0.25s;
}
.feature-card:hover {
  transform: translateY(-3px);
  border-color: rgba(149, 128, 255, 0.15);
}
.feature-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.05rem;
  margin-bottom: 12px;
}
.feature-title {
  font-size: 0.95rem;
  font-weight: 700;
  margin: 0 0 5px;
  color: var(--lp-text);
}
.feature-desc {
  font-size: 0.8rem;
  color: var(--lp-text-dim);
  margin: 0;
  line-height: 1.55;
}

/* ═══ How It Works ═══ */
.how-section {
  position: relative;
  z-index: 1;
  padding: 80px 24px;
  background: var(--lp-surface);
  border-top: 1px solid var(--lp-card-border);
  border-bottom: 1px solid var(--lp-card-border);
}
.steps-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}
.step-card {
  text-align: center;
  padding: 28px 18px;
}
.step-num {
  font-size: 2.2rem;
  font-weight: 900;
  background: linear-gradient(135deg, #9580ff, #b8a4ff);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  line-height: 1;
  margin-bottom: 12px;
}
.step-icon-wrap {
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background: var(--lp-accent-dim);
  border: 1px solid rgba(149, 128, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 12px;
  color: var(--lp-accent);
  font-size: 1rem;
}
.step-title {
  font-size: 0.95rem;
  font-weight: 700;
  margin: 0 0 5px;
  color: var(--lp-text);
}
.step-desc {
  font-size: 0.8rem;
  color: var(--lp-text-dim);
  margin: 0;
  line-height: 1.55;
}

/* ═══ CTA ═══ */
.cta-section {
  position: relative;
  z-index: 1;
  padding: 80px 24px;
}
.cta-inner {
  max-width: 560px;
  margin: 0 auto;
  text-align: center;
}
.cta-title {
  font-size: 1.8rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  margin: 0 0 10px;
  color: var(--lp-text);
}
.cta-sub {
  font-size: 0.9rem;
  color: var(--lp-text-dim);
  margin: 0 0 24px;
  line-height: 1.6;
}
.cta-btn {
  font-size: 0.9rem;
  font-weight: 700;
  border-radius: 10px;
  padding: 11px 30px;
  background: linear-gradient(135deg, #7c5ce0, #9580ff);
  border-color: rgba(149, 128, 255, 0.4);
  color: #fff;
  box-shadow: 0 4px 20px rgba(149, 128, 255, 0.2);
}
.cta-btn:hover {
  box-shadow: 0 6px 28px rgba(149, 128, 255, 0.35);
  transform: translateY(-1px);
}

/* ═══ Footer ═══ */
.landing-footer {
  position: relative;
  z-index: 1;
  border-top: 1px solid var(--lp-card-border);
  padding: 22px 24px;
}

@supports (content-visibility: auto) {
  .features-section,
  .how-section,
  .cta-section,
  .landing-footer {
    content-visibility: auto;
  }

  .features-section,
  .how-section,
  .cta-section {
    contain-intrinsic-size: 760px;
  }

  .landing-footer {
    contain-intrinsic-size: 96px;
  }
}
.footer-inner {
  max-width: 1100px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.footer-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 0.88rem;
  color: var(--lp-text);
}
.footer-logo {
  width: 28px;
  height: 28px;
  object-fit: contain;
}
.footer-rights {
  font-size: 0.7rem;
  color: var(--lp-text-muted);
}
.footer-links {
  display: flex;
  align-items: center;
  gap: 8px;
}
.footer-legal-link {
  font-size: 0.75rem;
  color: var(--lp-text-muted);
  text-decoration: none;
  transition: color 0.2s;
}
.footer-legal-link:hover {
  color: var(--lp-accent);
}
.footer-sep {
  color: var(--lp-text-muted);
  font-size: 0.7rem;
}

/* ═══ Responsive ═══ */
@media (max-width: 768px) {
  .hero-shell {
    grid-template-columns: 1fr;
  }
  .hero-content {
    text-align: center;
    align-items: center;
  }
  .hero-title { font-size: 2rem; }
  .hero-sub { font-size: 0.88rem; }
  .hero-section {
    min-height: 560px;
    padding: 116px 18px 62px;
  }
  .hero-media-grid {
    inset: -22px -44px -34px;
    grid-template-columns: repeat(3, minmax(130px, 1fr));
    grid-template-rows: repeat(3, 176px);
    gap: 10px;
    transform: rotate(-5deg) scale(1.08);
    filter: blur(2.5px) saturate(1.05) brightness(0.82);
    opacity: 0.84;
  }
  .hero-media-img-1 { grid-column: 1; grid-row: 1; }
  .hero-media-img-2 { grid-column: 2; grid-row: 1; }
  .hero-media-img-3 { grid-column: 3; grid-row: 1; }
  .hero-media-img-4 { grid-column: 1; grid-row: 2; }
  .hero-media-img-5 { grid-column: 3; grid-row: 2; }
  .hero-media-img-6 { grid-column: 1; grid-row: 3; }
  .hero-media-img-7 { grid-column: 2; grid-row: 3; }
  .hero-media-img-8 { grid-column: 3; grid-row: 3; }
  .hero-media-img-9 { grid-column: 2; grid-row: 2; opacity: 0.74; }
  .hero-media-img {
    border-radius: 14px;
  }
  .hero-media-shade {
    background:
      radial-gradient(ellipse at center, rgba(6, 7, 12, 0.8) 0%, rgba(6, 7, 12, 0.62) 36%, rgba(6, 7, 12, 0.2) 68%, rgba(6, 7, 12, 0.38) 100%),
      linear-gradient(180deg, rgba(6, 7, 12, 0.44) 0%, rgba(6, 7, 12, 0.24) 46%, rgba(6, 7, 12, 0.68) 86%, var(--lp-bg) 100%);
  }
  .hero-content-centered {
    padding: 26px 8px 22px;
    background: radial-gradient(ellipse at center, rgba(6, 7, 12, 0.68) 0%, rgba(6, 7, 12, 0.44) 56%, rgba(6, 7, 12, 0) 78%);
  }
  .hero-actions {
    flex-direction: column;
    width: 100%;
  }
  .hero-proof-grid {
    grid-template-columns: 1fr;
  }
  .hero-btn-primary,
  .hero-btn-secondary { width: 100%; }
  .simple-steps-grid { grid-template-columns: 1fr; }
  .features-grid { grid-template-columns: 1fr; }
  .steps-grid { grid-template-columns: 1fr; }
  .section-title { font-size: 1.4rem; }
  .cta-title { font-size: 1.4rem; }
  .nav-links { display: none; }
  .nav-login-btn { display: none; }
  .footer-inner {
    flex-direction: column;
    gap: 10px;
    text-align: center;
  }
  .landing-page::before {
    height: 720px;
    background:
      radial-gradient(circle at 50% 10%, rgba(120, 90, 220, 0.2) 0%, transparent 38%),
      radial-gradient(circle at 20% 70%, rgba(140, 80, 200, 0.1) 0%, transparent 28%);
  }
}

@media (max-width: 480px) {
  .hero-section { padding: 120px 16px 60px; }
  .hero-title { font-size: 1.7rem; }
}
</style>
