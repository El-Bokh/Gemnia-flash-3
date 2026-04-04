<script setup lang="ts">
import { ref, watch } from 'vue'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import { getRole } from '@/services/roleService'
import type { RoleDetail } from '@/types/roles'

const props = defineProps<{
  visible: boolean
  roleId: number | null
}>()
const emit = defineEmits<{ (e: 'update:visible', val: boolean): void }>()

const loading = ref(false)
const role = ref<RoleDetail | null>(null)

watch(() => props.visible, async (open) => {
  if (!open || !props.roleId) { role.value = null; return }
  loading.value = true
  try {
    const res = await getRole(props.roleId)
    role.value = res.data
  } catch {
    loadMock()
  } finally {
    loading.value = false
  }
})

function loadMock() {
  role.value = {
    id: props.roleId!,
    name: 'Admin',
    slug: 'admin',
    description: 'Administrative access with most permissions',
    is_default: false,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-04-01T10:00:00Z',
    permissions: [
      { id: 1, name: 'View Users', slug: 'users.view', group: 'Users', description: 'View user list and details' },
      { id: 2, name: 'Create Users', slug: 'users.create', group: 'Users', description: 'Create new users' },
      { id: 3, name: 'Edit Users', slug: 'users.edit', group: 'Users', description: 'Edit existing users' },
      { id: 4, name: 'Delete Users', slug: 'users.delete', group: 'Users', description: 'Delete users' },
      { id: 5, name: 'View Roles', slug: 'roles.view', group: 'Roles', description: null },
      { id: 6, name: 'Manage Roles', slug: 'roles.manage', group: 'Roles', description: null },
      { id: 7, name: 'View AI Requests', slug: 'ai.view', group: 'AI', description: null },
      { id: 8, name: 'Create AI Requests', slug: 'ai.create', group: 'AI', description: null },
      { id: 10, name: 'View Plans', slug: 'plans.view', group: 'Plans', description: null },
      { id: 11, name: 'Manage Plans', slug: 'plans.manage', group: 'Plans', description: null },
      { id: 12, name: 'View Payments', slug: 'payments.view', group: 'Payments', description: null },
      { id: 14, name: 'View Settings', slug: 'settings.view', group: 'Settings', description: null },
    ],
    permissions_grouped: [
      { group: 'Users', permissions: [{ id: 1, name: 'View Users', slug: 'users.view' }, { id: 2, name: 'Create Users', slug: 'users.create' }, { id: 3, name: 'Edit Users', slug: 'users.edit' }, { id: 4, name: 'Delete Users', slug: 'users.delete' }] },
      { group: 'Roles', permissions: [{ id: 5, name: 'View Roles', slug: 'roles.view' }, { id: 6, name: 'Manage Roles', slug: 'roles.manage' }] },
      { group: 'AI', permissions: [{ id: 7, name: 'View AI Requests', slug: 'ai.view' }, { id: 8, name: 'Create AI Requests', slug: 'ai.create' }] },
      { group: 'Plans', permissions: [{ id: 10, name: 'View Plans', slug: 'plans.view' }, { id: 11, name: 'Manage Plans', slug: 'plans.manage' }] },
      { group: 'Payments', permissions: [{ id: 12, name: 'View Payments', slug: 'payments.view' }] },
      { group: 'Settings', permissions: [{ id: 14, name: 'View Settings', slug: 'settings.view' }] },
    ],
    permissions_count: 12,
    users_count: 5,
    recent_users: [
      { id: 1, name: 'Omar Ali', email: 'omar@flash.io', avatar: null, status: 'active' },
      { id: 2, name: 'Sara Ahmed', email: 'sara@flash.io', avatar: null, status: 'active' },
      { id: 3, name: 'Karim Mostafa', email: 'karim@flash.io', avatar: null, status: 'suspended' },
      { id: 4, name: 'Mona Khaled', email: 'mona@flash.io', avatar: null, status: 'active' },
      { id: 5, name: 'Youssef Nabil', email: 'youssef@flash.io', avatar: null, status: 'active' },
    ],
  }
}

function close() { emit('update:visible', false) }

function fmtDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function statusSev(s: string): 'success' | 'warn' | 'danger' | 'info' | 'secondary' {
  return { active: 'success' as const, suspended: 'warn' as const, banned: 'danger' as const }[s] || 'secondary'
}

function initials(name: string) {
  return name.split(' ').map(n => n[0]).join('').substring(0, 2)
}

function groupColor(group: string) {
  const map: Record<string, string> = {
    Users: '#3b82f6', Roles: '#f59e0b', AI: '#8b5cf6',
    Plans: '#10b981', Payments: '#ef4444', Settings: '#6b7280',
  }
  return map[group] || '#6366f1'
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="close"
    header="Role Detail"
    :modal="true"
    position="right"
    :style="{ width: '520px', maxWidth: '95vw', height: '100vh', margin: 0, borderRadius: 0 }"
    :draggable="false"
    class="detail-drawer"
  >
    <div v-if="loading" class="drawer-loading">
      <i class="pi pi-spin pi-spinner" style="font-size: 1.4rem; color: var(--text-muted)" />
    </div>

    <div v-else-if="role" class="drawer-content">
      <!-- Header -->
      <div class="role-header">
        <div class="rh-icon" :style="{ background: groupColor(role.slug) }">
          <i class="pi pi-shield" />
        </div>
        <div class="rh-info">
          <h2>{{ role.name }}</h2>
          <span class="rh-slug">{{ role.slug }}</span>
        </div>
        <Tag v-if="role.is_default" value="Default" severity="info" />
      </div>

      <p class="rh-desc" v-if="role.description">{{ role.description }}</p>

      <!-- Stats -->
      <div class="stats-row">
        <div class="stat-box">
          <i class="pi pi-lock" />
          <span class="stat-num">{{ role.permissions_count }}</span>
          <span class="stat-lbl">Permissions</span>
        </div>
        <div class="stat-box">
          <i class="pi pi-users" />
          <span class="stat-num">{{ role.users_count }}</span>
          <span class="stat-lbl">Users</span>
        </div>
        <div class="stat-box">
          <i class="pi pi-calendar" />
          <span class="stat-num" style="font-size: 0.68rem">{{ fmtDate(role.created_at) }}</span>
          <span class="stat-lbl">Created</span>
        </div>
      </div>

      <!-- Permissions Grouped -->
      <div class="section">
        <h3 class="section-title">Permissions by Group</h3>
        <div class="perm-groups">
          <div v-for="g in role.permissions_grouped" :key="g.group" class="pg-block">
            <div class="pg-head">
              <span class="pg-dot" :style="{ background: groupColor(g.group) }" />
              <span class="pg-label">{{ g.group }}</span>
              <span class="pg-count">{{ g.permissions.length }}</span>
            </div>
            <div class="pg-chips">
              <span v-for="p in g.permissions" :key="p.id" class="perm-chip">
                <i class="pi pi-check" />
                {{ p.name }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Users -->
      <div class="section">
        <h3 class="section-title">Recent Users ({{ role.users_count }})</h3>
        <div class="user-list">
          <div v-for="u in role.recent_users" :key="u.id" class="user-row">
            <div class="ur-avatar">
              <img v-if="u.avatar" :src="u.avatar" :alt="u.name" />
              <span v-else>{{ initials(u.name) }}</span>
            </div>
            <div class="ur-info">
              <span class="ur-name">{{ u.name }}</span>
              <span class="ur-email">{{ u.email }}</span>
            </div>
            <Tag :value="u.status" :severity="statusSev(u.status)" class="ur-tag" />
          </div>
        </div>
      </div>

      <!-- Meta -->
      <div class="meta-section">
        <div class="meta-row"><span>ID</span><span>#{{ role.id }}</span></div>
        <div class="meta-row"><span>Created</span><span>{{ fmtDate(role.created_at) }}</span></div>
        <div class="meta-row"><span>Updated</span><span>{{ fmtDate(role.updated_at) }}</span></div>
      </div>
    </div>
  </Dialog>
</template>

<style scoped>
.drawer-loading { display: flex; align-items: center; justify-content: center; height: 200px; }
.drawer-content { display: flex; flex-direction: column; gap: 14px; }

/* Header */
.role-header { display: flex; align-items: center; gap: 10px; }
.rh-icon {
  width: 42px; height: 42px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 1rem; flex-shrink: 0;
}
.rh-info { flex: 1; }
.rh-info h2 { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.rh-slug { font-size: 0.66rem; color: var(--text-muted); }
.rh-desc { font-size: 0.72rem; color: var(--text-muted); margin: 0; line-height: 1.4; }

/* Stats */
.stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.stat-box {
  display: flex; flex-direction: column; align-items: center;
  padding: 8px 4px; border-radius: 8px;
  background: var(--hover-bg); border: 1px solid var(--card-border);
}
.stat-box i { font-size: 0.72rem; color: var(--text-muted); }
.stat-num { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); margin-top: 2px; }
.stat-lbl { font-size: 0.58rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }

/* Permissions grouped */
.section { display: flex; flex-direction: column; gap: 6px; }
.section-title {
  font-size: 0.7rem; font-weight: 700; color: var(--text-secondary);
  text-transform: uppercase; letter-spacing: 0.05em; margin: 0;
}
.perm-groups { display: flex; flex-direction: column; gap: 6px; }
.pg-block {
  border: 1px solid var(--card-border); border-radius: 8px; overflow: hidden;
}
.pg-head {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 10px; background: var(--hover-bg);
}
.pg-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.pg-label { font-size: 0.72rem; font-weight: 700; color: var(--text-primary); flex: 1; }
.pg-count {
  font-size: 0.58rem; font-weight: 600; color: var(--text-muted);
  background: var(--card-bg); border-radius: 9px; padding: 1px 7px;
}
.pg-chips {
  display: flex; flex-wrap: wrap; gap: 4px; padding: 6px 10px;
}
.perm-chip {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 0.62rem; padding: 2px 8px; border-radius: 12px;
  background: color-mix(in srgb, var(--active-color) 10%, transparent);
  color: var(--active-color); font-weight: 500;
}
.perm-chip i { font-size: 0.5rem; }

/* Users */
.user-list { display: flex; flex-direction: column; gap: 0; border: 1px solid var(--card-border); border-radius: 8px; overflow: hidden; }
.user-row {
  display: flex; align-items: center; gap: 8px;
  padding: 7px 10px; border-bottom: 1px solid var(--card-border);
}
.user-row:last-child { border-bottom: none; }
.ur-avatar {
  width: 26px; height: 26px; border-radius: 7px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff; font-size: 0.58rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; overflow: hidden;
}
.ur-avatar img { width: 100%; height: 100%; object-fit: cover; }
.ur-info { flex: 1; min-width: 0; }
.ur-name { font-size: 0.72rem; font-weight: 600; color: var(--text-primary); display: block; }
.ur-email { font-size: 0.6rem; color: var(--text-muted); }
.ur-tag { font-size: 0.55rem !important; padding: 1px 6px !important; }

/* Meta */
.meta-section {
  display: flex; flex-direction: column; gap: 0;
  border: 1px solid var(--card-border); border-radius: 8px; overflow: hidden;
}
.meta-row {
  display: flex; justify-content: space-between; padding: 6px 10px;
  font-size: 0.68rem; border-bottom: 1px solid var(--card-border);
}
.meta-row:last-child { border-bottom: none; }
.meta-row span:first-child { color: var(--text-muted); font-weight: 500; }
.meta-row span:last-child { color: var(--text-primary); font-weight: 500; }

/* Dialog drawer overrides */
:deep(.detail-drawer) { margin: 0 !important; border-radius: 0 !important; }
:deep(.detail-drawer .p-dialog-header) {
  background: var(--card-bg); border-color: var(--card-border);
  color: var(--text-primary); padding: 10px 16px;
}
:deep(.detail-drawer .p-dialog-content) {
  background: var(--card-bg); color: var(--text-primary);
  padding: 12px 16px; overflow-y: auto;
}
</style>
