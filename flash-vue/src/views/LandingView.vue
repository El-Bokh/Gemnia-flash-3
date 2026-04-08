<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useLayoutStore } from '@/stores/layout'
import { useAuthStore } from '@/stores/auth'
import { useSeo } from '@/composables/useSeo'
import { getAuthenticatedHome } from '@/utils/auth'
import Button from 'primevue/button'

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

const features = computed(() => [
  { icon: 'pi pi-sparkles', title: t('landing.feature1Title'), desc: t('landing.feature1Desc'), color: '#8b5cf6' },
  { icon: 'pi pi-palette', title: t('landing.feature2Title'), desc: t('landing.feature2Desc'), color: '#0ea5e9' },
  { icon: 'pi pi-bolt', title: t('landing.feature3Title'), desc: t('landing.feature3Desc'), color: '#f59e0b' },
  { icon: 'pi pi-check-circle', title: t('landing.feature4Title'), desc: t('landing.feature4Desc'), color: '#10b981' },
])

const steps = computed(() => [
  { num: '1', icon: 'pi pi-pencil', title: t('landing.step1Title'), desc: t('landing.step1Desc') },
  { num: '2', icon: 'pi pi-cog', title: t('landing.step2Title'), desc: t('landing.step2Desc') },
  { num: '3', icon: 'pi pi-download', title: t('landing.step3Title'), desc: t('landing.step3Desc') },
])

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
</script>

<template>
  <div class="landing-page">
    <!-- ═══ Navbar ═══ -->
    <nav class="landing-nav">
      <div class="nav-inner">
        <div class="nav-brand">
          <img src="/klek-ai-mark.svg" alt="Klek AI" class="nav-logo" />
          <span class="nav-brand-text">Klek AI</span>
        </div>
        <div class="nav-links">
          <router-link to="/pricing" class="nav-link">{{ t('landing.viewPricing') }}</router-link>
          <!-- Theme toggle -->
          <button class="nav-icon-btn" @click="layout.toggleDarkMode()" :title="t('client.toggleTheme')">
            <i :class="layout.darkMode ? 'pi pi-sun' : 'pi pi-moon'" />
          </button>
          <!-- Language toggle -->
          <button class="nav-icon-btn" @click="layout.toggleLocale()" :title="layout.locale === 'ar' ? 'English' : 'العربية'">
            <i class="pi pi-globe" />
          </button>
        </div>
        <div class="nav-actions">
          <template v-if="auth.isAuthenticated">
            <Button :label="t('client.home')" icon="pi pi-sparkles" size="small" class="nav-cta" @click="goChat" />
          </template>
          <template v-else>
            <button class="nav-login-btn" @click="goLogin">{{ t('landing.login') }}</button>
            <Button :label="t('landing.register')" icon="pi pi-user-plus" size="small" class="nav-cta" @click="goRegister" />
          </template>
        </div>
      </div>
    </nav>

    <!-- ═══ Hero ═══ -->
    <section class="hero-section">
      <div class="hero-bg">
        <div class="hero-orb orb-1" />
        <div class="hero-orb orb-2" />
        <div class="hero-orb orb-3" />
        <div class="hero-grid" />
        <div class="hero-noise" />
      </div>
      <div class="hero-content">
        <div class="hero-badge">
          <i class="pi pi-sparkles" />
          <span>{{ t('landing.trustedBy') }}</span>
        </div>
        <h1 class="hero-title">
          {{ t('landing.heroTitle') }}
          <span class="hero-highlight">{{ t('landing.heroTitleHighlight') }}</span>
        </h1>
        <p class="hero-sub">{{ t('landing.heroSub') }}</p>
        <div class="hero-actions">
          <Button :label="t('landing.getStarted')" icon="pi pi-arrow-right" iconPos="right" class="hero-btn-primary" @click="goRegister" />
          <Button :label="t('landing.viewPricing')" icon="pi pi-tag" severity="secondary" outlined class="hero-btn-secondary" @click="goPricing" />
        </div>
      </div>

      <!-- Floating preview -->
      <div class="hero-preview">
        <div class="preview-card">
          <div class="preview-header">
            <div class="preview-dot red" />
            <div class="preview-dot yellow" />
            <div class="preview-dot green" />
          </div>
          <div class="preview-body">
            <div class="preview-prompt">
              <i class="pi pi-sparkles" />
              <span>{{ t('client.suggestDesign') }}</span>
            </div>
            <div class="preview-result">
              <div class="preview-img-placeholder">
                <i class="pi pi-image" />
              </div>
              <div class="preview-generating">
                <div class="gen-bar" />
                <div class="gen-bar short" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ Features ═══ -->
    <section class="features-section">
      <div class="section-inner">
        <h2 class="section-title">{{ t('landing.featuresTitle') }}</h2>
        <p class="section-sub">{{ t('landing.featuresSub') }}</p>
        <div class="features-grid">
          <div v-for="(f, i) in features" :key="i" class="feature-card">
            <div class="feature-icon" :style="{ background: f.color + '15', color: f.color }">
              <i :class="f.icon" />
            </div>
            <h3 class="feature-title">{{ f.title }}</h3>
            <p class="feature-desc">{{ f.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ How It Works ═══ -->
    <section class="how-section">
      <div class="section-inner">
        <h2 class="section-title">{{ t('landing.howTitle') }}</h2>
        <p class="section-sub">{{ t('landing.howSub') }}</p>
        <div class="steps-grid">
          <div v-for="(s, i) in steps" :key="i" class="step-card">
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

    <!-- ═══ CTA ═══ -->
    <section class="cta-section">
      <div class="cta-inner">
        <div class="cta-glow" />
        <h2 class="cta-title">{{ t('landing.ctaTitle') }}</h2>
        <p class="cta-sub">{{ t('landing.ctaSub') }}</p>
        <Button :label="t('landing.ctaButton')" icon="pi pi-arrow-right" iconPos="right" class="cta-btn" @click="goRegister" />
      </div>
    </section>

    <!-- ═══ Footer ═══ -->
    <footer class="landing-footer">
      <div class="footer-inner">
        <div class="footer-brand">
          <img src="/klek-ai-mark.svg" alt="Klek AI" class="footer-logo" />
          <span>Klek AI</span>
        </div>
        <span class="footer-rights">{{ t('landing.footerRights') }}</span>
      </div>
    </footer>
  </div>
</template>

<style scoped>
/* ═══ Force dark palette on landing ═══ */
.landing-page {
  --lp-bg: #050510;
  --lp-surface: #0c0c1d;
  --lp-card: rgba(15, 15, 35, 0.6);
  --lp-card-border: rgba(139, 92, 246, 0.12);
  --lp-text: #e8e8f0;
  --lp-text-dim: #9896b0;
  --lp-text-muted: #5e5c78;
  --lp-accent: #a78bfa;
  --lp-accent2: #c084fc;
  --lp-glow: rgba(139, 92, 246, 0.15);

  min-height: 100vh;
  background: var(--lp-bg);
  color: var(--lp-text);
  overflow-x: hidden;
}

/* ═══ Navbar ═══ */
.landing-nav {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: rgba(5, 5, 16, 0.7);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(139, 92, 246, 0.08);
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
  width: 32px;
  height: 32px;
  filter: drop-shadow(0 0 12px rgba(167, 139, 250, 0.5));
}
.nav-brand-text {
  font-size: 1.1rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #fff;
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
  transition: color 0.15s, background 0.15s;
}
.nav-link:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.06);
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
  font-size: 0.9rem;
  transition: color 0.15s, background 0.15s;
}
.nav-icon-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.06);
}
.nav-actions {
  display: flex;
  align-items: center;
  gap: 10px;
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
  transition: color 0.15s, background 0.15s;
}
.nav-login-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.06);
}
.nav-cta {
  font-size: 0.78rem !important;
  font-weight: 600 !important;
  border-radius: 9px !important;
}

/* ═══ Hero ═══ */
.hero-section {
  position: relative;
  padding: 150px 24px 90px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  align-items: center;
  background: linear-gradient(180deg, #050510 0%, #0a0a20 40%, #0d0820 70%, #050510 100%);
}
.hero-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
}

/* Animated floating orbs */
.hero-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.5;
}
.orb-1 {
  width: 600px;
  height: 600px;
  top: -200px;
  left: 50%;
  transform: translateX(-50%);
  background: radial-gradient(circle, rgba(139, 92, 246, 0.35) 0%, rgba(99, 102, 241, 0.1) 50%, transparent 70%);
  animation: orbFloat1 8s ease-in-out infinite;
}
.orb-2 {
  width: 400px;
  height: 400px;
  top: 20%;
  right: -100px;
  background: radial-gradient(circle, rgba(192, 132, 252, 0.25) 0%, transparent 70%);
  animation: orbFloat2 10s ease-in-out infinite;
}
.orb-3 {
  width: 350px;
  height: 350px;
  bottom: -50px;
  left: -80px;
  background: radial-gradient(circle, rgba(99, 102, 241, 0.2) 0%, transparent 70%);
  animation: orbFloat3 12s ease-in-out infinite;
}

@keyframes orbFloat1 {
  0%, 100% { transform: translateX(-50%) translateY(0); }
  50% { transform: translateX(-50%) translateY(30px); }
}
@keyframes orbFloat2 {
  0%, 100% { transform: translateY(0) translateX(0); }
  50% { transform: translateY(-25px) translateX(-15px); }
}
@keyframes orbFloat3 {
  0%, 100% { transform: translateY(0) translateX(0); }
  50% { transform: translateY(-20px) translateX(20px); }
}

.hero-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(139, 92, 246, 0.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(139, 92, 246, 0.06) 1px, transparent 1px);
  background-size: 60px 60px;
  mask-image: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.3) 30%, transparent 85%);
  -webkit-mask-image: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.3) 30%, transparent 85%);
}

.hero-noise {
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
  opacity: 0.4;
}

.hero-content {
  position: relative;
  z-index: 2;
  text-align: center;
  max-width: 700px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}
.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 7px 18px;
  border-radius: 100px;
  background: rgba(139, 92, 246, 0.1);
  border: 1px solid rgba(139, 92, 246, 0.2);
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--lp-accent);
  backdrop-filter: blur(8px);
}
.hero-badge i { font-size: 0.7rem; }
.hero-title {
  font-size: 3.2rem;
  font-weight: 900;
  line-height: 1.08;
  letter-spacing: -0.03em;
  margin: 0;
  color: #fff;
}
.hero-highlight {
  background: linear-gradient(135deg, #8b5cf6, #a78bfa, #c084fc, #e879f9);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.hero-sub {
  font-size: 1.05rem;
  color: var(--lp-text-dim);
  line-height: 1.7;
  margin: 0;
  max-width: 540px;
}
.hero-actions {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 10px;
}
.hero-btn-primary {
  font-size: 0.9rem !important;
  font-weight: 700 !important;
  border-radius: 12px !important;
  padding: 11px 30px !important;
  background: linear-gradient(135deg, #7c3aed, #8b5cf6) !important;
  border-color: #8b5cf6 !important;
  box-shadow: 0 0 24px rgba(139, 92, 246, 0.3) !important;
  transition: box-shadow 0.2s, transform 0.2s !important;
}
.hero-btn-primary:hover {
  box-shadow: 0 0 36px rgba(139, 92, 246, 0.5) !important;
  transform: translateY(-1px) !important;
}
.hero-btn-secondary {
  font-size: 0.9rem !important;
  font-weight: 600 !important;
  border-radius: 12px !important;
  padding: 11px 30px !important;
  color: var(--lp-text-dim) !important;
  border-color: rgba(139, 92, 246, 0.25) !important;
  background: rgba(139, 92, 246, 0.06) !important;
}
.hero-btn-secondary:hover {
  background: rgba(139, 92, 246, 0.12) !important;
  border-color: rgba(139, 92, 246, 0.4) !important;
  color: #fff !important;
}

/* Hero preview card */
.hero-preview {
  position: relative;
  z-index: 2;
  margin-top: 50px;
  width: 100%;
  max-width: 560px;
}
.preview-card {
  background: rgba(12, 12, 30, 0.7);
  border: 1px solid rgba(139, 92, 246, 0.15);
  border-radius: 18px;
  overflow: hidden;
  backdrop-filter: blur(12px);
  box-shadow:
    0 8px 40px rgba(0, 0, 0, 0.3),
    0 0 80px rgba(139, 92, 246, 0.08),
    inset 0 1px 0 rgba(255, 255, 255, 0.04);
}
.preview-header {
  display: flex;
  gap: 6px;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(139, 92, 246, 0.1);
}
.preview-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
.preview-dot.red { background: #ef4444; }
.preview-dot.yellow { background: #f59e0b; }
.preview-dot.green { background: #10b981; }
.preview-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.preview-prompt {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: rgba(139, 92, 246, 0.06);
  border: 1px solid rgba(139, 92, 246, 0.1);
  border-radius: 10px;
  font-size: 0.82rem;
  color: var(--lp-text-dim);
}
.preview-prompt i {
  color: var(--lp-accent);
  font-size: 0.9rem;
}
.preview-result {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}
.preview-img-placeholder {
  width: 100px;
  height: 100px;
  border-radius: 12px;
  background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(192, 132, 252, 0.1));
  border: 1px solid rgba(139, 92, 246, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--lp-accent);
  font-size: 1.6rem;
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
  height: 10px;
  border-radius: 6px;
  background: linear-gradient(90deg, rgba(139, 92, 246, 0.08) 25%, rgba(139, 92, 246, 0.18) 50%, rgba(139, 92, 246, 0.08) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s ease-in-out infinite;
}
.gen-bar.short { width: 60%; }
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ═══ Features ═══ */
.features-section {
  padding: 90px 24px;
  background: var(--lp-bg);
  position: relative;
}
.section-inner {
  max-width: 1000px;
  margin: 0 auto;
}
.section-title {
  font-size: 2rem;
  font-weight: 800;
  text-align: center;
  letter-spacing: -0.02em;
  margin: 0 0 8px;
  color: #fff;
}
.section-sub {
  text-align: center;
  font-size: 0.9rem;
  color: var(--lp-text-dim);
  margin: 0 0 44px;
}
.features-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}
.feature-card {
  padding: 28px 24px;
  background: var(--lp-card);
  border: 1px solid var(--lp-card-border);
  border-radius: 16px;
  backdrop-filter: blur(8px);
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}
.feature-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(139, 92, 246, 0.08);
  border-color: rgba(139, 92, 246, 0.25);
}
.feature-icon {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
  margin-bottom: 14px;
}
.feature-title {
  font-size: 1rem;
  font-weight: 700;
  margin: 0 0 6px;
  color: #fff;
}
.feature-desc {
  font-size: 0.82rem;
  color: var(--lp-text-dim);
  margin: 0;
  line-height: 1.55;
}

/* ═══ How It Works ═══ */
.how-section {
  padding: 90px 24px;
  background: var(--lp-surface);
  border-top: 1px solid var(--lp-card-border);
  border-bottom: 1px solid var(--lp-card-border);
}
.steps-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}
.step-card {
  text-align: center;
  padding: 32px 20px;
}
.step-num {
  font-size: 2.5rem;
  font-weight: 900;
  background: linear-gradient(135deg, #8b5cf6, #c084fc);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  line-height: 1;
  margin-bottom: 14px;
}
.step-icon-wrap {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: rgba(139, 92, 246, 0.1);
  border: 1px solid rgba(139, 92, 246, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 14px;
  color: var(--lp-accent);
  font-size: 1.1rem;
}
.step-title {
  font-size: 1.05rem;
  font-weight: 700;
  margin: 0 0 6px;
  color: #fff;
}
.step-desc {
  font-size: 0.82rem;
  color: var(--lp-text-dim);
  margin: 0;
  line-height: 1.55;
}

/* ═══ CTA ═══ */
.cta-section {
  padding: 90px 24px;
  position: relative;
  overflow: hidden;
  background: var(--lp-bg);
}
.cta-inner {
  max-width: 600px;
  margin: 0 auto;
  text-align: center;
  position: relative;
  z-index: 1;
}
.cta-glow {
  position: absolute;
  width: 500px;
  height: 500px;
  border-radius: 50%;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: radial-gradient(circle, rgba(139, 92, 246, 0.1) 0%, transparent 70%);
  pointer-events: none;
}
.cta-title {
  font-size: 2rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  margin: 0 0 10px;
  color: #fff;
}
.cta-sub {
  font-size: 0.92rem;
  color: var(--lp-text-dim);
  margin: 0 0 28px;
  line-height: 1.6;
}
.cta-btn {
  font-size: 0.92rem !important;
  font-weight: 700 !important;
  border-radius: 12px !important;
  padding: 12px 32px !important;
  background: linear-gradient(135deg, #7c3aed, #8b5cf6) !important;
  border-color: #8b5cf6 !important;
  box-shadow: 0 0 24px rgba(139, 92, 246, 0.3) !important;
}
.cta-btn:hover {
  box-shadow: 0 0 36px rgba(139, 92, 246, 0.5) !important;
}

/* ═══ Footer ═══ */
.landing-footer {
  border-top: 1px solid var(--lp-card-border);
  padding: 24px;
  background: var(--lp-bg);
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
  font-size: 0.9rem;
  color: #fff;
}
.footer-logo {
  width: 24px;
  height: 24px;
}
.footer-rights {
  font-size: 0.72rem;
  color: var(--lp-text-muted);
}

/* ═══ Responsive ═══ */
@media (max-width: 768px) {
  .hero-title {
    font-size: 2.1rem;
  }
  .hero-sub {
    font-size: 0.9rem;
  }
  .hero-actions {
    flex-direction: column;
    width: 100%;
  }
  .hero-btn-primary,
  .hero-btn-secondary {
    width: 100%;
  }
  .features-grid {
    grid-template-columns: 1fr;
  }
  .steps-grid {
    grid-template-columns: 1fr;
  }
  .section-title {
    font-size: 1.5rem;
  }
  .cta-title {
    font-size: 1.5rem;
  }
  .nav-links {
    display: none;
  }
  .footer-inner {
    flex-direction: column;
    gap: 12px;
    text-align: center;
  }
  .hero-preview {
    max-width: 100%;
  }
  .orb-2 { display: none; }
}

@media (max-width: 480px) {
  .hero-section {
    padding: 120px 16px 60px;
  }
  .hero-title {
    font-size: 1.75rem;
  }
}
</style>
