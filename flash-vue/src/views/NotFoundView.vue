<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSeo } from '@/composables/useSeo'
import Button from 'primevue/button'

const { t } = useI18n()

useSeo({
  title: computed(() => t('seo.notFoundTitle')),
  description: computed(() => t('seo.notFoundDescription')),
  noindex: true,
})
</script>

<template>
  <div class="not-found-page">
    <div class="not-found-bg">
      <div class="bg-gradient" />
      <div class="bg-grid" />
    </div>

    <div class="not-found-container">
      <div class="not-found-card">
        <div class="error-code">{{ t('notFound.errorCode') }}</div>
        <h1 class="error-title">{{ t('notFound.title') }}</h1>
        <p class="error-description">{{ t('notFound.subtitle') }}</p>
        <router-link to="/">
          <Button
            :label="t('notFound.backHome')"
            icon="pi pi-home"
            class="home-btn"
          />
        </router-link>
      </div>
    </div>
  </div>
</template>

<style scoped>
.not-found-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  position: relative;
  overflow: hidden;
  background: var(--layout-bg, #f8fafc);
}

.not-found-bg {
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 0;
}
.bg-gradient {
  position: absolute;
  width: 600px;
  height: 600px;
  border-radius: 50%;
  top: -200px;
  right: -150px;
  background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, transparent 70%);
}
.bg-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(99, 102, 241, 0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(99, 102, 241, 0.03) 1px, transparent 1px);
  background-size: 40px 40px;
}

.not-found-container {
  position: relative;
  z-index: 1;
  text-align: center;
}

.not-found-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--card-border, #e2e8f0);
  border-radius: 14px;
  padding: 48px 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 6px 24px rgba(0, 0, 0, 0.03);
  max-width: 420px;
}

.error-code {
  font-size: 5rem;
  font-weight: 900;
  line-height: 1;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  letter-spacing: -0.04em;
}

.error-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--text-primary, #0f172a);
  margin: 0;
}

.error-description {
  font-size: 0.82rem;
  color: var(--text-muted, #94a3b8);
  margin: 0;
  max-width: 300px;
  line-height: 1.5;
}

.home-btn {
  margin-top: 8px;
  font-size: 0.8rem !important;
  font-weight: 600 !important;
  border-radius: 9px !important;
}

@media (max-width: 440px) {
  .not-found-card {
    padding: 32px 20px;
  }
  .error-code {
    font-size: 4rem;
  }
}
</style>
