<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useLayoutStore } from '@/stores/layout'
import Tooltip from 'primevue/tooltip'

const vTooltip = Tooltip

const route = useRoute()
const layout = useLayoutStore()
const { t } = useI18n()

interface NavItem {
  labelKey: string
  icon: string
  to: string
}

interface NavGroup {
  titleKey: string
  items: NavItem[]
}

const navigation: NavGroup[] = [
  {
    titleKey: 'sidebar.main',
    items: [
      { labelKey: 'sidebar.dashboard', icon: 'pi pi-objects-column', to: '/admin' },
    ],
  },
  {
    titleKey: 'sidebar.management',
    items: [
      { labelKey: 'sidebar.users', icon: 'pi pi-users', to: '/admin/users' },
      { labelKey: 'sidebar.rolesPermissions', icon: 'pi pi-shield', to: '/admin/roles' },
    ],
  },
  {
    titleKey: 'sidebar.aiSystem',
    items: [
      { labelKey: 'sidebar.aiRequests', icon: 'pi pi-microchip-ai', to: '/admin/ai-requests' },
      { labelKey: 'sidebar.plansFeatures', icon: 'pi pi-box', to: '/admin/plans' },
      { labelKey: 'sidebar.visualStyles', icon: 'pi pi-palette', to: '/admin/styles' },
    ],
  },
  {
    titleKey: 'sidebar.finance',
    items: [
      { labelKey: 'sidebar.paymentsBilling', icon: 'pi pi-credit-card', to: '/admin/payments' },
    ],
  },
  {
    titleKey: 'sidebar.support',
    items: [
      { labelKey: 'sidebar.supportTickets', icon: 'pi pi-ticket', to: '/admin/support' },
      { labelKey: 'sidebar.systemSettings', icon: 'pi pi-cog', to: '/admin/settings' },
    ],
  },
]

function isActive(to: string): boolean {
  if (to === '/admin') return route.path === '/admin'
  return route.path.startsWith(to)
}

const collapsed = computed(() => layout.sidebarCollapsed)
</script>

<template>
  <!-- Mobile overlay -->
  <Transition name="fade">
    <div
      v-if="layout.sidebarMobileOpen"
      class="sidebar-overlay lg:hidden"
      @click="layout.closeMobileSidebar()"
    />
  </Transition>

  <aside
    class="sidebar"
    :class="{
      'sidebar-collapsed': collapsed,
      'sidebar-mobile-open': layout.sidebarMobileOpen,
    }"
  >
    <nav class="sidebar-nav">
      <div v-for="(group, gi) in navigation" :key="gi" class="nav-group">
        <div class="nav-group-title" v-show="!collapsed">
          {{ t(group.titleKey) }}
        </div>
        <div v-show="collapsed" class="nav-group-divider" v-if="gi > 0" />

        <router-link
          v-for="item in group.items"
          :key="item.to"
          :to="item.to"
          class="nav-item"
          :class="{ 'nav-item-active': isActive(item.to) }"
          v-tooltip.right="collapsed ? t(item.labelKey) : undefined"
          @click="layout.closeMobileSidebar()"
        >
          <i :class="item.icon" class="nav-icon" />
          <span class="nav-label" v-show="!collapsed">{{ t(item.labelKey) }}</span>
        </router-link>
      </div>
    </nav>

    <div class="sidebar-footer">
      <router-link to="/" class="nav-item platform-link" v-tooltip.right="collapsed ? t('topbar.goToPlatform') : undefined" @click="layout.closeMobileSidebar()">
        <i class="pi pi-external-link nav-icon" />
        <span class="nav-label" v-show="!collapsed">{{ t('topbar.goToPlatform') }}</span>
      </router-link>
      <div class="footer-version" v-show="!collapsed">
        <img class="footer-logo" src="/klek-ai-mark.svg" alt="Klek AI" />
        <span>Klek AI v1.0</span>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.sidebar {
  position: fixed;
  top: 52px;
  inset-inline-start: 0;
  bottom: 0;
  width: 230px;
  background: var(--sidebar-bg, #ffffff);
  border-inline-end: 1px solid var(--sidebar-border, #e2e8f0);
  display: flex;
  flex-direction: column;
  z-index: 90;
  transition: width 0.2s ease, transform 0.25s ease;
  overflow: hidden;
}

.sidebar-collapsed {
  width: 60px;
}

/* Mobile: hidden by default, slide in when open */
@media (max-width: 1023px) {
  .sidebar {
    transform: translateX(-100%);
    width: 250px;
    box-shadow: none;
  }
  [dir="rtl"] .sidebar {
    transform: translateX(100%);
  }
  .sidebar-mobile-open {
    transform: translateX(0) !important;
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.1);
  }
  .sidebar-collapsed {
    width: 250px;
  }
}

.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.3);
  z-index: 89;
}

.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 8px;
}

/* Scrollbar */
.sidebar-nav::-webkit-scrollbar {
  width: 3px;
}
.sidebar-nav::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}

/* Group */
.nav-group {
  margin-bottom: 4px;
}

.nav-group-title {
  font-size: 0.62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-muted, #94a3b8);
  padding: 10px 12px 4px;
  white-space: nowrap;
  overflow: hidden;
}

.nav-group-divider {
  height: 1px;
  background: var(--sidebar-border, #e2e8f0);
  margin: 6px 10px;
}

/* Nav item */
.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 500;
  color: var(--text-secondary, #64748b);
  text-decoration: none;
  transition: all 0.15s;
  white-space: nowrap;
  overflow: hidden;
  position: relative;
}

.nav-item:hover {
  background: var(--hover-bg, #f1f5f9);
  color: var(--text-primary, #0f172a);
}

.nav-item-active {
  background: var(--active-bg, #eef2ff);
  color: var(--active-color, #4f46e5);
  font-weight: 600;
}

.nav-item-active .nav-icon {
  color: var(--active-color, #4f46e5);
}

.nav-item-active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 3px;
  height: 18px;
  border-radius: 0 3px 3px 0;
  background: var(--active-color, #4f46e5);
}

.nav-icon {
  font-size: 1rem;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

.nav-label {
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Collapsed state: center icons */
.sidebar-collapsed .nav-item {
  justify-content: center;
  padding: 10px;
}

.sidebar-collapsed .nav-item-active::before {
  display: none;
}

.sidebar-collapsed .nav-group-title {
  display: none;
}

/* Footer */
.sidebar-footer {
  padding: 8px;
  border-top: 1px solid var(--sidebar-border, #e2e8f0);
}

.platform-link {
  margin-bottom: 8px;
  color: var(--active-color, #4f46e5) !important;
  font-weight: 600 !important;
}

.platform-link:hover {
  background: var(--active-bg, #eef2ff);
}

.sidebar-collapsed .platform-link {
  justify-content: center;
  padding: 10px;
}

.footer-version {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.68rem;
  color: var(--text-muted, #94a3b8);
  font-weight: 500;
}

.footer-logo {
  width: 18px;
  height: 18px;
  display: block;
  flex-shrink: 0;
  filter: drop-shadow(0 0 6px rgba(129, 140, 248, 0.4));
}

/* Fade transition for overlay */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
