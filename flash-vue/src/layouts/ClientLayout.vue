<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useLayoutStore } from '@/stores/layout'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Tooltip from 'primevue/tooltip'

const vTooltip = Tooltip

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const layout = useLayoutStore()

const sidebarOpen = ref(true)
const mobileSidebarOpen = ref(false)

// Mock conversations
const conversations = ref([
  { id: 1, title: 'تصميم شعار احترافي', date: '2026-04-04', preview: 'أريد شعار بألوان...' },
  { id: 2, title: 'صورة منتج للتسويق', date: '2026-04-03', preview: 'صورة عالية الدقة...' },
  { id: 3, title: 'تعديل خلفية الصورة', date: '2026-04-02', preview: 'إزالة الخلفية و...' },
  { id: 4, title: 'رسم توضيحي تعليمي', date: '2026-04-01', preview: 'رسم يوضح كيفية...' },
  { id: 5, title: 'تحويل صورة لكرتون', date: '2026-03-31', preview: 'تحويل الصورة إلى...' },
])

const isHome = computed(() => route.name === 'home')

function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
}

function toggleMobileSidebar() {
  mobileSidebarOpen.value = !mobileSidebarOpen.value
}

function newChat() {
  router.push({ name: 'home' })
  mobileSidebarOpen.value = false
}

function openConversation(id: number) {
  // Future: navigate to conversation
  mobileSidebarOpen.value = false
}

function goToPricing() {
  router.push({ name: 'pricing' })
}

function goToLogin() {
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="client-layout" :class="{ 'sidebar-open': sidebarOpen, 'sidebar-closed': !sidebarOpen }">
    <!-- Mobile overlay -->
    <div v-if="mobileSidebarOpen" class="mobile-overlay" @click="mobileSidebarOpen = false" />

    <!-- Sidebar -->
    <aside class="client-sidebar" :class="{ 'mobile-open': mobileSidebarOpen }">
      <div class="sidebar-header">
        <div class="brand" v-if="sidebarOpen">
          <span class="brand-icon">
            <i class="pi pi-sparkles" />
          </span>
          <span class="brand-text">Klek AI</span>
        </div>
        <Button
          :icon="sidebarOpen ? 'pi pi-chevron-right' : 'pi pi-chevron-left'"
          severity="secondary"
          text
          rounded
          size="small"
          class="collapse-btn desktop-only"
          @click="toggleSidebar"
          v-tooltip.left="sidebarOpen ? t('client.collapseSidebar') : t('client.expandSidebar')"
        />
      </div>

      <div class="sidebar-new-chat" v-if="sidebarOpen">
        <Button
          :label="t('client.newChat')"
          icon="pi pi-plus"
          severity="secondary"
          outlined
          size="small"
          class="new-chat-btn"
          @click="newChat"
        />
      </div>
      <div class="sidebar-new-chat" v-else>
        <Button
          icon="pi pi-plus"
          severity="secondary"
          text
          rounded
          size="small"
          @click="newChat"
          v-tooltip.left="t('client.newChat')"
        />
      </div>

      <div class="sidebar-conversations" v-if="sidebarOpen">
        <div class="conv-section-label">{{ t('client.recentChats') }}</div>
        <div
          v-for="conv in conversations"
          :key="conv.id"
          class="conv-item"
          @click="openConversation(conv.id)"
        >
          <i class="pi pi-comment conv-icon" />
          <div class="conv-text">
            <span class="conv-title">{{ conv.title }}</span>
          </div>
        </div>
      </div>
      <div class="sidebar-conversations sidebar-icons-only" v-else>
        <Button
          v-for="conv in conversations.slice(0, 6)"
          :key="conv.id"
          icon="pi pi-comment"
          severity="secondary"
          text
          rounded
          size="small"
          @click="openConversation(conv.id)"
          v-tooltip.left="conv.title"
        />
      </div>

      <div class="sidebar-footer" v-if="sidebarOpen">
        <Button
          :label="t('client.pricing')"
          icon="pi pi-tag"
          severity="secondary"
          text
          size="small"
          class="footer-link"
          @click="goToPricing"
        />
      </div>
      <div class="sidebar-footer" v-else>
        <Button
          icon="pi pi-tag"
          severity="secondary"
          text
          rounded
          size="small"
          @click="goToPricing"
          v-tooltip.left="t('client.pricing')"
        />
      </div>
    </aside>

    <!-- Main -->
    <div class="client-main">
      <!-- Topbar -->
      <header class="client-topbar">
        <div class="topbar-start">
          <Button
            icon="pi pi-bars"
            severity="secondary"
            text
            rounded
            size="small"
            class="mobile-menu-btn"
            @click="toggleMobileSidebar"
          />
          <router-link :to="{ name: 'home' }" class="topbar-brand mobile-brand">
            <span class="brand-icon-sm"><i class="pi pi-sparkles" /></span>
            <span class="brand-label">Klek AI</span>
          </router-link>
        </div>

        <nav class="topbar-nav">
          <router-link :to="{ name: 'home' }" class="nav-link" :class="{ active: isHome }">
            {{ t('client.home') }}
          </router-link>
          <router-link :to="{ name: 'pricing' }" class="nav-link" :class="{ active: route.name === 'pricing' }">
            {{ t('client.pricing') }}
          </router-link>
        </nav>

        <div class="topbar-end">
          <Button
            :icon="layout.darkMode ? 'pi pi-sun' : 'pi pi-moon'"
            severity="secondary"
            text
            rounded
            size="small"
            @click="layout.toggleDarkMode()"
            v-tooltip.bottom="t('client.toggleTheme')"
          />
          <Button
            icon="pi pi-globe"
            severity="secondary"
            text
            rounded
            size="small"
            @click="layout.toggleLocale()"
            v-tooltip.bottom="layout.locale === 'ar' ? 'English' : 'العربية'"
          />
          <Button
            :label="t('client.startNow')"
            icon="pi pi-user-plus"
            size="small"
            class="start-btn"
            @click="goToLogin"
          />
        </div>
      </header>

      <!-- Content -->
      <main class="client-content">
        <router-view />
      </main>
    </div>
  </div>
</template>

<style scoped>
.client-layout {
  display: flex;
  min-height: 100vh;
  background: var(--layout-bg);
  --client-sidebar-width: 260px;
  --client-sidebar-collapsed: 56px;
}

/* ── Sidebar ── */
.client-sidebar {
  position: fixed;
  inset-block: 0;
  inset-inline-start: 0;
  width: var(--client-sidebar-width);
  background: var(--sidebar-bg);
  border-inline-end: 1px solid var(--sidebar-border);
  display: flex;
  flex-direction: column;
  z-index: 100;
  transition: width 0.2s ease;
  overflow: hidden;
}

.sidebar-closed .client-sidebar {
  width: var(--client-sidebar-collapsed);
}

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 12px;
  gap: 8px;
  min-height: 52px;
}

.brand {
  display: flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}

.brand-icon {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.brand-text {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
}

.sidebar-new-chat {
  padding: 0 12px 8px;
}

.new-chat-btn {
  width: 100%;
  justify-content: flex-start;
  gap: 8px;
  font-size: 0.78rem !important;
  border-style: dashed !important;
}

.sidebar-conversations {
  flex: 1;
  overflow-y: auto;
  padding: 4px 8px;
}

.sidebar-icons-only {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 4px;
}

.conv-section-label {
  font-size: 0.64rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 8px 8px 6px;
}

.conv-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 10px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.14s;
  overflow: hidden;
}

.conv-item:hover {
  background: var(--hover-bg);
}

.conv-icon {
  font-size: 0.8rem;
  color: var(--text-muted);
  flex-shrink: 0;
}

.conv-text {
  min-width: 0;
  flex: 1;
}

.conv-title {
  font-size: 0.78rem;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
}

.sidebar-footer {
  padding: 8px;
  border-top: 1px solid var(--sidebar-border);
}

.footer-link {
  width: 100%;
  justify-content: flex-start;
  font-size: 0.76rem !important;
}

/* ── Main area ── */
.client-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  margin-inline-start: var(--client-sidebar-width);
  transition: margin-inline-start 0.2s ease;
}

.sidebar-closed .client-main {
  margin-inline-start: var(--client-sidebar-collapsed);
}

/* ── Topbar ── */
.client-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 52px;
  padding: 0 16px;
  background: var(--topbar-bg);
  border-bottom: 1px solid var(--topbar-border);
  gap: 12px;
  position: sticky;
  top: 0;
  z-index: 50;
}

.topbar-start {
  display: flex;
  align-items: center;
  gap: 8px;
}

.mobile-menu-btn {
  display: none;
}

.mobile-brand {
  display: none;
  align-items: center;
  gap: 6px;
  text-decoration: none;
}

.brand-icon-sm {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.72rem;
}

.brand-label {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text-primary);
}

.topbar-nav {
  display: flex;
  align-items: center;
  gap: 4px;
}

.nav-link {
  font-size: 0.8rem;
  color: var(--text-secondary);
  padding: 6px 14px;
  border-radius: 8px;
  transition: color 0.14s, background 0.14s;
  text-decoration: none;
  font-weight: 500;
}

.nav-link:hover {
  color: var(--text-primary);
  background: var(--hover-bg);
}

.nav-link.active {
  color: var(--active-color);
  background: var(--active-bg);
  font-weight: 600;
}

.topbar-end {
  display: flex;
  align-items: center;
  gap: 4px;
}

.start-btn {
  font-size: 0.78rem !important;
  border-radius: 8px !important;
  font-weight: 600 !important;
}

/* ── Content ── */
.client-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow-x: hidden;
}

/* ── Mobile overlay ── */
.mobile-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 99;
}

.desktop-only {
  display: inline-flex;
}

.collapse-btn {
  flex-shrink: 0;
}

/* ── Responsive ── */
@media (max-width: 767px) {
  .client-sidebar {
    transform: translateX(-100%);
    width: var(--client-sidebar-width) !important;
  }

  [dir='rtl'] .client-sidebar {
    transform: translateX(100%);
  }

  .client-sidebar.mobile-open {
    transform: translateX(0);
  }

  .mobile-overlay {
    display: block;
  }

  .client-main {
    margin-inline-start: 0 !important;
  }

  .mobile-menu-btn {
    display: inline-flex;
  }

  .mobile-brand {
    display: flex;
  }

  .desktop-only,
  .topbar-nav {
    display: none;
  }
}
</style>
