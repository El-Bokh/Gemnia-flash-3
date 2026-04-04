<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLayoutStore } from '@/stores/layout'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import Button from 'primevue/button'
import Badge from 'primevue/badge'
import Menu from 'primevue/menu'
import Popover from 'primevue/popover'

const layout = useLayoutStore()
const auth = useAuthStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const profileMenu = ref()
const notifPanel = ref()

const profileItems = computed(() => [
  {
    label: t('topbar.profile'),
    icon: 'pi pi-user',
    command: () => {},
  },
  {
    label: t('topbar.settings'),
    icon: 'pi pi-cog',
    command: () => {},
  },
  { separator: true },
  {
    label: t('topbar.logout'),
    icon: 'pi pi-sign-out',
    command: () => auth.logout(),
  },
])

const notifications = computed(() => notificationStore.notifications)
const unreadCount = computed(() => notificationStore.unreadCount)

const apiOnline = ref(true)

function toggleProfile(event: Event) {
  profileMenu.value.toggle(event)
}

function toggleNotifications(event: Event) {
  notifPanel.value.toggle(event)
  if (notificationStore.notifications.length === 0) {
    notificationStore.fetchNotifications()
  }
}

onMounted(() => {
  notificationStore.setMode(true)
  notificationStore.startPolling()
})

onUnmounted(() => {
  notificationStore.stopPolling()
})
</script>

<template>
  <header class="topbar">
    <div class="topbar-start">
      <!-- Mobile sidebar toggle -->
      <Button
        icon="pi pi-bars"
        severity="secondary"
        text
        rounded
        class="topbar-btn lg:!hidden"
        @click="layout.toggleMobileSidebar()"
        aria-label="Toggle sidebar"
      />
      <!-- Desktop sidebar collapse -->
      <Button
        icon="pi pi-bars"
        severity="secondary"
        text
        rounded
        class="topbar-btn !hidden lg:!inline-flex"
        @click="layout.toggleSidebar()"
        aria-label="Collapse sidebar"
      />

      <div class="topbar-brand">
        <img class="brand-logo" src="/klek-ai-mark.svg" alt="Klek AI" />
        <span class="brand-text">Klek AI</span>
      </div>
    </div>

    <div class="topbar-end">
      <!-- System Status -->
      <div class="status-indicator" :class="apiOnline ? 'status-online' : 'status-offline'">
        <span class="status-dot" />
        <span class="status-label">{{ apiOnline ? t('topbar.apiOnline') : t('topbar.apiDown') }}</span>
      </div>

      <!-- Language Switcher -->
      <Button
        :icon="'pi pi-globe'"
        severity="secondary"
        text
        rounded
        class="topbar-btn"
        @click="layout.toggleLocale()"
        v-tooltip.bottom="layout.locale === 'en' ? 'العربية' : 'English'"
        aria-label="Switch language"
      >
        <template #icon>
          <i class="pi pi-globe" />
          <span class="lang-badge">{{ layout.locale === 'en' ? 'EN' : 'AR' }}</span>
        </template>
      </Button>

      <!-- Dark Mode Toggle -->
      <Button
        :icon="layout.darkMode ? 'pi pi-sun' : 'pi pi-moon'"
        severity="secondary"
        text
        rounded
        class="topbar-btn"
        @click="layout.toggleDarkMode()"
        v-tooltip.bottom="layout.darkMode ? t('topbar.lightMode') : t('topbar.darkMode')"
        aria-label="Toggle dark mode"
      />

      <!-- Notifications -->
      <Button
        icon="pi pi-bell"
        severity="secondary"
        text
        rounded
        class="topbar-btn"
        @click="toggleNotifications"
        aria-label="Notifications"
      >
        <template #icon>
          <i class="pi pi-bell" style="font-size: 1.1rem" />
          <Badge v-if="unreadCount > 0" :value="unreadCount" severity="danger" class="notif-badge" />
        </template>
      </Button>

      <Popover ref="notifPanel" class="notif-panel">
        <div class="notif-header">
          <span class="notif-title">{{ t('topbar.notifications') }}</span>
          <button v-if="unreadCount > 0" class="notif-mark-read" @click="notificationStore.markAllRead()">
            {{ t('topbar.markAllRead') }}
          </button>
          <span v-else class="notif-count">{{ unreadCount }} {{ t('common.new') }}</span>
        </div>
        <ul class="notif-list">
          <li v-if="notificationStore.loading" class="notif-empty">{{ t('common.loading') }}...</li>
          <li v-else-if="notifications.length === 0" class="notif-empty">{{ t('topbar.noNotifications') }}</li>
          <li
            v-for="n in notifications"
            :key="n.id"
            class="notif-item"
            :class="{ 'notif-unread': !n.is_read }"
            @click="notificationStore.markRead(n.id)"
          >
            <div class="notif-dot" v-if="!n.is_read" />
            <div class="notif-content">
              <p class="notif-text">{{ n.title }}</p>
              <p class="notif-body">{{ n.body }}</p>
              <span class="notif-time">{{ n.created_at }}</span>
            </div>
          </li>
        </ul>
      </Popover>

      <!-- Profile -->
      <div class="profile-trigger" @click="toggleProfile">
        <div class="avatar">
          <img
            v-if="auth.user.avatar"
            :src="auth.user.avatar"
            :alt="auth.user.name"
          />
          <span v-else class="avatar-initials">
            {{ auth.user.name.split(' ').map(n => n[0]).join('') }}
          </span>
        </div>
        <div class="profile-info">
          <span class="profile-name">{{ auth.user.name }}</span>
          <span class="profile-role">{{ auth.user.roles?.[0] || 'Admin' }}</span>
        </div>
        <i class="pi pi-chevron-down profile-chevron" />
      </div>
      <Menu ref="profileMenu" :model="profileItems" popup class="profile-menu" />
    </div>
  </header>
</template>

<style scoped>
.topbar {
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  background: var(--topbar-bg, #ffffff);
  border-bottom: 1px solid var(--topbar-border, #e2e8f0);
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  gap: 8px;
}

.topbar-start {
  display: flex;
  align-items: center;
  gap: 8px;
}

.topbar-end {
  display: flex;
  align-items: center;
  gap: 4px;
}

.topbar-btn {
  width: 34px !important;
  height: 34px !important;
  color: var(--text-secondary, #64748b) !important;
}

.topbar-brand {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--text-primary, #0f172a);
  user-select: none;
}

.brand-logo {
  width: 32px;
  height: 32px;
  display: block;
  flex-shrink: 0;
  filter: drop-shadow(0 0 10px rgba(129, 140, 248, 0.45)) drop-shadow(0 0 4px rgba(167, 139, 250, 0.35));
  animation: topbar-logo-glow 3s ease-in-out infinite;
}
@keyframes topbar-logo-glow {
  0%, 100% { filter: drop-shadow(0 0 10px rgba(129, 140, 248, 0.45)) drop-shadow(0 0 4px rgba(167, 139, 250, 0.35)); }
  50% { filter: drop-shadow(0 0 18px rgba(129, 140, 248, 0.65)) drop-shadow(0 0 8px rgba(167, 139, 250, 0.5)); }
}

.brand-text {
  letter-spacing: -0.01em;
}

/* Status */
.status-indicator {
  display: none;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

@media (min-width: 768px) {
  .status-indicator {
    display: flex;
  }
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.status-online {
  background: var(--hover-bg, #ecfdf5);
  color: #10b981;
}
.status-online .status-dot {
  background: #10b981;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
}

.status-offline {
  background: var(--hover-bg, #fef2f2);
  color: #ef4444;
}
.status-offline .status-dot {
  background: #ef4444;
  box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.25);
}

/* Language badge */
.lang-badge {
  font-size: 0.55rem;
  font-weight: 700;
  position: absolute;
  bottom: 2px;
  right: 2px;
  letter-spacing: 0.02em;
}

/* Notification badge */
.notif-badge {
  position: absolute;
  top: 2px;
  right: 2px;
  font-size: 0.6rem;
  min-width: 16px;
  height: 16px;
  line-height: 16px;
}

/* Notification panel */
.notif-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  border-bottom: 1px solid var(--topbar-border, #e2e8f0);
}

.notif-title {
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--text-primary, #0f172a);
}

.notif-count {
  font-size: 0.7rem;
  color: var(--primary-color, #6366f1);
  font-weight: 600;
}

.notif-list {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 260px;
  overflow-y: auto;
}

.notif-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 14px;
  border-bottom: 1px solid var(--topbar-border, #f1f5f9);
  transition: background 0.15s;
}

.notif-item:hover {
  background: var(--hover-bg, #f8fafc);
}

.notif-unread {
  background: var(--unread-bg, #f0f4ff);
}

.notif-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--primary-color, #6366f1);
  margin-top: 6px;
  flex-shrink: 0;
}

.notif-text {
  font-size: 0.8rem;
  color: var(--text-primary, #0f172a);
  margin: 0;
  line-height: 1.4;
}

.notif-time {
  font-size: 0.68rem;
  color: var(--text-muted, #94a3b8);
}

.notif-body {
  font-size: 0.72rem;
  color: var(--text-muted, #94a3b8);
  margin: 2px 0 0;
  line-height: 1.3;
}

.notif-mark-read {
  background: none;
  border: none;
  font-size: 0.7rem;
  color: var(--primary-color, #6366f1);
  cursor: pointer;
  font-weight: 600;
  padding: 0;
}

.notif-mark-read:hover {
  text-decoration: underline;
}

.notif-empty {
  padding: 20px 14px;
  text-align: center;
  font-size: 0.8rem;
  color: var(--text-muted, #94a3b8);
}

.notif-item {
  cursor: pointer;
}

/* Profile */
.profile-trigger {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 8px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.15s;
  margin-left: 4px;
}

.profile-trigger:hover {
  background: var(--hover-bg, #f1f5f9);
}

.avatar {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  overflow: hidden;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-initials {
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.03em;
}

.profile-info {
  display: none;
  flex-direction: column;
  line-height: 1.2;
}

@media (min-width: 768px) {
  .profile-info {
    display: flex;
  }
}

.profile-name {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-primary, #0f172a);
}

.profile-role {
  font-size: 0.65rem;
  color: var(--text-muted, #94a3b8);
}

.profile-chevron {
  font-size: 0.6rem;
  color: var(--text-muted, #94a3b8);
  display: none;
}

@media (min-width: 768px) {
  .profile-chevron {
    display: block;
  }
}
</style>
