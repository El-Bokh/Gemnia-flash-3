<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getPermissionMatrix, assignPermissions } from '@/services/roleService'
import type { PermissionMatrix, MatrixRow, MatrixPermissionRef } from '@/types/roles'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

const emit = defineEmits<{ (e: 'role-updated'): void }>()

const loading = ref(true)
const saving = ref<number | null>(null)
const matrix = ref<MatrixRow[]>([])
const allPermissions = ref<MatrixPermissionRef[]>([])
const permGroups = ref<string[]>([])

// Editable state: roleId -> Set of enabled permission ids
const editState = ref<Map<number, Set<number>>>(new Map())
const dirty = ref<Set<number>>(new Set())

onMounted(fetchMatrix)

async function fetchMatrix() {
  loading.value = true
  try {
    const res = await getPermissionMatrix()
    matrix.value = res.data.roles
    allPermissions.value = res.data.permissions
    // Extract unique groups
    const groups = new Set<string>()
    for (const p of res.data.permissions) groups.add(p.group || 'General')
    permGroups.value = Array.from(groups)
    // Init edit state
    initState()
  } catch {
    loadMock()
  } finally {
    loading.value = false
  }
}

function initState() {
  editState.value = new Map()
  dirty.value = new Set()
  for (const row of matrix.value) {
    const enabled = new Set(row.permissions.filter(p => p.enabled).map(p => p.id))
    editState.value.set(row.role.id, enabled)
  }
}

function loadMock() {
  const perms: MatrixPermissionRef[] = [
    { id: 1, name: 'View Users', slug: 'users.view', group: 'Users' },
    { id: 2, name: 'Create Users', slug: 'users.create', group: 'Users' },
    { id: 3, name: 'Edit Users', slug: 'users.edit', group: 'Users' },
    { id: 4, name: 'Delete Users', slug: 'users.delete', group: 'Users' },
    { id: 5, name: 'View Roles', slug: 'roles.view', group: 'Roles' },
    { id: 6, name: 'Manage Roles', slug: 'roles.manage', group: 'Roles' },
    { id: 7, name: 'View AI', slug: 'ai.view', group: 'AI' },
    { id: 8, name: 'Create AI', slug: 'ai.create', group: 'AI' },
    { id: 9, name: 'Delete AI', slug: 'ai.delete', group: 'AI' },
    { id: 10, name: 'View Plans', slug: 'plans.view', group: 'Plans' },
    { id: 11, name: 'Manage Plans', slug: 'plans.manage', group: 'Plans' },
    { id: 12, name: 'View Payments', slug: 'payments.view', group: 'Payments' },
    { id: 13, name: 'Process Refunds', slug: 'payments.refund', group: 'Payments' },
    { id: 14, name: 'View Settings', slug: 'settings.view', group: 'Settings' },
    { id: 15, name: 'Manage Settings', slug: 'settings.manage', group: 'Settings' },
  ]
  allPermissions.value = perms

  const roles = [
    { id: 1, name: 'Super Admin', slug: 'super-admin', users_count: 2 },
    { id: 2, name: 'Admin', slug: 'admin', users_count: 5 },
    { id: 3, name: 'Moderator', slug: 'moderator', users_count: 8 },
    { id: 4, name: 'User', slug: 'user', users_count: 245 },
    { id: 5, name: 'Viewer', slug: 'viewer', users_count: 32 },
  ]
  const enabledMap: Record<number, number[]> = {
    1: perms.map(p => p.id),
    2: [1, 2, 3, 4, 5, 6, 7, 8, 10, 11, 12, 14],
    3: [1, 3, 7, 8, 12],
    4: [1, 7, 8, 10, 12],
    5: [1, 7, 10, 12, 14],
  }

  matrix.value = roles.map(role => ({
    role,
    permissions: perms.map(p => ({
      ...p,
      enabled: enabledMap[role.id]?.includes(p.id) ?? false,
    })),
  }))

  const groups = new Set<string>()
  for (const p of perms) groups.add(p.group || 'General')
  permGroups.value = Array.from(groups)
  initState()
}

function togglePerm(roleId: number, permId: number) {
  const set = editState.value.get(roleId)
  if (!set) return
  if (set.has(permId)) set.delete(permId)
  else set.add(permId)
  dirty.value.add(roleId)
}

function isEnabled(roleId: number, permId: number) {
  return editState.value.get(roleId)?.has(permId) ?? false
}

function getPermsForGroup(group: string) {
  return allPermissions.value.filter(p => (p.group || 'General') === group)
}

async function saveRole(roleId: number) {
  saving.value = roleId
  try {
    const perms = Array.from(editState.value.get(roleId) || [])
    await assignPermissions(roleId, { permissions: perms })
    dirty.value.delete(roleId)
    emit('role-updated')
  } catch { /* handled */ }
  finally { saving.value = null }
}

function roleColor(slug: string) {
  const map: Record<string, string> = {
    'super-admin': '#ef4444', admin: '#f59e0b', moderator: '#8b5cf6',
    user: '#3b82f6', viewer: '#6b7280',
  }
  return map[slug] || '#6366f1'
}
</script>

<template>
  <div class="matrix-container">
    <div v-if="loading" class="matrix-loading">
      <i class="pi pi-spin pi-spinner" /> Loading permission matrix…
    </div>

    <div v-else class="matrix-scroll">
      <table class="matrix-table">
        <thead>
          <tr>
            <th class="th-corner">
              <span class="corner-label">Permissions</span>
            </th>
            <th v-for="row in matrix" :key="row.role.id" class="th-role">
              <div class="role-head">
                <span class="rh-dot" :style="{ background: roleColor(row.role.slug) }" />
                <span class="rh-name">{{ row.role.name }}</span>
                <span class="rh-users">{{ row.role.users_count }}</span>
              </div>
              <Button
                v-if="dirty.has(row.role.id)"
                icon="pi pi-save"
                label="Save"
                size="small"
                severity="success"
                class="rh-save"
                :loading="saving === row.role.id"
                @click="saveRole(row.role.id)"
              />
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in permGroups" :key="group">
            <!-- Group header row -->
            <tr class="group-row">
              <td class="group-cell" :colspan="matrix.length + 1">
                <span class="g-dot" :style="{ background: roleColor(group.toLowerCase()) }" />
                {{ group }}
              </td>
            </tr>
            <tr v-for="perm in getPermsForGroup(group)" :key="perm.id" class="perm-row">
              <td class="perm-cell">
                <span class="perm-name">{{ perm.name }}</span>
                <span class="perm-slug">{{ perm.slug }}</span>
              </td>
              <td
                v-for="row in matrix"
                :key="row.role.id"
                class="check-cell"
                :class="{ enabled: isEnabled(row.role.id, perm.id) }"
                @click="togglePerm(row.role.id, perm.id)"
              >
                <Checkbox
                  :modelValue="isEnabled(row.role.id, perm.id)"
                  :binary="true"
                  @click.stop="togglePerm(row.role.id, perm.id)"
                  class="matrix-check"
                />
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Mobile hint -->
    <p class="scroll-hint d-mobile-only">
      <i class="pi pi-arrows-h" /> Scroll horizontally to see all roles
    </p>
  </div>
</template>

<style scoped>
.matrix-container { display: flex; flex-direction: column; gap: 8px; }
.matrix-loading { font-size: 0.72rem; color: var(--text-muted); padding: 24px 0; text-align: center; }

.matrix-scroll {
  overflow-x: auto;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
  scrollbar-width: thin;
}

.matrix-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.72rem;
  min-width: 600px;
}

/* Header */
.th-corner {
  position: sticky; left: 0; z-index: 3;
  background: var(--hover-bg);
  padding: 8px 12px;
  text-align: left;
  border-bottom: 2px solid var(--card-border);
  min-width: 160px;
}
.corner-label { font-size: 0.66rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

.th-role {
  background: var(--hover-bg);
  padding: 8px 10px;
  text-align: center;
  border-bottom: 2px solid var(--card-border);
  border-left: 1px solid var(--card-border);
  min-width: 90px;
  vertical-align: top;
}
.role-head { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.rh-dot { width: 8px; height: 8px; border-radius: 50%; }
.rh-name { font-size: 0.68rem; font-weight: 700; color: var(--text-primary); }
.rh-users { font-size: 0.55rem; color: var(--text-muted); }
.rh-save { margin-top: 4px; font-size: 0.58rem !important; padding: 2px 8px !important; }

/* Group row */
.group-row td { background: var(--hover-bg); }
.group-cell {
  padding: 5px 12px;
  font-size: 0.66rem; font-weight: 700; color: var(--text-secondary);
  text-transform: uppercase; letter-spacing: 0.04em;
  border-bottom: 1px solid var(--card-border);
  display: flex; align-items: center; gap: 6px;
}
.g-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }

/* Permission row */
.perm-row:hover td { background: var(--hover-bg); }
.perm-cell {
  position: sticky; left: 0; z-index: 2;
  background: var(--card-bg);
  padding: 5px 12px;
  border-bottom: 1px solid var(--card-border);
  min-width: 160px;
}
.perm-row:hover .perm-cell { background: var(--hover-bg); }
.perm-name { display: block; font-size: 0.68rem; font-weight: 500; color: var(--text-primary); }
.perm-slug { font-size: 0.56rem; color: var(--text-muted); }

.check-cell {
  text-align: center;
  padding: 4px 8px;
  border-bottom: 1px solid var(--card-border);
  border-left: 1px solid var(--card-border);
  cursor: pointer;
  transition: background 0.1s;
  background: var(--card-bg);
}
.check-cell:hover { background: var(--hover-bg); }
.check-cell.enabled {
  background: color-mix(in srgb, var(--active-color) 6%, var(--card-bg));
}

.matrix-check { cursor: pointer; }

/* Scroll hint */
.scroll-hint {
  font-size: 0.62rem; color: var(--text-muted); text-align: center; margin: 0;
}
.scroll-hint i { font-size: 0.7rem; }

.d-mobile-only { display: block; }
@media (min-width: 768px) { .d-mobile-only { display: none; } }
</style>
