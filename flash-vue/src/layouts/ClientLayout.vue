<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useLayoutStore } from '@/stores/layout'
import { useChatStore } from '@/stores/chat'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Tooltip from 'primevue/tooltip'
import NotificationToast from '@/components/chat/NotificationToast.vue'

const vTooltip = Tooltip

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const layout = useLayoutStore()
const chat = useChatStore()
const auth = useAuthStore()
const notificationStore = useNotificationStore()

const sidebarOpen = computed({
  get: () => !layout.sidebarCollapsed,
  set: (val: boolean) => { layout.sidebarCollapsed = !val },
})
const mobileSidebarOpen = ref(false)

// Show full sidebar content when expanded OR when mobile sidebar is open
const showSidebarContent = computed(() => sidebarOpen.value || mobileSidebarOpen.value)
const showUserMenu = ref(false)
const showNotifications = ref(false)
const editTitle = ref('')
const searchInputRef = ref<HTMLInputElement | null>(null)
const userMenuRef = ref<HTMLElement | null>(null)
const notificationMenuRef = ref<HTMLElement | null>(null)

const isHome = computed(() => route.name === 'chat' && !chat.activeConversationId)

const notifications = computed(() => notificationStore.notifications)
const unreadCount = computed(() => notificationStore.unreadCount)
const userInitials = computed(() => {
  const parts = auth.user.name.trim().split(/\s+/).filter(Boolean)
  const initials = parts.map(part => part[0]).join('').slice(0, 2).toUpperCase()

  return initials || 'U'
})

function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
}

function toggleMobileSidebar() {
  mobileSidebarOpen.value = !mobileSidebarOpen.value
}

function newChat() {
  chat.setActiveConversation(null)
  void router.push({ name: 'chat' })
  mobileSidebarOpen.value = false
}

function openConversation(id: string) {
  chat.setActiveConversation(id)
  void router.push({ name: 'chat' })
  mobileSidebarOpen.value = false
}

function startRename(id: string, currentTitle: string) {
  chat.startEditing(id)
  editTitle.value = currentTitle
  nextTick(() => {
    const el = document.querySelector('.rename-input') as HTMLInputElement
    el?.focus()
    el?.select()
  })
}

function finishRename(id: string) {
  chat.renameConversation(id, editTitle.value)
}

function cancelRename() {
  chat.cancelEditing()
  editTitle.value = ''
}

function handleRenameKey(e: KeyboardEvent, id: string) {
  if (e.key === 'Enter') finishRename(id)
  if (e.key === 'Escape') cancelRename()
}

function handleDeleteConv(id: string) {
  chat.deleteConversation(id)
}

function handlePinConv(id: string) {
  chat.togglePin(id)
}

function closeOverlays() {
  showUserMenu.value = false
  showNotifications.value = false
}

function goToPricing() {
  closeOverlays()
  void router.push({ name: 'pricing' })
}

function goToLogin() {
  closeOverlays()
  void router.push({ name: 'login' })
}

function goToProfile() {
  closeOverlays()
  void router.push({ name: 'profile' })
}

async function handleLogout() {
  closeOverlays()
  chat.$reset()
  await auth.logout()
}

function markAllRead() {
  notificationStore.markAllRead()
}

function focusSearch() {
  chat.searchQuery = ''
  nextTick(() => searchInputRef.value?.focus())
}

function handleCollapsedSearch() {
  toggleSidebar()
  focusSearch()
}

function toggleNotificationsMenu() {
  showNotifications.value = !showNotifications.value
  if (showNotifications.value) {
    showUserMenu.value = false
    if (notificationStore.notifications.length === 0) {
      notificationStore.fetchNotifications()
    }
  }
}

function toggleUserDropdown() {
  showUserMenu.value = !showUserMenu.value
  if (showUserMenu.value) {
    showNotifications.value = false
  }
}

function handleDocumentPointerDown(e: PointerEvent) {
  const target = e.target as Node
  if (showUserMenu.value && userMenuRef.value && !userMenuRef.value.contains(target)) {
    showUserMenu.value = false
  }
  if (showNotifications.value && notificationMenuRef.value && !notificationMenuRef.value.contains(target)) {
    showNotifications.value = false
  }
}

onMounted(() => {
  document.addEventListener('pointerdown', handleDocumentPointerDown)
  if (auth.isAuthenticated) {
    notificationStore.setMode(false)
    notificationStore.startPolling()
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', handleDocumentPointerDown)
  notificationStore.stopPolling()
})
</script>

<template>
  <div class="client-layout" :class="{ 'sidebar-open': sidebarOpen, 'sidebar-closed': !sidebarOpen }">
    <!-- Mobile overlay -->
    <Transition name="fade">
      <div v-if="mobileSidebarOpen" class="mobile-overlay" @click="mobileSidebarOpen = false" />
    </Transition>

    <!-- Notification Toast -->
    <NotificationToast />

    <!-- ═══════════════ Sidebar ═══════════════ -->
    <aside class="client-sidebar" :class="{ 'mobile-open': mobileSidebarOpen }">
      <!-- Header -->
      <div class="sidebar-header">
        <div class="brand" v-if="showSidebarContent">
          <span class="brand-icon"><i class="pi pi-sparkles" /></span>
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

      <!-- New Chat Button -->
      <div class="sidebar-new-chat" v-if="showSidebarContent">
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
        <Button icon="pi pi-plus" severity="secondary" text rounded size="small" @click="newChat"
          v-tooltip.left="t('client.newChat')" />
      </div>

      <!-- Search -->
      <div class="sidebar-search" v-if="showSidebarContent">
        <div class="search-box">
          <i class="pi pi-search search-icon" />
          <input
            ref="searchInputRef"
            v-model="chat.searchQuery"
            type="text"
            class="search-input"
            :placeholder="t('chat.searchConversations')"
          />
          <button v-if="chat.searchQuery" class="search-clear" @click="chat.searchQuery = ''">
            <i class="pi pi-times" />
          </button>
        </div>
      </div>
      <div class="sidebar-search" v-else>
        <Button icon="pi pi-search" severity="secondary" text rounded size="small" @click="handleCollapsedSearch"
          v-tooltip.left="t('chat.searchConversations')" />
      </div>

      <!-- Conversations list -->
      <div class="sidebar-conversations" v-if="showSidebarContent">
        <!-- Search results -->
        <template v-if="chat.filteredConversations">
          <div class="conv-section-label">{{ t('chat.searchResults') }}</div>
          <div
            v-for="conv in chat.filteredConversations"
            :key="conv.id"
            class="conv-item"
            :class="{ active: chat.activeConversationId === conv.id }"
            @click="openConversation(conv.id)"
          >
            <i class="pi pi-comment conv-icon" />
            <div class="conv-text">
              <span class="conv-title">{{ conv.title }}</span>
            </div>
          </div>
          <div v-if="!chat.filteredConversations.length" class="conv-empty">
            <i class="pi pi-search" />
            <span>{{ t('chat.noResults') }}</span>
          </div>
        </template>

        <!-- Normal list -->
        <template v-else>
          <!-- Pinned -->
          <template v-if="chat.pinnedConversations.length">
            <div class="conv-section-label">
              <i class="pi pi-bookmark-fill" style="font-size: 0.58rem" />
              {{ t('chat.pinned') }}
            </div>
            <div
              v-for="conv in chat.pinnedConversations"
              :key="conv.id"
              class="conv-item"
              :class="{ active: chat.activeConversationId === conv.id }"
              @click="openConversation(conv.id)"
            >
              <i class="pi pi-bookmark-fill conv-icon pinned" />
              <template v-if="chat.editingConversationId === conv.id">
                <input
                  v-model="editTitle"
                  class="rename-input"
                  @keydown="handleRenameKey($event, conv.id)"
                  @blur="finishRename(conv.id)"
                />
              </template>
              <template v-else>
                <div class="conv-text">
                  <span class="conv-title">{{ conv.title }}</span>
                </div>
                <div class="conv-actions">
                  <button class="conv-action-btn" @click.stop="startRename(conv.id, conv.title)" :title="t('chat.rename')">
                    <i class="pi pi-pencil" />
                  </button>
                  <button class="conv-action-btn" @click.stop="handlePinConv(conv.id)" :title="t('chat.unpin')">
                    <i class="pi pi-bookmark" />
                  </button>
                  <button class="conv-action-btn danger" @click.stop="handleDeleteConv(conv.id)" :title="t('chat.deleteChat')">
                    <i class="pi pi-trash" />
                  </button>
                </div>
              </template>
            </div>
          </template>

          <!-- Recent -->
          <div class="conv-section-label">{{ t('client.recentChats') }}</div>
          <TransitionGroup name="conv-list" tag="div">
            <div
              v-for="conv in chat.unpinnedConversations"
              :key="conv.id"
              class="conv-item"
              :class="{ active: chat.activeConversationId === conv.id }"
              @click="openConversation(conv.id)"
            >
              <i class="pi pi-comment conv-icon" />
              <template v-if="chat.editingConversationId === conv.id">
                <input
                  v-model="editTitle"
                  class="rename-input"
                  @keydown="handleRenameKey($event, conv.id)"
                  @blur="finishRename(conv.id)"
                />
              </template>
              <template v-else>
                <div class="conv-text">
                  <span class="conv-title">{{ conv.title }}</span>
                </div>
                <div class="conv-actions">
                  <button class="conv-action-btn" @click.stop="startRename(conv.id, conv.title)" :title="t('chat.rename')">
                    <i class="pi pi-pencil" />
                  </button>
                  <button class="conv-action-btn" @click.stop="handlePinConv(conv.id)" :title="t('chat.pin')">
                    <i class="pi pi-bookmark" />
                  </button>
                  <button class="conv-action-btn danger" @click.stop="handleDeleteConv(conv.id)" :title="t('chat.deleteChat')">
                    <i class="pi pi-trash" />
                  </button>
                </div>
              </template>
            </div>
          </TransitionGroup>
        </template>
      </div>

      <!-- Collapsed icons -->
      <div class="sidebar-conversations sidebar-icons-only" v-else>
        <Button
          v-for="conv in chat.conversations.slice(0, 6)"
          :key="conv.id"
          :icon="conv.pinned ? 'pi pi-bookmark-fill' : 'pi pi-comment'"
          severity="secondary"
          text
          rounded
          size="small"
          :class="{ 'active-icon': chat.activeConversationId === conv.id }"
          @click="openConversation(conv.id)"
          v-tooltip.left="conv.title"
        />
      </div>

      <!-- Footer -->
      <div class="sidebar-footer" v-if="showSidebarContent">
        <Button :label="t('client.pricing')" icon="pi pi-tag" severity="secondary" text size="small" class="footer-link" @click="goToPricing" />
      </div>
      <div class="sidebar-footer" v-else>
        <Button icon="pi pi-tag" severity="secondary" text rounded size="small" @click="goToPricing" v-tooltip.left="t('client.pricing')" />
      </div>
    </aside>

    <!-- ═══════════════ Main ═══════════════ -->
    <div class="client-main">
      <!-- Topbar -->
      <header class="client-topbar">
        <div class="topbar-start">
          <Button icon="pi pi-bars" severity="secondary" text rounded size="small" class="mobile-menu-btn" @click="toggleMobileSidebar" />
          <router-link :to="{ name: 'chat' }" class="topbar-brand mobile-brand">
            <span class="brand-icon-sm"><i class="pi pi-sparkles" /></span>
            <span class="brand-label">Klek AI</span>
          </router-link>
          <!-- Current conversation model indicator -->
          <div class="model-badge desktop-only" v-if="chat.activeConversation">
            <i class="pi pi-sparkles" />
            <span>Klek AI v2</span>
          </div>
        </div>

        <nav class="topbar-nav">
          <router-link :to="{ name: 'chat' }" class="nav-link" :class="{ active: route.name === 'chat' }">
            {{ t('client.home') }}
          </router-link>
          <router-link :to="{ name: 'pricing' }" class="nav-link" :class="{ active: route.name === 'pricing' }">
            {{ t('client.pricing') }}
          </router-link>
          <router-link :to="{ name: 'profile' }" class="nav-link" :class="{ active: route.name === 'profile' }">
            {{ t('chat.myProfile') }}
          </router-link>
        </nav>

        <div class="topbar-end">
          <!-- Theme -->
          <Button
            :icon="layout.darkMode ? 'pi pi-sun' : 'pi pi-moon'"
            severity="secondary" text rounded size="small"
            @click="layout.toggleDarkMode()"
            v-tooltip.bottom="t('client.toggleTheme')"
          />
          <!-- Language -->
          <Button icon="pi pi-globe" severity="secondary" text rounded size="small"
            @click="layout.toggleLocale()"
            v-tooltip.bottom="layout.locale === 'ar' ? 'English' : 'العربية'"
          />
          <!-- Notifications -->
          <div class="notification-container" ref="notificationMenuRef">
            <Button icon="pi pi-bell" severity="secondary" text rounded size="small"
              class="notif-btn"
              :class="{ 'has-unread': unreadCount > 0 }"
              @click="toggleNotificationsMenu"
            />
            <span v-if="unreadCount > 0" class="notif-badge">{{ unreadCount }}</span>

            <Transition name="pop">
              <div v-if="showNotifications" class="notif-dropdown">
                <div class="notif-header">
                  <span class="notif-title">{{ t('chat.notifications') }}</span>
                  <button class="notif-mark-read" @click="markAllRead">{{ t('chat.markAllRead') }}</button>
                </div>
                <div class="notif-list">
                  <div v-if="notificationStore.loading" class="notif-empty">{{ t('common.loading') }}...</div>
                  <div v-else-if="notifications.length === 0" class="notif-empty">{{ t('topbar.noNotifications') }}</div>
                  <div
                    v-for="notif in notifications"
                    :key="notif.id"
                    class="notif-item"
                    :class="{ unread: !notif.is_read }"
                    @click="notificationStore.markRead(notif.id)"
                  >
                    <div class="notif-dot" v-if="!notif.is_read" />
                    <div class="notif-content">
                      <span class="notif-text">{{ notif.title }}</span>
                      <span class="notif-body">{{ notif.body }}</span>
                      <span class="notif-time">{{ notif.created_at }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </Transition>
          </div>

          <!-- User / Login -->
          <div v-if="auth.isAuthenticated" class="user-container" ref="userMenuRef">
            <button type="button" class="user-avatar-btn" @click.stop="toggleUserDropdown">
              <div class="user-avatar">
                <img v-if="auth.user.avatar" :src="auth.user.avatar" :alt="auth.user.name" />
                <span v-else>{{ userInitials }}</span>
              </div>
            </button>
            <Transition name="pop">
              <div v-if="showUserMenu" class="user-dropdown" @click.stop>
                <div class="user-info">
                  <div class="user-avatar-lg">
                    <img v-if="auth.user.avatar" :src="auth.user.avatar" :alt="auth.user.name" />
                    <span v-else>{{ userInitials }}</span>
                  </div>
                  <div>
                    <div class="user-name">{{ auth.user.name }}</div>
                    <div class="user-email">{{ auth.user.email }}</div>
                  </div>
                </div>
                <div class="user-menu-divider" />
                <button type="button" class="user-menu-item" @click="goToProfile">
                  <i class="pi pi-user" />
                  <span>{{ t('chat.myProfile') }}</span>
                </button>
                <button type="button" class="user-menu-item" @click="goToPricing">
                  <i class="pi pi-tag" />
                  <span>{{ t('client.pricing') }}</span>
                </button>
                <div class="user-menu-divider" />
                <button type="button" class="user-menu-item danger" @click="handleLogout">
                  <i class="pi pi-sign-out" />
                  <span>{{ t('chat.logout') }}</span>
                </button>
              </div>
            </Transition>
          </div>
          <Button v-else :label="t('client.startNow')" icon="pi pi-user-plus" size="small" class="start-btn" @click="goToLogin" />
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
  --client-sidebar-width: 240px;
  --client-sidebar-collapsed: 48px;
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
  transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
}

.sidebar-closed .client-sidebar {
  width: var(--client-sidebar-collapsed);
}

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 10px;
  gap: 6px;
  min-height: 42px;
}

.brand {
  display: flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
  animation: fadeIn 0.2s;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.brand-icon {
  width: 24px;
  height: 24px;
  border-radius: 6px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.brand-text {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-primary);
}

.sidebar-new-chat {
  padding: 0 10px 6px;
}

.new-chat-btn {
  width: 100%;
  justify-content: flex-start;
  gap: 8px;
  font-size: 0.78rem !important;
  border-style: dashed !important;
  transition: border-color 0.2s, background 0.2s !important;
}

.new-chat-btn:hover {
  border-color: var(--active-color) !important;
  background: var(--active-bg) !important;
}

/* ── Search ── */
.sidebar-search {
  padding: 0 10px 6px;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--hover-bg);
  border-radius: 10px;
  padding: 0 10px;
  transition: background 0.2s, box-shadow 0.2s;
}

.search-box:focus-within {
  background: var(--card-bg);
  box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.search-icon {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.search-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 0.74rem;
  color: var(--text-primary);
  padding: 6px 0;
  min-width: 0;
}

.search-input::placeholder {
  color: var(--text-muted);
}

.search-clear {
  border: none;
  background: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 2px;
  font-size: 0.65rem;
  border-radius: 4px;
}

.search-clear:hover {
  color: var(--text-primary);
}

/* ── Conversations ── */
.sidebar-conversations {
  flex: 1;
  overflow-y: auto;
  padding: 4px 8px;
  scrollbar-width: thin;
  scrollbar-color: var(--card-border) transparent;
}

.sidebar-icons-only {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 4px;
}

.active-icon {
  background: var(--active-bg) !important;
  color: var(--active-color) !important;
}

.conv-section-label {
  font-size: 0.6rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 8px 8px 4px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.conv-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 24px 8px;
  color: var(--text-muted);
  font-size: 0.76rem;
}

.conv-empty i {
  font-size: 1.2rem;
  opacity: 0.5;
}

.conv-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 8px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.14s;
  overflow: hidden;
  position: relative;
}

.conv-item:hover {
  background: var(--hover-bg);
}

.conv-item.active {
  background: var(--active-bg);
}

.conv-item.active .conv-icon {
  color: var(--active-color);
}

.conv-item.active .conv-title {
  color: var(--active-color);
  font-weight: 600;
}

.conv-icon {
  font-size: 0.8rem;
  color: var(--text-muted);
  flex-shrink: 0;
  transition: color 0.14s;
}

.conv-icon.pinned {
  color: var(--active-color);
  font-size: 0.72rem;
}

.conv-text {
  min-width: 0;
  flex: 1;
}

.conv-title {
  font-size: 0.74rem;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
  transition: color 0.14s;
}

/* Rename input */
.rename-input {
  flex: 1;
  min-width: 0;
  border: 1px solid var(--active-color);
  background: var(--card-bg);
  border-radius: 6px;
  padding: 3px 8px;
  font-size: 0.78rem;
  color: var(--text-primary);
  outline: none;
}

/* Hover actions */
.conv-actions {
  display: flex;
  gap: 1px;
  opacity: 0;
  transition: opacity 0.15s;
  flex-shrink: 0;
}

.conv-item:hover .conv-actions {
  opacity: 1;
}

.conv-action-btn {
  border: none;
  background: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px 5px;
  border-radius: 6px;
  font-size: 0.68rem;
  transition: color 0.14s, background 0.14s;
}

.conv-action-btn:hover {
  color: var(--text-primary);
  background: var(--hover-bg);
}

.conv-action-btn.danger:hover {
  color: #ef4444;
  background: rgba(239, 68, 68, 0.08);
}

/* Conversation list animation */
.conv-list-enter-active {
  transition: all 0.25s ease-out;
}

.conv-list-leave-active {
  transition: all 0.2s ease-in;
}

.conv-list-enter-from {
  opacity: 0;
  transform: translateX(-12px);
}

.conv-list-leave-to {
  opacity: 0;
  transform: translateX(12px);
  height: 0;
  padding: 0 10px;
  margin: 0;
  overflow: hidden;
}

.conv-list-move {
  transition: transform 0.25s;
}

.sidebar-footer {
  padding: 6px;
  border-top: 1px solid var(--sidebar-border);
}

.footer-link {
  width: 100%;
  justify-content: flex-start;
  font-size: 0.72rem !important;
}

/* ── Main area ── */
.client-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  margin-inline-start: var(--client-sidebar-width);
  transition: margin-inline-start 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-closed .client-main {
  margin-inline-start: var(--client-sidebar-collapsed);
}

/* ── Topbar ── */
.client-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 42px;
  padding: 0 12px;
  background: var(--topbar-bg);
  border-bottom: 1px solid var(--topbar-border);
  gap: 8px;
  position: sticky;
  top: 0;
  z-index: 50;
  backdrop-filter: blur(12px);
  background: rgba(255, 255, 255, 0.85);
}

html.dark .client-topbar {
  background: rgba(24, 24, 27, 0.85);
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

.model-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 8px;
  background: var(--active-bg);
  color: var(--active-color);
  font-size: 0.72rem;
  font-weight: 600;
}

.model-badge i {
  font-size: 0.65rem;
}

.topbar-nav {
  display: flex;
  align-items: center;
  gap: 4px;
}

.nav-link {
  font-size: 0.74rem;
  color: var(--text-secondary);
  padding: 4px 10px;
  border-radius: 6px;
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

/* ── Notifications ── */
.notification-container {
  position: relative;
}

.notif-btn.has-unread {
  color: var(--active-color) !important;
}

.notif-badge {
  position: absolute;
  top: -2px;
  inset-inline-end: -2px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #ef4444;
  color: #fff;
  font-size: 0.58rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  animation: badgePop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes badgePop {
  from { transform: scale(0); }
  to { transform: scale(1); }
}

.notif-dropdown {
  position: absolute;
  top: 100%;
  inset-inline-end: 0;
  margin-top: 8px;
  width: 320px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 14px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.14);
  z-index: 60;
  overflow: hidden;
}

.notif-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-bottom: 1px solid var(--card-border);
}

.notif-title {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-primary);
}

.notif-mark-read {
  font-size: 0.7rem;
  color: var(--active-color);
  border: none;
  background: none;
  cursor: pointer;
  font-weight: 500;
}

.notif-mark-read:hover {
  text-decoration: underline;
}

.notif-list {
  max-height: 280px;
  overflow-y: auto;
}

.notif-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 14px;
  transition: background 0.14s;
  cursor: pointer;
}

.notif-item:hover {
  background: var(--hover-bg);
}

.notif-item.unread {
  background: var(--unread-bg);
}

.notif-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--active-color);
  flex-shrink: 0;
  margin-top: 5px;
}

.notif-content {
  min-width: 0;
}

.notif-text {
  font-size: 0.78rem;
  color: var(--text-primary);
  display: block;
  line-height: 1.4;
}

.notif-time {
  font-size: 0.66rem;
  color: var(--text-muted);
}

.notif-body {
  font-size: 0.72rem;
  color: var(--text-muted);
  display: block;
  line-height: 1.3;
  margin-top: 2px;
}

.notif-empty {
  padding: 20px 14px;
  text-align: center;
  font-size: 0.8rem;
  color: var(--text-muted);
}

.notif-item {
  cursor: pointer;
  margin-top: 2px;
  display: block;
}

/* ── User menu ── */
.user-container {
  position: relative;
}

.user-avatar-btn {
  border: none;
  background: none;
  cursor: pointer;
  padding: 0;
}

.user-avatar {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.78rem;
  font-weight: 600;
  transition: transform 0.15s, box-shadow 0.15s;
  overflow: hidden;
}

.user-avatar img,
.user-avatar-lg img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-avatar:hover {
  transform: scale(1.05);
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}

.user-dropdown {
  position: absolute;
  top: 100%;
  inset-inline-end: 0;
  margin-top: 8px;
  width: 260px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 14px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.14);
  z-index: 60;
  overflow: hidden;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
}

.user-avatar-lg {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  font-weight: 600;
  flex-shrink: 0;
  overflow: hidden;
}

.user-name {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-primary);
}

.user-email {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-top: 2px;
}

.user-menu-divider {
  height: 1px;
  background: var(--card-border);
  margin: 0 8px;
}

.user-menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 14px;
  border: none;
  background: none;
  color: var(--text-secondary);
  font-size: 0.78rem;
  cursor: pointer;
  transition: background 0.14s, color 0.14s;
}

.user-menu-item:hover {
  background: var(--hover-bg);
  color: var(--text-primary);
}

.user-menu-item.danger:hover {
  color: #ef4444;
  background: rgba(239, 68, 68, 0.06);
}

.user-menu-item i {
  font-size: 0.82rem;
  width: 18px;
  text-align: center;
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
  backdrop-filter: blur(2px);
}

.desktop-only {
  display: inline-flex;
}

.collapse-btn {
  flex-shrink: 0;
}

/* ── Transitions ── */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.pop-enter-active {
  transition: opacity 0.15s, transform 0.15s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.pop-leave-active {
  transition: opacity 0.1s, transform 0.1s;
}

.pop-enter-from {
  opacity: 0;
  transform: scale(0.92) translateY(-4px);
}

.pop-leave-to {
  opacity: 0;
  transform: scale(0.96) translateY(-2px);
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

  .notif-dropdown {
    width: calc(100vw - 32px);
    inset-inline-end: -60px;
  }

  .user-dropdown {
    width: calc(100vw - 32px);
    inset-inline-end: -16px;
  }

  .start-btn :deep(.p-button-label) {
    display: none;
  }
}
</style>
