<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getRoles, deleteRole } from '@/services/roleService'
import type { Role, ListRolesParams } from '@/types/roles'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dialog from 'primevue/dialog'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import RoleFormDialog from '@/components/roles/RoleFormDialog.vue'
import RoleDetailDrawer from '@/components/roles/RoleDetailDrawer.vue'
import PermissionMatrixView from '@/components/roles/PermissionMatrixView.vue'

// ── State ───────────────────────────────────────────────────
const activeTab = ref('roles')
const loading = ref(true)
const roles = ref<Role[]>([])
const search = ref('')
const sortField = ref('name')
const sortOrder = ref<'asc' | 'desc'>('asc')

// Dialogs
const showForm = ref(false)
const editRole = ref<Role | null>(null)
const showDetail = ref(false)
const detailRoleId = ref<number | null>(null)
const showDeleteConfirm = ref(false)
const deleteTarget = ref<Role | null>(null)
const actionLoading = ref(false)

const { t } = useI18n()

// ── Fetch ───────────────────────────────────────────────────
async function fetchRoles() {
  loading.value = true
  try {
    const params: ListRolesParams = {
      with_counts: true,
      sort_by: sortField.value as ListRolesParams['sort_by'],
      sort_dir: sortOrder.value,
    }
    if (search.value) params.search = search.value
    const res = await getRoles(params)
    roles.value = res.data
  } catch {
    loadMockRoles()
  } finally {
    loading.value = false
  }
}

function loadMockRoles() {
  roles.value = [
    { id: 1, name: 'Super Admin', slug: 'super-admin', description: 'Full system access with all permissions', is_default: false, permissions_count: 42, users_count: 2, created_at: '2026-01-01T00:00:00Z', updated_at: '2026-04-01T10:00:00Z' },
    { id: 2, name: 'Admin', slug: 'admin', description: 'Administrative access with most permissions', is_default: false, permissions_count: 35, users_count: 5, created_at: '2026-01-01T00:00:00Z', updated_at: '2026-04-01T10:00:00Z' },
    { id: 3, name: 'Moderator', slug: 'moderator', description: 'Content moderation and user management', is_default: false, permissions_count: 18, users_count: 8, created_at: '2026-01-15T00:00:00Z', updated_at: '2026-03-20T10:00:00Z' },
    { id: 4, name: 'User', slug: 'user', description: 'Default registered user role', is_default: true, permissions_count: 12, users_count: 245, created_at: '2026-01-01T00:00:00Z', updated_at: '2026-02-15T10:00:00Z' },
    { id: 5, name: 'Viewer', slug: 'viewer', description: 'Read-only access to public content', is_default: false, permissions_count: 5, users_count: 32, created_at: '2026-02-01T00:00:00Z', updated_at: '2026-03-10T10:00:00Z' },
  ]
}

onMounted(fetchRoles)

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(fetchRoles, 400)
})

// ── Sort ────────────────────────────────────────────────────
function onSort(event: any) {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder === 1 ? 'asc' : 'desc'
  fetchRoles()
}

// ── Actions ─────────────────────────────────────────────────
function openCreate() {
  editRole.value = null
  showForm.value = true
}

function openEdit(role: Role) {
  editRole.value = role
  showForm.value = true
}

function openDetail(role: Role) {
  detailRoleId.value = role.id
  showDetail.value = true
}

function confirmDelete(role: Role) {
  deleteTarget.value = role
  showDeleteConfirm.value = true
}

async function handleDelete() {
  if (!deleteTarget.value) return
  actionLoading.value = true
  try {
    await deleteRole(deleteTarget.value.id)
    showDeleteConfirm.value = false
    fetchRoles()
  } catch { /* handled */ }
  finally { actionLoading.value = false }
}

function onFormSaved() {
  showForm.value = false
  fetchRoles()
}

// ── Helpers ─────────────────────────────────────────────────
function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function roleColor(slug: string) {
  const map: Record<string, string> = {
    'super-admin': '#ef4444',
    admin: '#f59e0b',
    moderator: '#8b5cf6',
    user: '#3b82f6',
    viewer: '#6b7280',
  }
  return map[slug] || '#6366f1'
}
</script>

<template>
  <div class="roles-page">
    <!-- Toolbar -->
    <div class="page-toolbar">
      <h1 class="page-title">{{ t('roles.title') }}</h1>
      <Button icon="pi pi-plus" :label="t('roles.addRole')" size="small" @click="openCreate" />
    </div>

    <!-- Main Tabs -->
    <Tabs v-model:value="activeTab" class="roles-tabs">
      <TabList>
        <Tab value="roles">
          <i class="pi pi-users" style="font-size: 0.72rem" />
          <span>{{ t('roles.rolesTab') }}</span>
        </Tab>
        <Tab value="matrix">
          <i class="pi pi-th-large" style="font-size: 0.72rem" />
          <span>{{ t('roles.permissionMatrix') }}</span>
        </Tab>
      </TabList>

      <TabPanels>
        <!-- ═══ Roles List Tab ═══ -->
        <TabPanel value="roles">
          <!-- Filters -->
          <div class="filters-bar">
            <span class="filter-search">
              <i class="pi pi-search" />
              <InputText v-model="search" :placeholder="t('roles.searchPlaceholder')" size="small" class="filter-input" />
            </span>
            <span class="filter-count">{{ t('roles.rolesCount', { count: roles.length }) }}</span>
          </div>

          <!-- Cards Grid (mobile-first) -->
          <div class="roles-grid d-mobile">
            <div
              v-for="role in roles"
              :key="role.id"
              class="role-card"
              @click="openDetail(role)"
            >
              <div class="rc-header">
                <div class="rc-icon" :style="{ background: roleColor(role.slug) }">
                  <i class="pi pi-shield" />
                </div>
                <div class="rc-info">
                  <span class="rc-name">{{ role.name }}</span>
                  <span class="rc-slug">{{ role.slug }}</span>
                </div>
                <Tag v-if="role.is_default" :value="t('roles.default')" severity="info" class="rc-default" />
              </div>
              <p class="rc-desc">{{ role.description || t('roles.noDescription') }}</p>
              <div class="rc-stats">
                <div class="rc-stat">
                  <i class="pi pi-lock" />
                  <span>{{ role.permissions_count ?? 0 }}</span>
                  <span class="rc-stat-label">{{ t('roles.permissions') }}</span>
                </div>
                <div class="rc-stat">
                  <i class="pi pi-users" />
                  <span>{{ role.users_count ?? 0 }}</span>
                  <span class="rc-stat-label">{{ t('roles.users') }}</span>
                </div>
              </div>
              <div class="rc-actions" @click.stop>
                <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openDetail(role)" v-tooltip.top="'View'" />
                <Button icon="pi pi-pencil" severity="secondary" text rounded size="small" @click="openEdit(role)" v-tooltip.top="'Edit'" />
                <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete(role)" v-tooltip.top="'Delete'" :disabled="role.is_default" />
              </div>
            </div>
          </div>

          <!-- Table (desktop) -->
          <div class="table-card d-desktop">
            <DataTable
              :value="roles"
              :loading="loading"
              sortMode="single"
              :sortField="sortField"
              :sortOrder="sortOrder === 'asc' ? 1 : -1"
              @sort="onSort"
              stripedRows
              size="small"
              scrollable
              class="roles-table"
              dataKey="id"
            >
              <!-- Role -->
              <Column field="name" :header="t('common.role')" sortable style="min-width: 200px">
                <template #body="{ data }">
                  <div class="role-cell" @click="openDetail(data)" style="cursor: pointer">
                    <div class="r-icon" :style="{ background: roleColor(data.slug) }">
                      <i class="pi pi-shield" />
                    </div>
                    <div class="r-info">
                      <span class="r-name">{{ data.name }}</span>
                      <span class="r-slug">{{ data.slug }}</span>
                    </div>
                    <Tag v-if="data.is_default" :value="t('roles.default')" severity="info" class="r-default" />
                  </div>
                </template>
              </Column>

              <!-- Description -->
              <Column field="description" :header="t('roles.description')" style="min-width: 180px">
                <template #body="{ data }">
                  <span class="r-desc">{{ data.description || '—' }}</span>
                </template>
              </Column>

              <!-- Permissions -->
              <Column field="permissions_count" :header="t('roles.permissions')" sortable style="min-width: 100px">
                <template #body="{ data }">
                  <div class="r-metric">
                    <i class="pi pi-lock" />
                    <span>{{ data.permissions_count ?? 0 }}</span>
                  </div>
                </template>
              </Column>

              <!-- Users -->
              <Column field="users_count" :header="t('roles.users')" sortable style="min-width: 80px">
                <template #body="{ data }">
                  <div class="r-metric">
                    <i class="pi pi-users" />
                    <span>{{ data.users_count ?? 0 }}</span>
                  </div>
                </template>
              </Column>

              <!-- Created -->
              <Column field="created_at" :header="t('roles.created')" sortable style="min-width: 100px">
                <template #body="{ data }">
                  <span class="r-date">{{ formatDate(data.created_at) }}</span>
                </template>
              </Column>

              <!-- Actions -->
              <Column header="" style="min-width: 90px; text-align: right" frozen alignFrozen="right">
                <template #body="{ data }">
                  <div class="r-actions">
                    <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openDetail(data)" v-tooltip.left="'View'" />
                    <Button icon="pi pi-pencil" severity="secondary" text rounded size="small" @click="openEdit(data)" v-tooltip.left="'Edit'" />
                    <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete(data)" v-tooltip.left="'Delete'" :disabled="data.is_default" />
                  </div>
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>

        <!-- ═══ Permission Matrix Tab ═══ -->
        <TabPanel value="matrix">
          <PermissionMatrixView @role-updated="fetchRoles" />
        </TabPanel>
      </TabPanels>
    </Tabs>

    <!-- Create/Edit Dialog -->
    <RoleFormDialog
      v-model:visible="showForm"
      :role="editRole"
      @saved="onFormSaved"
    />

    <!-- Detail Drawer -->
    <RoleDetailDrawer
      v-model:visible="showDetail"
      :roleId="detailRoleId"
    />

    <!-- Delete Confirm -->
    <Dialog v-model:visible="showDeleteConfirm" :header="t('roles.deleteRole')" :modal="true" :style="{ width: '360px' }">
      <div class="confirm-body">
        <i class="pi pi-exclamation-triangle confirm-icon" />
        <p>{{ t('roles.deleteConfirm', { name: deleteTarget?.name }) }}</p>
        <p class="confirm-sub" v-if="deleteTarget && (deleteTarget.users_count ?? 0) > 0">
          {{ t('roles.deleteConfirmHasUsers', { count: deleteTarget.users_count }) }}
        </p>
        <p class="confirm-sub" v-else>{{ t('roles.deleteConfirmNoUndo') }}</p>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text size="small" @click="showDeleteConfirm = false" />
        <Button :label="t('common.delete')" severity="danger" size="small" :loading="actionLoading" @click="handleDelete" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.roles-page { display: flex; flex-direction: column; gap: 10px; }

.page-toolbar {
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.page-title { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin: 0; }

/* ── Tabs ─────────────────────────── */
:deep(.roles-tabs .p-tablist) { background: transparent; }
:deep(.roles-tabs .p-tab) {
  font-size: 0.72rem !important; padding: 7px 12px !important;
  color: var(--text-muted) !important; background: transparent !important;
  border: none !important; display: flex; align-items: center; gap: 5px;
}
:deep(.roles-tabs .p-tab-active) { color: var(--active-color) !important; }
:deep(.roles-tabs .p-tabpanels) { background: transparent; padding: 8px 0 !important; }

/* ── Filters ──────────────────────── */
.filters-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
.filter-search {
  position: relative; flex: 1; min-width: 160px; max-width: 280px;
}
.filter-search i {
  position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
  font-size: 0.8rem; color: var(--text-muted); z-index: 1;
}
.filter-input { width: 100%; padding-left: 32px !important; font-size: 0.78rem !important; }
.filter-count { font-size: 0.7rem; color: var(--text-muted); margin-left: auto; }

/* ── Mobile Cards ─────────────────── */
.d-mobile { display: grid; }
.d-desktop { display: none; }
@media (min-width: 768px) {
  .d-mobile { display: none; }
  .d-desktop { display: block; }
}

.roles-grid {
  grid-template-columns: 1fr;
  gap: 8px;
}
@media (min-width: 480px) {
  .roles-grid { grid-template-columns: repeat(2, 1fr); }
}

.role-card {
  padding: 12px;
  border-radius: 10px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
  display: flex; flex-direction: column; gap: 8px;
}
.role-card:hover {
  border-color: var(--active-color);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--active-color) 20%, transparent);
}

.rc-header { display: flex; align-items: center; gap: 8px; }
.rc-icon {
  width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 0.72rem;
}
.rc-info { flex: 1; min-width: 0; }
.rc-name { font-size: 0.82rem; font-weight: 700; color: var(--text-primary); display: block; }
.rc-slug { font-size: 0.62rem; color: var(--text-muted); }
.rc-default { font-size: 0.55rem !important; padding: 1px 6px !important; }
.rc-desc { font-size: 0.68rem; color: var(--text-muted); margin: 0; line-height: 1.3; }
.rc-stats { display: flex; gap: 16px; }
.rc-stat {
  display: flex; align-items: center; gap: 4px;
  font-size: 0.72rem; font-weight: 600; color: var(--text-primary);
}
.rc-stat i { font-size: 0.62rem; color: var(--text-muted); }
.rc-stat-label { font-size: 0.6rem; font-weight: 400; color: var(--text-muted); }
.rc-actions { display: flex; gap: 0; margin-top: -2px; }

/* ── Desktop Table ────────────────── */
.table-card {
  border-radius: 10px; border: 1px solid var(--card-border);
  background: var(--card-bg); overflow: hidden;
}
.roles-table { font-size: 0.75rem; }
:deep(.roles-table .p-datatable-table-container) { border-radius: 10px; overflow: hidden; background: var(--card-bg); }
:deep(.roles-table .p-datatable-thead > tr > th) {
  background: var(--hover-bg); color: var(--text-secondary);
  border-color: var(--card-border); font-size: 0.66rem; font-weight: 700;
  letter-spacing: 0.06em; text-transform: uppercase; padding: 10px 12px;
}
:deep(.roles-table .p-datatable-tbody > tr) {
  background: var(--card-bg); color: var(--text-primary); transition: background 0.12s;
}
:deep(.roles-table.p-datatable-striped .p-datatable-tbody > tr:nth-child(even)) {
  background: color-mix(in srgb, var(--card-bg) 76%, var(--hover-bg) 24%);
}
:deep(.roles-table .p-datatable-tbody > tr:hover) { background: var(--hover-bg); }
:deep(.roles-table .p-datatable-tbody > tr > td) {
  background: transparent; color: var(--text-primary);
  border-color: var(--card-border); padding: 8px 12px;
}

/* Table cells */
.role-cell { display: flex; align-items: center; gap: 8px; }
.r-icon {
  width: 26px; height: 26px; border-radius: 7px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 0.62rem;
}
.r-info { display: flex; flex-direction: column; min-width: 0; }
.r-name { font-size: 0.78rem; font-weight: 600; color: var(--text-primary); }
.r-slug { font-size: 0.62rem; color: var(--text-muted); }
.r-default { font-size: 0.55rem !important; padding: 1px 6px !important; margin-left: 4px; }
.r-desc { font-size: 0.68rem; color: var(--text-muted); max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.r-metric {
  display: flex; align-items: center; gap: 5px;
  font-size: 0.78rem; font-weight: 600; color: var(--text-primary);
}
.r-metric i { font-size: 0.62rem; color: var(--text-muted); }
.r-date { font-size: 0.68rem; color: var(--text-muted); }
.r-actions { display: flex; align-items: center; gap: 0; }

/* ── Delete confirm ───────────────── */
.confirm-body { text-align: center; padding: 6px 0; }
.confirm-icon { font-size: 2rem; color: #ef4444; margin-bottom: 8px; }
.confirm-body p { font-size: 0.82rem; color: var(--text-primary); margin: 4px 0; }
.confirm-sub { font-size: 0.7rem; color: var(--text-muted); }
</style>
