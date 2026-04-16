<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useLayoutStore } from '@/stores/layout'
import { useAuthStore } from '@/stores/auth'
import { getAuthenticatedHome } from '@/utils/auth'
import BrandLogo from '@/components/branding/BrandLogo.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const layout = useLayoutStore()
const auth = useAuthStore()

const currentRouteName = computed(() => String(route.name ?? ''))

function goRoot() {
  if (auth.isAuthenticated) {
    void router.push(getAuthenticatedHome(auth.user))
    return
  }

  void router.push({ name: 'landing' })
}

function goChat() {
  void router.push({ name: 'chat' })
}

function goProfile() {
  void router.push({ name: 'profile' })
}

function goLogin() {
  void router.push({ name: 'login' })
}

function goRegister() {
  void router.push({ name: 'register' })
}
</script>

<template>
  <div class="standalone-layout">
    <header class="standalone-topbar">
      <div class="standalone-topbar-shell">
        <button type="button" class="standalone-brand" @click="goRoot">
          <BrandLogo class="brand-mark" />
          <span class="brand-copy">
            <strong>Klek AI</strong>
            <small>{{ currentRouteName === 'pricing' ? t('client.pricing') : t('clientSupport.support') }}</small>
          </span>
        </button>

        <nav class="standalone-nav">
          <router-link :to="{ name: 'pricing' }" class="standalone-link" :class="{ active: currentRouteName === 'pricing' }">
            {{ t('client.pricing') }}
          </router-link>
          <router-link
            v-if="auth.isAuthenticated"
            :to="{ name: 'support' }"
            class="standalone-link"
            :class="{ active: currentRouteName === 'support' }"
          >
            {{ t('clientSupport.support') }}
          </router-link>
        </nav>

        <div class="standalone-actions">
          <button class="toolbar-btn" type="button" :title="t('client.toggleTheme')" @click="layout.toggleDarkMode()">
            <i :class="layout.darkMode ? 'pi pi-sun' : 'pi pi-moon'" />
          </button>
          <button class="lang-btn" type="button" @click="layout.toggleLocale()">
            {{ layout.locale === 'ar' ? 'English' : 'العربية' }}
          </button>

          <template v-if="auth.isAuthenticated">
            <button type="button" class="action-btn action-btn-ghost" @click="goChat">
              <i class="pi pi-comments" />
              <span>{{ t('landing.chat') }}</span>
            </button>
            <button type="button" class="action-btn action-btn-primary" @click="goProfile">
              <i class="pi pi-user" />
              <span>{{ t('client.profile') }}</span>
            </button>
          </template>

          <template v-else>
            <button type="button" class="action-btn action-btn-ghost" @click="goLogin">
              <span>{{ t('landing.login') }}</span>
            </button>
            <button type="button" class="action-btn action-btn-primary" @click="goRegister">
              <span>{{ t('landing.register') }}</span>
            </button>
          </template>
        </div>
      </div>
    </header>

    <main class="standalone-content">
      <div class="standalone-content-shell">
        <router-view />
      </div>
    </main>
  </div>
</template>

<style scoped>
.standalone-layout {
  --standalone-shell-width: 1500px;

  min-height: 100vh;
  background:
    radial-gradient(circle at top, rgba(99, 102, 241, 0.14), transparent 28%),
    linear-gradient(180deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0)),
    var(--layout-bg);
}

.standalone-topbar {
  position: sticky;
  top: 0;
  z-index: 40;
  padding: 12px 14px;
  border-bottom: 1px solid var(--surface-border);
  backdrop-filter: blur(16px);
  background: color-mix(in srgb, var(--surface-ground) 82%, transparent);
}

.standalone-topbar-shell {
  width: min(100%, var(--standalone-shell-width));
  margin: 0 auto;
  display: grid;
  grid-template-columns: minmax(200px, 1fr) auto minmax(200px, 1fr);
  align-items: center;
  gap: 18px;
}

.standalone-brand {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  border: none;
  background: transparent;
  color: var(--text-color);
  cursor: pointer;
  padding: 0;
}

.brand-mark {
  width: 46px;
  height: 46px;
  display: block;
  object-fit: contain;
  filter: drop-shadow(0 8px 18px rgba(99, 102, 241, 0.22));
}

.brand-copy {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.brand-copy strong {
  font-size: 0.92rem;
  line-height: 1.1;
}

.brand-copy small {
  font-size: 0.72rem;
  color: var(--text-color-secondary);
}

.standalone-nav {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-self: center;
}

.standalone-link {
  padding: 9px 14px;
  border-radius: 999px;
  color: var(--text-color-secondary);
  font-size: 0.82rem;
  font-weight: 600;
  transition: background 0.2s, color 0.2s;
}

.standalone-link:hover,
.standalone-link.active {
  color: var(--text-color);
  background: color-mix(in srgb, var(--primary-color) 16%, transparent);
}

.standalone-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  justify-self: end;
}

.toolbar-btn,
.lang-btn,
.action-btn {
  height: 38px;
  border-radius: 12px;
  border: 1px solid var(--surface-border);
  background: color-mix(in srgb, var(--surface-card) 88%, transparent);
  color: var(--text-color);
}

.toolbar-btn,
.lang-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 12px;
}

.toolbar-btn {
  width: 38px;
  padding: 0;
}

.lang-btn {
  font-size: 0.8rem;
  font-weight: 600;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0 14px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s, border-color 0.2s, background 0.2s;
}

.action-btn:hover,
.toolbar-btn:hover,
.lang-btn:hover {
  border-color: color-mix(in srgb, var(--primary-color) 34%, var(--surface-border));
  transform: translateY(-1px);
}

.action-btn-ghost {
  background: transparent;
}

.action-btn-primary {
  color: #fff;
  border-color: transparent;
  background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
}

.standalone-content {
  padding: 18px 14px 36px;
}

.standalone-content-shell {
  width: min(100%, var(--standalone-shell-width));
  margin: 0 auto;
}

@media (max-width: 920px) {
  .standalone-topbar-shell {
    grid-template-columns: 1fr;
  }

  .standalone-nav {
    order: 3;
    width: 100%;
    justify-content: center;
  }

  .standalone-actions {
    justify-self: stretch;
    justify-content: flex-end;
  }
}

@media (max-width: 640px) {
  .standalone-topbar {
    padding: 12px;
    gap: 12px;
  }

  .standalone-actions {
    width: 100%;
    flex-wrap: wrap;
    justify-content: stretch;
  }

  .lang-btn,
  .action-btn {
    flex: 1 1 auto;
    justify-content: center;
  }

  .standalone-content {
    padding: 14px 8px 28px;
  }
}
</style>