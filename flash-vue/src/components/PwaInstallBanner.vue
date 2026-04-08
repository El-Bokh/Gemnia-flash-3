<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const showBanner = ref(false)
let deferredPrompt: any = null

function handleBeforeInstallPrompt(e: Event) {
  e.preventDefault()
  deferredPrompt = e
  // Don't show if user dismissed before
  if (localStorage.getItem('pwa-install-dismissed') === 'true') return
  showBanner.value = true
}

async function installApp() {
  if (!deferredPrompt) return
  deferredPrompt.prompt()
  const { outcome } = await deferredPrompt.userChoice
  if (outcome === 'accepted') {
    showBanner.value = false
  }
  deferredPrompt = null
}

function dismissBanner() {
  showBanner.value = false
  localStorage.setItem('pwa-install-dismissed', 'true')
}

onMounted(() => {
  window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
  window.addEventListener('appinstalled', () => {
    showBanner.value = false
    deferredPrompt = null
  })
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
})
</script>

<template>
  <Transition name="slide-up">
    <div v-if="showBanner" class="install-banner">
      <div class="install-content">
        <img class="install-icon" src="/klek-ai-mark.svg" alt="Klek AI" />
        <div class="install-text">
          <span class="install-title">{{ t('pwa.installTitle') }}</span>
          <span class="install-desc">{{ t('pwa.installDesc') }}</span>
        </div>
      </div>
      <div class="install-actions">
        <button type="button" class="install-btn" @click="installApp">
          <i class="pi pi-download" />
          <span>{{ t('pwa.install') }}</span>
        </button>
        <button class="dismiss-btn" @click="dismissBanner">
          <i class="pi pi-times" />
        </button>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.install-banner {
  position: fixed;
  bottom: 16px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 16px;
  background: var(--card-bg, #18181b);
  border: 1px solid var(--card-border, #27272a);
  border-radius: 14px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
  max-width: 420px;
  width: calc(100% - 32px);
}

.install-content {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.install-icon {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  filter: drop-shadow(0 0 8px rgba(129, 140, 248, 0.4));
}

.install-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.install-title {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--text-primary, #e4e4e7);
}

.install-desc {
  font-size: 0.68rem;
  color: var(--text-muted, #71717a);
}

.install-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.install-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid rgba(129, 140, 248, 0.2);
  background: linear-gradient(135deg, #7c5ce0, #9580ff);
  color: #fff;
  cursor: pointer;
  padding: 8px 12px;
  font-size: 0.75rem;
  border-radius: 8px;
  font-weight: 600;
  white-space: nowrap;
  transition: filter 0.15s, transform 0.15s;
}

.install-btn:hover {
  filter: brightness(1.04);
  transform: translateY(-1px);
}

.dismiss-btn {
  border: none;
  background: none;
  color: var(--text-muted, #71717a);
  cursor: pointer;
  padding: 6px;
  border-radius: 6px;
  font-size: 0.82rem;
  transition: color 0.15s, background 0.15s;
}

.dismiss-btn:hover {
  color: var(--text-primary, #e4e4e7);
  background: var(--hover-bg, #27272a);
}

.slide-up-enter-active {
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.slide-up-leave-active {
  transition: all 0.2s ease-in;
}

.slide-up-enter-from {
  opacity: 0;
  transform: translateX(-50%) translateY(20px);
}

.slide-up-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(10px);
}
</style>
