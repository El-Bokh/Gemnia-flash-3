<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useLayoutStore } from '@/stores/layout'
import { useAuthStore } from '@/stores/auth'
import { useSeo } from '@/composables/useSeo'
import { getAuthenticatedHome } from '@/utils/auth'

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
  { icon: 'pi pi-palette', title: t('landing.feature2Title'), desc: t('landing.feature2Desc'), color: '#06b6d4' },
  { icon: 'pi pi-bolt', title: t('landing.feature3Title'), desc: t('landing.feature3Desc'), color: '#f59e0b' },
  { icon: 'pi pi-check-circle', title: t('landing.feature4Title'), desc: t('landing.feature4Desc'), color: '#10b981' },
])

const steps = computed(() => [
  { num: '1', icon: 'pi pi-pencil', title: t('landing.step1Title'), desc: t('landing.step1Desc') },
  { num: '2', icon: 'pi pi-cog', title: t('landing.step2Title'), desc: t('landing.step2Desc') },
  { num: '3', icon: 'pi pi-download', title: t('landing.step3Title'), desc: t('landing.step3Desc') },
])

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
        </div>
        <div class="nav-actions">
          <button class="nav-icon-btn" @click="layout.toggleDarkMode()" :title="t('client.toggleTheme')">
            <i :class="layout.darkMode ? 'pi pi-sun' : 'pi pi-moon'" />
          </button>
          <button class="nav-icon-btn" @click="layout.toggleLocale()" :title="layout.locale === 'ar' ? 'English' : 'العربية'">
            <i class="pi pi-globe" />
          </button>
          <template v-if="auth.isAuthenticated">
            <button type="button" class="nav-cta" @click="goChat">
              <i class="pi pi-sparkles" />
              <span>{{ t('client.home') }}</span>
            </button>
          </template>
          <template v-else>
            <button class="nav-login-btn" @click="goLogin">{{ t('landing.login') }}</button>
            <button type="button" class="nav-cta" @click="goRegister">
              <i class="pi pi-user-plus" />
              <span>{{ t('landing.register') }}</span>
            </button>
          </template>
        </div>
      </div>
    </nav>

    <!-- ═══ Hero ═══ -->
    <section class="hero-section">
      <div class="hero-content reveal">
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
          <button type="button" class="hero-btn hero-btn-primary" @click="goRegister">
            <span>{{ t('landing.getStarted') }}</span>
            <i class="pi pi-arrow-right" />
          </button>
          <button type="button" class="hero-btn hero-btn-secondary" @click="goPricing">
            <i class="pi pi-tag" />
            <span>{{ t('landing.viewPricing') }}</span>
          </button>
        </div>
      </div>

      <div class="hero-preview reveal" style="--reveal-delay: 0.2s;">
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
        <h2 class="section-title reveal">{{ t('landing.featuresTitle') }}</h2>
        <p class="section-sub reveal" style="--reveal-delay: 0.1s;">{{ t('landing.featuresSub') }}</p>
        <div class="features-grid">
          <div v-for="(f, i) in features" :key="i" class="feature-card reveal" :style="{ '--reveal-delay': i * 0.08 + 's' }">
            <div class="feature-icon" :style="{ background: f.color + '12', color: f.color }">
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
        <h2 class="section-title reveal">{{ t('landing.howTitle') }}</h2>
        <p class="section-sub reveal" style="--reveal-delay: 0.1s;">{{ t('landing.howSub') }}</p>
        <div class="steps-grid">
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

    <!-- ═══ CTA ═══ -->
    <section class="cta-section">
      <div class="cta-inner reveal">
        <h2 class="cta-title">{{ t('landing.ctaTitle') }}</h2>
        <p class="cta-sub">{{ t('landing.ctaSub') }}</p>
        <button type="button" class="cta-btn" @click="goRegister">
          <span>{{ t('landing.ctaButton') }}</span>
          <i class="pi pi-arrow-right" />
        </button>
      </div>
    </section>

    <!-- ═══ Footer ═══ -->
    <footer class="landing-footer">
      <div class="footer-inner">
        <div class="footer-brand">
          <img src="/klek-ai-mark.svg" alt="Klek AI" class="footer-logo" />
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
  --lp-bg: #07070e;
  --lp-surface: #0b0b16;
  --lp-card: rgba(14, 14, 28, 0.65);
  --lp-card-border: rgba(255, 255, 255, 0.06);
  --lp-text: #dddde4;
  --lp-text-dim: #8b8b9e;
  --lp-text-muted: #55556a;
  --lp-accent: #9580ff;
  --lp-accent-dim: rgba(149, 128, 255, 0.12);

  min-height: 100vh;
  background: var(--lp-bg);
  color: var(--lp-text);
  overflow-x: hidden;
  position: relative;
  isolation: isolate;
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
    radial-gradient(circle at 50% 8%, rgba(120, 90, 220, 0.22) 0%, transparent 34%),
    radial-gradient(circle at 88% 42%, rgba(60, 100, 200, 0.14) 0%, transparent 28%),
    radial-gradient(circle at 12% 64%, rgba(140, 80, 200, 0.12) 0%, transparent 24%);
  opacity: 0.9;
}

/* ═══ Navbar ═══ */
.landing-nav {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: rgba(7, 7, 14, 0.92);
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
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
  width: 30px;
  height: 30px;
  filter: drop-shadow(0 0 8px rgba(149, 128, 255, 0.35));
}
.nav-brand-text {
  font-size: 1.05rem;
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
  transition: color 0.2s, background 0.2s;
}
.nav-link:hover {
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
  padding: 150px 24px 80px;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.hero-content {
  text-align: center;
  max-width: 680px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 18px;
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
  letter-spacing: -0.03em;
  margin: 0;
  color: #fff;
}
.hero-highlight {
  background: linear-gradient(135deg, #9580ff 0%, #b8a4ff 50%, #c9b8ff 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.hero-sub {
  font-size: 1rem;
  color: var(--lp-text-dim);
  line-height: 1.7;
  margin: 0;
  max-width: 500px;
}
.hero-actions {
  display: flex;
  align-items: center;
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
.hero-btn-secondary {
  font-size: 0.88rem;
  font-weight: 600;
  border-radius: 10px;
  padding: 10px 28px;
  background: transparent;
  color: var(--lp-text-dim);
  border-color: rgba(255, 255, 255, 0.1);
}
.hero-btn-secondary:hover {
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
  color: #fff;
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
  color: #fff;
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
  color: #fff;
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
  color: #fff;
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
  color: #fff;
}
.footer-logo {
  width: 22px;
  height: 22px;
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
  .hero-title { font-size: 2rem; }
  .hero-sub { font-size: 0.88rem; }
  .hero-actions {
    flex-direction: column;
    width: 100%;
  }
  .hero-btn-primary,
  .hero-btn-secondary { width: 100%; }
  .features-grid { grid-template-columns: 1fr; }
  .steps-grid { grid-template-columns: 1fr; }
  .section-title { font-size: 1.4rem; }
  .cta-title { font-size: 1.4rem; }
  .nav-links { display: none; }
  .nav-login-btn { display: none; }
  .hero-preview { max-width: 100%; }
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
