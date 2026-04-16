<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import { useSeo } from '@/composables/useSeo'
import { getCachedMaintenanceStatus, getPublicMaintenanceStatus } from '@/services/maintenanceService'
import { getAuthenticatedHome, getStoredAuthUser } from '@/utils/auth'
import BrandLogo from '@/components/branding/BrandLogo.vue'

const router = useRouter()
const route = useRoute()
const { t } = useI18n()

const loading = ref(false)
const fetchError = ref('')
const status = ref(getCachedMaintenanceStatus())

const message = computed(() => status.value?.message || t('maintenancePage.messageFallback'))

useSeo({
  title: computed(() => t('maintenancePage.seoTitle')),
  description: computed(() => message.value),
  path: '/maintenance',
  noindex: true,
})

function resolveTarget(): string {
  const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''

  if (redirect.startsWith('/') && redirect !== '/maintenance') {
    return redirect
  }

  const storedUser = getStoredAuthUser()

  return localStorage.getItem('auth_token') ? getAuthenticatedHome(storedUser) : '/'
}

async function refreshStatus(force = false) {
  loading.value = true
  fetchError.value = ''

  try {
    const nextStatus = await getPublicMaintenanceStatus(force)
    status.value = nextStatus

    if (!nextStatus.is_enabled || nextStatus.can_bypass) {
      await router.replace(resolveTarget())
    }
  } catch {
    fetchError.value = t('maintenancePage.refreshError')
  } finally {
    loading.value = false
  }
}

void refreshStatus(true)
</script>

<template>
  <div class="maintenance-page">
    <div class="maintenance-orbit orbit-a" />
    <div class="maintenance-orbit orbit-b" />

    <section class="maintenance-shell">
      <div class="maintenance-card">
        <div class="maintenance-copy">
          <div class="maintenance-brand">
            <BrandLogo class="brand-logo" />
            <span>Klek AI</span>
          </div>

          <span class="maintenance-pill">{{ t('maintenancePage.badge') }}</span>
          <h1 class="maintenance-title">{{ t('maintenancePage.heading') }}</h1>
          <p class="maintenance-description">{{ t('maintenancePage.description') }}</p>

          <div class="maintenance-actions">
            <Button
              :label="t('maintenancePage.retry')"
              icon="pi pi-refresh"
              :loading="loading"
              @click="refreshStatus(true)"
            />
          </div>

          <p v-if="fetchError" class="maintenance-error">{{ fetchError }}</p>
        </div>

        <aside class="maintenance-panel">
          <div class="panel-topline">{{ t('maintenancePage.statusLabel') }}</div>
          <p class="panel-message">{{ message }}</p>

          <div class="panel-divider" />

          <div class="panel-meta">
            <i class="pi pi-wrench" />
            <span>{{ t('maintenancePage.meta') }}</span>
          </div>
        </aside>
      </div>
    </section>
  </div>
</template>

<style scoped>
.maintenance-page {
  min-height: 100vh;
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background:
    radial-gradient(circle at top left, rgba(0, 121, 107, 0.14), transparent 34%),
    radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.18), transparent 28%),
    linear-gradient(145deg, #f7f5ef 0%, #eef2e9 45%, #f8fafc 100%);
}

.maintenance-orbit {
  position: absolute;
  border-radius: 999px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  pointer-events: none;
}

.orbit-a {
  width: 520px;
  height: 520px;
  top: -180px;
  left: -140px;
}

.orbit-b {
  width: 360px;
  height: 360px;
  right: -80px;
  bottom: -120px;
}

.maintenance-shell {
  position: relative;
  z-index: 1;
  width: min(980px, 100%);
}

.maintenance-card {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(280px, 0.9fr);
  gap: 24px;
  padding: 28px;
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.82);
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 24px 80px rgba(15, 23, 42, 0.12);
  backdrop-filter: blur(14px);
}

.maintenance-copy {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.maintenance-brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: #0f172a;
  font-size: 0.95rem;
  font-weight: 700;
}

.brand-logo {
  width: 42px;
  height: 42px;
  object-fit: contain;
}

.maintenance-pill {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  padding: 8px 12px;
  border-radius: 999px;
  background: rgba(180, 83, 9, 0.08);
  color: #9a3412;
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.maintenance-title {
  margin: 0;
  color: #0f172a;
  font-size: clamp(2.2rem, 4vw, 4.4rem);
  line-height: 0.94;
  letter-spacing: -0.05em;
}

.maintenance-description {
  margin: 0;
  max-width: 38rem;
  color: #475569;
  font-size: 1.05rem;
  line-height: 1.8;
}

.maintenance-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-top: 4px;
}

.maintenance-error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.95rem;
}

.maintenance-panel {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 22px;
  border-radius: 22px;
  background: linear-gradient(180deg, rgba(15, 23, 42, 0.95), rgba(15, 23, 42, 0.88));
  color: #e2e8f0;
}

.panel-topline {
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #fbbf24;
}

.panel-message {
  margin: 0;
  font-size: 1.05rem;
  line-height: 1.9;
  color: #f8fafc;
}

.panel-divider {
  width: 100%;
  height: 1px;
  background: rgba(148, 163, 184, 0.24);
}

.panel-meta {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: #cbd5e1;
  font-size: 0.92rem;
}

@media (max-width: 820px) {
  .maintenance-card {
    grid-template-columns: 1fr;
    padding: 22px;
  }

  .maintenance-page {
    padding: 18px;
  }
}
</style>