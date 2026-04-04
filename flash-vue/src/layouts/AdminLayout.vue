<script setup lang="ts">
import { useLayoutStore } from '@/stores/layout'
import AdminTopbar from '@/components/layout/AdminTopbar.vue'
import AdminSidebar from '@/components/layout/AdminSidebar.vue'
import { computed } from 'vue'

const layout = useLayoutStore()

const mainClass = computed(() => ({
  'main-content': true,
  'main-collapsed': layout.sidebarCollapsed,
}))
</script>

<template>
  <div class="admin-layout">
    <AdminTopbar />
    <AdminSidebar />
    <main :class="mainClass">
      <router-view />
    </main>
  </div>
</template>

<style scoped>
.admin-layout {
  --admin-topbar-height: 52px;
  --admin-sidebar-width: 230px;
  --admin-sidebar-collapsed-width: 60px;
  min-height: 100vh;
  background: var(--layout-bg, #f8fafc);
  padding-top: var(--admin-topbar-height);
}

.main-content {
  margin-left: var(--admin-sidebar-width);
  min-height: calc(100vh - var(--admin-topbar-height));
  padding: 16px;
  transition: margin-left 0.2s ease;
}

.main-collapsed {
  margin-left: var(--admin-sidebar-collapsed-width);
}

@media (max-width: 1023px) {
  .main-content,
  .main-collapsed {
    margin-left: 0;
    padding: 14px;
  }
}
</style>
